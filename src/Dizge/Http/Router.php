<?php

declare(strict_types=1);

namespace Dizge\Http;

/** Explicit, ordered router. Patterns support literal segments and `{parameter}` segments only. */
final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable, middleware: list<Middleware|callable>}> */
    private array $routes = [];

    /** @param callable(Request, array<string, string>): Response $handler */
    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /** @param callable(Request, array<string, string>): Response $handler */
    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /** @param callable(Request, array<string, string>): Response $handler */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        if (!str_starts_with($pattern, '/')) {
            throw new \InvalidArgumentException('Route patterns must start with "/".');
        }
        foreach ($middleware as $entry) {
            if (!$entry instanceof Middleware && !is_callable($entry)) throw new \InvalidArgumentException('Middleware must implement Middleware or be callable.');
        }
        $this->routes[] = ['method' => strtoupper($method), 'pattern' => $this->normalize($pattern), 'handler' => $handler, 'middleware' => $middleware];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if (!$this->allowsMethod($route['method'], $request->method())) continue;
            $params = $this->match($route['pattern'], $request->path());
            if ($params === null) continue;

            $next = fn (Request $current): Response => ($route['handler'])($current, $params);
            foreach (array_reverse($route['middleware']) as $middleware) {
                $previous = $next;
                $next = $middleware instanceof Middleware
                    ? fn (Request $current): Response => $middleware->handle($current, $previous)
                    : fn (Request $current): Response => $middleware($current, $previous);
            }
            $response = $next($request);
            if (!$response instanceof Response) {
                throw new \LogicException('Route handlers must return Dizge\\Http\\Response.');
            }
            return $response;
        }

        return Response::notFound();
    }

    /** @return list<array{method:string,pattern:string,middleware:list<Middleware|callable>}> */
    public function routes(): array
    {
        return array_map(fn (array $route): array => [
            'method' => $route['method'], 'pattern' => $route['pattern'], 'middleware' => $route['middleware'],
        ], $this->routes);
    }

    private function allowsMethod(string $routeMethod, string $requestMethod): bool
    {
        return $routeMethod === $requestMethod || ($requestMethod === 'HEAD' && $routeMethod === 'GET');
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $expected = $this->segments($pattern);
        $actual = $this->segments($path);
        if (count($expected) !== count($actual)) return null;

        $params = [];
        foreach ($expected as $index => $segment) {
            if (preg_match('/^\{([A-Za-z][A-Za-z0-9_]*)\}$/', $segment, $match) === 1) {
                $params[$match[1]] = rawurldecode($actual[$index]);
                continue;
            }
            if ($segment !== $actual[$index]) return null;
        }
        return $params;
    }

    /** @return list<string> */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        return $trimmed === '' ? [] : explode('/', $trimmed);
    }

    private function normalize(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '/' ? $normalized : rtrim($normalized, '/');
    }
}
