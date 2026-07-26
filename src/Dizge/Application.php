<?php

declare(strict_types=1);

namespace Dizge;

use Dizge\Data\Database;
use Dizge\Http\HttpException;
use Dizge\Http\Problem;
use Dizge\Bridge\Registry;
use Dizge\Devinim\Renderer;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Http\Router;
use Dizge\Http\Middleware;
use Dizge\Security\Auth;
use Dizge\Security\Csrf;
use Dizge\Security\Gate;
use Dizge\Security\RateLimiter;
use Dizge\Security\Session;
use Dizge\Security\Throttle;
use Dizge\Security\VerifyCsrf;
use Dizge\View\ViewRenderer;
use Dizge\Validation\ValidationException;
use Throwable;

/** Application composition root: router, view renderer, error boundary and Devinim adapter. */
final class Application
{
    private static ?self $instance = null;

    private Router $router;
    private ViewRenderer $views;
    private Renderer $devinim;
    private ?Database $database = null;
    private Session $session;
    private Auth $auth;
    private Csrf $csrf;
    private Gate $gate;
    private Registry $bridge;
    private Container $container;
    private Config $config;
    private ?Throttle $globalThrottle = null;
    /** @var list<Middleware|callable> */
    private array $globalMiddleware;

    public function __construct(private string $basePath, private bool $debug = false)
    {
        $this->router = new Router();
        $this->container = new Container();
        $this->config = new Config($basePath . '/config');
        $this->views = new ViewRenderer($basePath . '/views');
        $this->devinim = new Renderer();
        $this->session = new Session();
        $this->auth = new Auth($this->session);
        $this->csrf = new Csrf($this->session);
        $this->gate = new Gate();
        $this->bridge = new Registry();
        // CSRF is a safe default for cookie-backed browser routes. Its middleware permits GET,
        // HEAD and OPTIONS, so this does not impose a token requirement on read-only pages.
        //
        // The global Throttle is an opt-in resource-exhaustion safety net for the whole app
        // (ADR-0006, docs/auth-threat-model.md): one bucket per client IP shared across every
        // route, sized from config/app.php's `rate_limit.max`/`rate_limit.seconds`. Off by
        // default — the file-backed RateLimiter adds a real disk-lock-and-write cost to every
        // request (~90x controller_dispatch when always-on, see ADR-0006's "Consequences"), so
        // it costs nothing until an operator sets `rate_limit.enabled` to true. When on, it
        // composes with, and never replaces, a route's own stricter per-route Throttle (e.g.
        // routes/demo.php's /demo/api/context). A route can opt back out via the explicit,
        // reviewable `withoutGlobalRateLimit: true` parameter on get()/post() below.
        if ($this->config->get('app.rate_limit.enabled', false)) {
            $this->globalThrottle = new Throttle(
                new RateLimiter($basePath . '/storage/rate-limits'),
                (int) $this->config->get('app.rate_limit.max', 300),
                (int) $this->config->get('app.rate_limit.seconds', 60),
                static fn (Request $request): string => 'global:' . ($request->header('remote-addr') ?? 'unknown'),
            );
        }
        $this->globalMiddleware = $this->globalThrottle !== null ? [new VerifyCsrf($this->csrf), $this->globalThrottle] : [new VerifyCsrf($this->csrf)];
        $this->router->post('/_dizge/bridge', function (Request $request): Response {
            $id = $request->input('id'); $detail = $request->input('detail', []);
            if (!is_string($id) || !is_array($detail)) return Response::json(['error' => 'Invalid bridge payload.'], 422);
            try { return Response::json(['patch' => $this->bridge->dispatch($id, $detail)?->toArray()]); }
            catch (\InvalidArgumentException) { return Response::json(['error' => 'Unknown bridge binding.'], 404); }
        }, $this->globalMiddleware);
    }

    public static function boot(string $basePath): void
    {
        if (self::$instance === null) {
            if (getenv('DIZGE_ENV') === 'production' && (getenv('DIZGECMS_PREVIEW_SECRET') === false || getenv('DIZGECMS_PREVIEW_SECRET') === '')) {
                throw new \LogicException('DIZGECMS_PREVIEW_SECRET must be configured in production.');
            }
            self::$instance = new self($basePath, getenv('DIZGE_DEBUG') === '1');
        }
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \LogicException('DizgePHP is not booted. Require dizge.php before calling app().');
        }

        return self::$instance;
    }

    /**
     * @param callable(Request, array<string, string>): Response|array{class-string, string} $handler
     * @param bool $withoutGlobalRateLimit Explicit, reviewable opt-out from the global default
     *     Throttle only (VerifyCsrf and any $middleware you pass are unaffected). Reserved for a
     *     route with a genuine reason not to share the app-wide bucket — see ADR-0006. Defaults
     *     to false: every route is protected by the global default unless this is deliberately
     *     set at the call site.
     */
    public function get(string $pattern, mixed $handler, array $middleware = [], bool $withoutGlobalRateLimit = false): self
    {
        $this->router->get($pattern, $this->handler($handler), [...$this->globalMiddlewareFor($withoutGlobalRateLimit), ...$middleware]);
        return $this;
    }

    /**
     * @param callable(Request, array<string, string>): Response|array{class-string, string} $handler
     * @param bool $withoutGlobalRateLimit See get()'s parameter of the same name.
     */
    public function post(string $pattern, mixed $handler, array $middleware = [], bool $withoutGlobalRateLimit = false): self
    {
        $this->router->post($pattern, $this->handler($handler), [...$this->globalMiddlewareFor($withoutGlobalRateLimit), ...$middleware]);
        return $this;
    }

    /**
     * @return list<Middleware|callable> The global middleware list, minus the global default
     *     Throttle (by reference identity, never by type) when a route explicitly opts out, or
     *     when the global default isn't enabled at all (nothing to remove either way).
     */
    private function globalMiddlewareFor(bool $withoutGlobalRateLimit): array
    {
        if (!$withoutGlobalRateLimit || $this->globalThrottle === null) return $this->globalMiddleware;
        return array_values(array_filter($this->globalMiddleware, fn (mixed $middleware): bool => $middleware !== $this->globalThrottle));
    }

    /** @param array<string, mixed> $data */
    public function view(string $template, array $data = []): Response
    {
        return Response::html($this->views->render($template, $data));
    }

    public function devinim(): Renderer
    {
        return $this->devinim;
    }

    /** Configures a database explicitly, which is useful in tests and multi-connection applications. */
    public function useDatabase(Database $database): self
    {
        $this->database = $database;
        return $this;
    }

    /** Lazily builds the configured PDO connection or the app-local SQLite database. */
    public function database(): Database
    {
        return $this->database ??= Database::fromEnvironment($this->basePath);
    }

    public function basePath(): string { return $this->basePath; }

    public function auth(): Auth { return $this->auth; }
    public function csrf(): Csrf { return $this->csrf; }
    public function gate(): Gate { return $this->gate; }
    public function bridge(): Registry { return $this->bridge; }
    public function container(): Container { return $this->container; }
    public function config(): Config { return $this->config; }
    /** @return list<array{method:string,pattern:string,middleware:list<Middleware|callable>}> */
    public function routes(): array { return $this->router->routes(); }

    public function dispatch(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
            if (str_starts_with($request->path(), '/api/') && $response->status() === 404) {
                return Problem::response(404, 'Not Found', 'No API route matches this request.');
            }
            return $response;
        } catch (Throwable $exception) {
            if (!$exception instanceof HttpException && !$exception instanceof ValidationException) {
                error_log(sprintf('[dizge] %s: %s', $exception::class, $exception->getMessage()));
            }
            if (str_starts_with($request->path(), '/api/')) {
                if ($exception instanceof ValidationException) {
                    return Problem::response(422, 'Validation failed', 'One or more fields are invalid.', ['errors' => $exception->errors()->all()]);
                }
                $status = $exception instanceof HttpException ? $exception->status() : 500;
                $title = $exception instanceof HttpException ? $exception->title() : 'Internal Server Error';
                $detail = $this->debug ? $exception->getMessage() : ($status >= 500 ? 'An unexpected error occurred.' : $exception->getMessage());
                return Problem::response($status, $title, $detail);
            }
            $message = $this->debug ? \e($exception->getMessage()) : 'Internal Server Error';
            return Response::html('<h1>500</h1><p>' . $message . '</p>', 500);
        }
    }

    public function run(?Request $request = null): void
    {
        $this->dispatch($request ?? Request::fromGlobals())->send();
    }

    /** @param callable(Request, array<string,string>): Response|array{class-string,string} $handler */
    private function handler(mixed $handler): callable
    {
        if (is_callable($handler)) return $handler;
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            return function (Request $request, array $params) use ($handler): Response {
                $controller = $this->container->get($handler[0]);
                $response = $controller->{$handler[1]}($request, $params);
                if (!$response instanceof Response) throw new \LogicException('Controller actions must return Dizge\\Http\\Response.');
                return $response;
            };
        }
        throw new \InvalidArgumentException('Routes require a callable or [Controller::class, method] action.');
    }
}
