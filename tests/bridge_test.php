<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;
use Dizge\Bridge\Patch;
use Dizge\Bridge\Registry;
use Dizge\Http\Request;

function bridge_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

// Registry::on() input validation (bindings are wired at boot by trusted application code, but
// the format is still enforced so a typo'd id/selector/event fails loudly, not silently).
$registry = new Registry();
$rejected = 0;
foreach ([
    ['bad id!', '#sel', 'change'],
    ['ok', 'no-hash-prefix', 'change'],
    ['ok', '#sel', 'Not-Lowercase'],
] as [$id, $selector, $event]) {
    try { $registry->on($id, $selector, $event, fn (array $detail): ?Patch => null); bridge_expect(false, "Registry::on() must reject the malformed binding ({$id}, {$selector}, {$event})."); }
    catch (InvalidArgumentException) { $rejected++; }
}
bridge_expect($rejected === 3, 'Registry::on() must reject every malformed id/selector/event combination.');
bridge_expect((new Patch(['a' => 1]))->toArray() === ['props' => ['a' => 1]], 'Patch must expose only its props array.');

// Full HTTP round trip through the real /_dizge/bridge route and its global CSRF middleware,
// isolated from the demo app's own bindings via a fresh Application (same pattern as core_test.php).
$application = new Application(dirname(__DIR__));
$token = $application->csrf()->token();
$captured = [];
$application->bridge()->on('demo-counter', '#demo-counter', 'change', function (array $detail) use (&$captured): ?Patch {
    $captured[] = $detail;
    $count = is_numeric($detail['count'] ?? null) ? (int) $detail['count'] : 0;
    $cap = 10;
    return $count > $cap ? new Patch(['start' => $cap]) : null;
});

$unknown = $application->dispatch(new Request('POST', '/_dizge/bridge', [], ['id' => 'not-registered', 'detail' => []], ['x-csrf-token' => $token]));
bridge_expect($unknown->status() === 404, 'Dispatching an unregistered binding id must 404, not silently no-op.');

$noToken = $application->dispatch(new Request('POST', '/_dizge/bridge', [], ['id' => 'demo-counter', 'detail' => ['count' => 3]]));
bridge_expect($noToken->status() === 419, 'A request with no CSRF token must be rejected by the same global middleware as any other state-changing route.');

$badToken = $application->dispatch(new Request('POST', '/_dizge/bridge', [], ['id' => 'demo-counter', 'detail' => ['count' => 3]], ['x-csrf-token' => 'wrong']));
bridge_expect($badToken->status() === 419, 'A request with an invalid CSRF token must be rejected.');

$malformed = $application->dispatch(new Request('POST', '/_dizge/bridge', [], ['id' => 123, 'detail' => 'not-an-object'], ['x-csrf-token' => $token]));
bridge_expect($malformed->status() === 422, 'A non-string id or non-array detail must be rejected before reaching the handler.');

$overCap = $application->dispatch(new Request('POST', '/_dizge/bridge', [], ['id' => 'demo-counter', 'detail' => ['count' => 15]], ['x-csrf-token' => $token]));
bridge_expect($overCap->status() === 200 && $overCap->body() === '{"patch":{"props":{"start":10}}}', 'A value over the server-enforced cap must come back with a corrective patch.');

$withinCap = $application->dispatch(new Request('POST', '/_dizge/bridge', [], ['id' => 'demo-counter', 'detail' => ['count' => 3]], ['x-csrf-token' => $token]));
bridge_expect($withinCap->body() === '{"patch":null}', 'A value already within bounds must not trigger a needless patch.');

bridge_expect($captured === [['count' => 15], ['count' => 3]], 'The bound handler must receive the raw client-supplied detail payload for its own validation, exactly like any route reading $request->input().');

// Global dv_bridge()/dv_patch()/dv_bridge_scripts() facades (dizge.php), against the process-wide
// Application::instance() singleton rather than the isolated $application above.
dv_bridge('facade-demo', '#facade-demo', 'change', fn (array $detail): ?Patch => null);
$scripts = dv_bridge_scripts();
preg_match('/data-dizge-bridge>(.*?)<\/script>/', $scripts, $matches);
$config = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES, 'UTF-8'), true, 8, JSON_THROW_ON_ERROR);
bridge_expect($config['endpoint'] === '/_dizge/bridge', 'dv_bridge_scripts() must point the client at the bridge endpoint.');
bridge_expect($config['token'] === csrf_token(), 'dv_bridge_scripts() must embed the current session CSRF token.');
bridge_expect(in_array(['id' => 'facade-demo', 'selector' => '#facade-demo', 'event' => 'change'], $config['bindings'], true), 'dv_bridge_scripts() must expose every binding registered through the dv_bridge() facade.');
bridge_expect(str_contains($scripts, '<script type="module" src="/assets/dizge-bridge.js">'), 'dv_bridge_scripts() must load the published bridge client module.');
bridge_expect(dv_patch(['x' => 1])->toArray() === ['props' => ['x' => 1]], 'dv_patch() must construct a Patch from a props array.');

echo "DizgePHP bridge tests passed.\n";
