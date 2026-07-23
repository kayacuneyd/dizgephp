<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

/**
 * Regression coverage for the global default rate limit (ADR-0006, docs/auth-threat-model.md).
 * Every Application instance here uses its own temp basePath (own config/app.php, own
 * storage/rate-limits directory) so this test can never contend with, or pollute, the real
 * repo's storage/rate-limits directory that other test files' `new Application(dirname(__DIR__))`
 * calls and the process-wide app() singleton also write to.
 *
 * The feature is opt-in (`rate_limit.enabled`, default false — see ADR-0006's revision after the
 * always-on version measured a large controller_dispatch benchmark regression): scenario 0 below
 * covers the default-off posture directly; scenarios 1-4 explicitly enable it to cover the
 * mechanism itself.
 */

use Dizge\Application;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Security\RateLimiter;
use Dizge\Security\Throttle;

function grl_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

/** @return array{0: Application, 1: string} */
function grl_app(int $max, int $seconds, bool $enabled = true): array
{
    $dir = sys_get_temp_dir() . '/dizge-global-rate-' . bin2hex(random_bytes(4));
    mkdir($dir . '/config', 0700, true);
    file_put_contents($dir . '/config/app.php', '<?php return ' . var_export(['rate_limit' => ['enabled' => $enabled, 'max' => $max, 'seconds' => $seconds]], true) . ";\n");
    return [new Application($dir), $dir];
}

function grl_cleanup(string $dir): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($dir);
}

/** @return int Middleware count for the first route matching $method/$pattern (a structural check
 *  that withoutGlobalRateLimit strips exactly one middleware, not more, not fewer). */
function grl_middleware_count(Application $app, string $method, string $pattern): int
{
    foreach ($app->routes() as $route) {
        if ($route['method'] === $method && $route['pattern'] === $pattern) return count($route['middleware']);
    }
    throw new RuntimeException("Route {$method} {$pattern} was not registered.");
}

// 0) Default posture (rate_limit.enabled = false, the shipped default): a route with no
//    Throttle of its own is never blocked, no matter how many requests one client sends. This is
//    the framework's default request cost being exactly what it was before ADR-0006 existed.
[$app, $dir] = grl_app(2, 60, enabled: false);
$app->get('/unprotected-default-off', fn (Request $request): Response => Response::json(['ok' => true]));
$clientDefault = ['remote-addr' => '203.0.113.200'];
for ($i = 1; $i <= 5; $i++) {
    $response = $app->dispatch(new Request('GET', '/unprotected-default-off', [], [], $clientDefault));
    grl_expect($response->status() === 200, "Request {$i} must succeed with the global default left disabled (the shipped default) even past what the configured limit would otherwise allow.");
}
grl_expect(grl_middleware_count($app, 'GET', '/unprotected-default-off') === 1, 'With the global default disabled, a route must carry only VerifyCsrf — no Throttle instance should be constructed at all.');
grl_cleanup($dir);

// 1) Once enabled, the global default protects a route that attaches no Throttle of its own, and
//    stops exactly at the configured limit.
[$app, $dir] = grl_app(3, 60);
$app->get('/unprotected', fn (Request $request): Response => Response::json(['ok' => true]));
$clientA = ['remote-addr' => '203.0.113.10'];
for ($i = 1; $i <= 3; $i++) {
    $response = $app->dispatch(new Request('GET', '/unprotected', [], [], $clientA));
    grl_expect($response->status() === 200, "Request {$i} within the configured global limit (3) must succeed.");
}
$blocked = $app->dispatch(new Request('GET', '/unprotected', [], [], $clientA));
grl_expect($blocked->status() === 429, 'A request past the global default limit must be throttled even though the route attaches no Throttle of its own.');
grl_expect($blocked->header('Retry-After') !== null, 'A throttled response must advertise Retry-After.');

// 2) A different client IP is an entirely separate bucket, unaffected by client A's usage.
$clientB = ['remote-addr' => '198.51.100.20'];
$fresh = $app->dispatch(new Request('GET', '/unprotected', [], [], $clientB));
grl_expect($fresh->status() === 200, "A different client IP must not share client A's already-exhausted global bucket.");
grl_cleanup($dir);

// 3) A route with its own stricter Throttle — the exact construction routes/demo.php's
//    /demo/api/context uses (RateLimiter over storage/rate-limits, key 'context:'.ip) — keeps
//    firing on its own independent schedule, composed with (not replaced by) a generous global
//    default. The same client hitting a *different*, unprotected route right after is judged
//    only by the shared global bucket (one bucket per client for the whole app, not per route)
//    and is unaffected by the context-style route's own exhaustion.
[$app, $dir] = grl_app(300, 60);
$contextThrottle = new Throttle(
    new RateLimiter($app->basePath() . '/storage/rate-limits'),
    2,
    60,
    fn (Request $request): string => 'context:' . ($request->header('remote-addr') ?? 'anonymous'),
);
$app->get('/demo/api/context-like', fn (Request $request): Response => Response::json(['ok' => true]), [$contextThrottle]);
$app->get('/unprotected-two', fn (Request $request): Response => Response::json(['ok' => true]));
$client = ['remote-addr' => '203.0.113.55'];
grl_expect($app->dispatch(new Request('GET', '/demo/api/context-like', [], [], $client))->status() === 200, 'First context-style request must pass.');
grl_expect($app->dispatch(new Request('GET', '/demo/api/context-like', [], [], $client))->status() === 200, "Second context-style request (still within its own limit of 2) must pass.");
$contextBlocked = $app->dispatch(new Request('GET', '/demo/api/context-like', [], [], $client));
grl_expect($contextBlocked->status() === 429, "The route's own stricter Throttle must fire on its own schedule, well before the generous (300/60) global default would.");
$otherRoute = $app->dispatch(new Request('GET', '/unprotected-two', [], [], $client));
grl_expect($otherRoute->status() === 200, 'The same client hitting a different route must not be blocked by the context-style Throttle exhausting its own, differently-keyed bucket.');
grl_cleanup($dir);

// 4) The explicit, documented withoutGlobalRateLimit opt-out (ADR-0006) removes only the global
//    default Throttle for that one route; a sibling default route for the same client still
//    throttles normally, proving this is a narrow, per-route exemption, not a silent bypass.
[$app, $dir] = grl_app(2, 60);
$app->get('/protected-by-default', fn (Request $request): Response => Response::json(['ok' => true]));
$app->get('/opted-out', fn (Request $request): Response => Response::json(['ok' => true]), [], withoutGlobalRateLimit: true);
grl_expect(grl_middleware_count($app, 'GET', '/protected-by-default') === 2, 'A default route must carry both global middlewares (VerifyCsrf + global Throttle).');
grl_expect(grl_middleware_count($app, 'GET', '/opted-out') === 1, 'An opted-out route must keep VerifyCsrf and drop only the global Throttle.');
$client = ['remote-addr' => '192.0.2.77'];
grl_expect($app->dispatch(new Request('GET', '/protected-by-default', [], [], $client))->status() === 200, 'First default-route request must pass.');
grl_expect($app->dispatch(new Request('GET', '/protected-by-default', [], [], $client))->status() === 200, 'Second default-route request (at the configured limit of 2) must pass.');
grl_expect($app->dispatch(new Request('GET', '/protected-by-default', [], [], $client))->status() === 429, 'A default route must throttle once the global limit configured for this test is exceeded.');
for ($i = 1; $i <= 5; $i++) {
    $optedOut = $app->dispatch(new Request('GET', '/opted-out', [], [], $client));
    grl_expect($optedOut->status() === 200, "Opted-out route request {$i} must never be throttled by the (already-exhausted-for-this-client) global default.");
}
grl_cleanup($dir);

echo "DizgePHP global rate limit tests passed.\n";
