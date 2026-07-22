<?php

declare(strict_types=1);

/**
 * DizgePHP public facade.
 *
 * Consumers need only `require 'dizge.php'`; internal framework classes remain namespaced to
 * avoid collisions in an existing PHP application.
 */

spl_autoload_register(static function (string $class): void {
    $prefixes = ['Dizge\\' => __DIR__ . '/src/Dizge/', 'App\\' => __DIR__ . '/app/'];
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/src/Dizge/Http/Request.php';
require_once __DIR__ . '/src/Dizge/Http/Response.php';
require_once __DIR__ . '/src/Dizge/Http/Middleware.php';
require_once __DIR__ . '/src/Dizge/Http/Router.php';
require_once __DIR__ . '/src/Dizge/View/ViewRenderer.php';
require_once __DIR__ . '/src/Dizge/Devinim/Renderer.php';
require_once __DIR__ . '/src/Dizge/Data/Database.php';
require_once __DIR__ . '/src/Dizge/Data/Query.php';
require_once __DIR__ . '/src/Dizge/Data/Migration.php';
require_once __DIR__ . '/src/Dizge/Data/Migrator.php';
require_once __DIR__ . '/src/Dizge/Bridge/Patch.php';
require_once __DIR__ . '/src/Dizge/Bridge/Registry.php';
require_once __DIR__ . '/src/Dizge/Security/Session.php';
require_once __DIR__ . '/src/Dizge/Security/Identity.php';
require_once __DIR__ . '/src/Dizge/Security/AuthenticationException.php';
require_once __DIR__ . '/src/Dizge/Security/AuthorizationException.php';
require_once __DIR__ . '/src/Dizge/Security/Auth.php';
require_once __DIR__ . '/src/Dizge/Security/Gate.php';
require_once __DIR__ . '/src/Dizge/Security/Csrf.php';
require_once __DIR__ . '/src/Dizge/Security/RequireAuth.php';
require_once __DIR__ . '/src/Dizge/Security/RequireAbility.php';
require_once __DIR__ . '/src/Dizge/Security/VerifyCsrf.php';
require_once __DIR__ . '/src/Dizge/Security/RateLimit.php';
require_once __DIR__ . '/src/Dizge/Security/RateLimiter.php';
require_once __DIR__ . '/src/Dizge/Security/Throttle.php';
require_once __DIR__ . '/src/Dizge/Security/UserProvider.php';
require_once __DIR__ . '/src/Dizge/Security/UserRecord.php';
require_once __DIR__ . '/src/Dizge/Security/PdoUserProvider.php';
require_once __DIR__ . '/src/Dizge/Security/AuditLog.php';
require_once __DIR__ . '/src/Dizge/Security/LoginService.php';
require_once __DIR__ . '/src/Dizge/Validation/ErrorBag.php';
require_once __DIR__ . '/src/Dizge/Validation/ValidationException.php';
require_once __DIR__ . '/src/Dizge/Validation/Validator.php';
require_once __DIR__ . '/src/Dizge/Cms/HtmlSanitizer.php';
require_once __DIR__ . '/src/Dizge/Cms/Slug.php';
require_once __DIR__ . '/src/Dizge/Cms/Workflow.php';
require_once __DIR__ . '/src/Dizge/Cms/Policy.php';
require_once __DIR__ . '/src/Dizge/Cms/ContentRepository.php';
require_once __DIR__ . '/src/Dizge/Cms/MediaStorage.php';
require_once __DIR__ . '/src/Dizge/Cms/Retrieval.php';
require_once __DIR__ . '/src/Dizge/Cms/View.php';
require_once __DIR__ . '/src/Dizge/Application.php';

use Dizge\Application;
use Dizge\Data\Database;
use Dizge\Http\Response;
use Dizge\Security\Auth;
use Dizge\Security\Csrf;
use Dizge\Security\Gate;
use Dizge\Cache\Cache;

Application::boot(__DIR__);

/** @return Application The application singleton for explicit route registration. */
function app(): Application
{
    return Application::instance();
}

/** @param array<string, mixed> $data @return Response A rendered, escaped-by-convention view response. */
function view(string $template, array $data = []): Response
{
    return app()->view($template, $data);
}

/** Escapes text for an HTML text or attribute context. Raw output requires a deliberate callback boundary. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param mixed ...$args Named component props, or one associative array for dynamic callers.
 * @return string A validated DevinimJS custom element.
 */
function dv(string $tag, mixed ...$args): string
{
    if (count($args) === 1 && isset($args[0]) && is_array($args[0])) {
        $args = $args[0];
    }
    return app()->devinim()->render($tag, $args);
}

/** @return string Deduplicated external ES module tags for components rendered on this page. */
function dv_scripts(): string
{
    return app()->devinim()->scripts();
}

/** Configures the base directory or pinned CDN URL for DevinimJS component modules. */
function dv_path(string $path): void
{
    app()->devinim()->setModulePath($path);
}

/** Returns the lazily configured PDO data boundary. Set DIZGE_DB_DSN before first use. */
function db(): Database
{
    return app()->database();
}

/** Returns the explicitly registered default cache module. */
function cache(): Cache
{
    return app()->container()->get(Cache::class);
}

/** Returns explicit session-backed authentication state. */
function auth(): Auth
{
    return app()->auth();
}

/** Returns the current form CSRF token and starts a secure session when needed. */
function csrf_token(): string
{
    return app()->csrf()->token();
}

/** @return string A safe hidden input for same-origin state-changing forms. */
function csrf_field(): string
{
    return app()->csrf()->field();
}

/** Returns the deny-by-default authorization policy registry. */
function gate(): Gate
{
    return app()->gate();
}

/** @param callable(array<string,mixed>): \Dizge\Bridge\Patch|null $handler */
function dv_bridge(string $id, string $selector, string $event, callable $handler): void { app()->bridge()->on($id, $selector, $event, $handler); }
function dv_patch(array $props): \Dizge\Bridge\Patch { return new \Dizge\Bridge\Patch($props); }
function dv_bridge_scripts(): string { return '<script type="application/json" data-dizge-bridge>' . e(json_encode(['endpoint' => '/_dizge/bridge', 'token' => csrf_token(), 'bindings' => app()->bridge()->bindings()], JSON_THROW_ON_ERROR)) . '</script><script type="module" src="/assets/dizge-bridge.js"></script>'; }
