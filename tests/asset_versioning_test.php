<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;
use Dizge\Devinim\Renderer;

/**
 * Regression coverage for TASK-003: the long, immutable Cache-Control set on /assets/** in
 * public/.htaccess is only safe for URLs that carry a filemtime-derived `?v=` query string
 * (Renderer::versionedUrl()). Renderer::scripts() uses it for every DevinimJS component module
 * URL it emits. This exercises the real published file on disk rather than a fixture, so it
 * fails loudly if bin/publish-assets and Renderer::COMPONENTS ever drift (the same pairing
 * bin/dizge assets:check guards).
 *
 * Renderer::versionedUrl() is also exercised directly against /assets/dizge-bridge.js (part 5
 * below) as a general-purpose unit, even though dv_bridge_scripts() (dizge.php) does not wire it
 * in yet — see the comment above that function and docs/swarm/handoffs/TASK-003-implementer.md.
 */

function av_expect(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

$modulePath = dirname(__DIR__) . '/public/assets/devinim/modules/dv-counter.js';
av_expect(is_file($modulePath), 'public/assets/devinim/modules/dv-counter.js must exist (run bin/publish-assets) for this test to exercise a real published file.');

function av_scripts_for_counter(): string
{
    $application = new Application(dirname(__DIR__));
    $application->devinim()->render('counter', ['start' => 1]);
    return $application->devinim()->scripts();
}

// 1. A locally published module URL must carry a `?v=<mtime>` query string.
$first = av_scripts_for_counter();
av_expect((bool) preg_match('/dv-counter\.js\?v=(\d+)/', $first, $matches), 'scripts() must append a filemtime-derived ?v= query string for a locally published module: ' . $first);
$firstVersion = $matches[1];
av_expect((int) $firstVersion === filemtime($modulePath), 'The ?v= value must be the published file\'s actual filemtime(), not an arbitrary counter.');

// 2. Stability: repeated captures without touching the file must agree exactly.
$second = av_scripts_for_counter();
av_expect($first === $second, 'The version query string must stay stable across requests when the published file has not changed.');

// 3. Change detection: touching (as a republish would) must change the emitted URL. filemtime()
// only has one-second resolution, so the new mtime is set explicitly rather than relying on
// wall-clock timing to land in a different second.
$originalMtime = filemtime($modulePath);
try {
    av_expect(touch($modulePath, $originalMtime + 5), 'touch() must succeed on the published module file.');
    clearstatcache(true, $modulePath);

    $third = av_scripts_for_counter();
    av_expect($first !== $third, 'The version query string must change once the published file\'s mtime changes.');
    av_expect((bool) preg_match('/dv-counter\.js\?v=(\d+)/', $third, $matchesThird), 'The touched capture must still carry a ?v= query string: ' . $third);
    av_expect((int) $matchesThird[1] === $originalMtime + 5, 'The new ?v= value must reflect the file\'s new mtime.');
} finally {
    // Leave the committed asset's mtime as this test found it.
    touch($modulePath, $originalMtime);
    clearstatcache(true, $modulePath);
}

// 4. An external (CDN) module path must never be versioned: filemtime() has no meaning for a
// URL that is not a local file, and a pinned CDN URL is already immutable by tagged version.
$cdnApplication = new Application(dirname(__DIR__));
$cdnApplication->devinim()->setModulePath('https://cdn.jsdelivr.net/gh/kayacuneyd/devinimjs@v0.1.0/dist/modules/');
$cdnApplication->devinim()->render('counter', ['start' => 1]);
$cdnScripts = $cdnApplication->devinim()->scripts();
av_expect(str_contains($cdnScripts, 'https://cdn.jsdelivr.net/gh/kayacuneyd/devinimjs@v0.1.0/dist/modules/dv-counter.js"'), 'A CDN module path must be emitted unchanged, with no ?v= query string appended: ' . $cdnScripts);

// 5. Direct unit coverage of Renderer::versionedUrl() for the two asset kinds it serves
// (component modules via Renderer::scripts(), and dizge-bridge.js via dv_bridge_scripts()).
av_expect(Renderer::versionedUrl('/assets/dizge-bridge.js') === '/assets/dizge-bridge.js?v=' . filemtime(dirname(__DIR__) . '/public/assets/dizge-bridge.js'), 'versionedUrl() must version /assets/dizge-bridge.js the same way as component modules.');
av_expect(Renderer::versionedUrl('/assets/does-not-exist.js') === '/assets/does-not-exist.js', 'versionedUrl() must degrade to the unversioned URL for a local path with no file on disk, instead of erroring.');
av_expect(Renderer::versionedUrl('https://cdn.jsdelivr.net/gh/kayacuneyd/devinimjs@v0.1.0/dist/core.min.js') === 'https://cdn.jsdelivr.net/gh/kayacuneyd/devinimjs@v0.1.0/dist/core.min.js', 'versionedUrl() must leave an external URL untouched.');

echo "Asset versioning tests passed.\n";
