<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Devinim\Renderer;

function icc_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$dizge = escapeshellarg($root . '/bin/dizge');

/** @return array<string,mixed> */
function icc_inspect(string $command): array
{
    exec($command . ' 2>&1', $output, $status);
    icc_expect($status === 0, 'inspect --json must exit 0. Command: ' . $command . ' Output: ' . implode("\n", $output));
    $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    icc_expect(is_array($decoded), 'inspect --json must produce a JSON object.');
    return $decoded;
}

// --- Graceful degradation: inspect must never hard-fail just because the sibling DevinimJS
// checkout is not resolvable (env override to a nonexistent path proves this without depending on
// whether ../devinimjs actually exists on this machine).
$degraded = icc_inspect('DEVINIM_MANIFEST=' . escapeshellarg('/nonexistent/' . bin2hex(random_bytes(6)) . '/component-manifest.json') . ' php ' . $dizge . ' inspect --json');
icc_expect(isset($degraded['component_contract']) && is_array($degraded['component_contract']), 'component_contract must be present even when the sibling manifest is unresolvable.');
icc_expect(array_keys($degraded['component_contract']) === Renderer::componentNames(), 'component_contract must cover exactly the components Renderer::COMPONENTS allowlists.');
foreach ($degraded['component_contract'] as $componentName => $entry) {
    icc_expect(is_array($entry['props']) && $entry['props'] !== [], "component_contract.{$componentName}.props must list Renderer's allowed props.");
    icc_expect($entry['tag'] === 'dv-' . $componentName, "component_contract.{$componentName}.tag must be the dv- custom element name.");
    icc_expect($entry['attributes'] === null && $entry['events'] === null, "component_contract.{$componentName} must omit richer fields, not fabricate them, when the manifest is missing.");
}
icc_expect(isset($degraded['routes'], $degraded['migrations'], $degraded['verification']), 'The existing inspect fields must be unaffected by this change.');

// --- Malformed JSON at the override path must degrade the same way, not fatal.
$badManifestPath = sys_get_temp_dir() . '/dizge-bad-manifest-' . bin2hex(random_bytes(4)) . '.json';
file_put_contents($badManifestPath, '{not valid json');
try {
    $badJson = icc_inspect('DEVINIM_MANIFEST=' . escapeshellarg($badManifestPath) . ' php ' . $dizge . ' inspect --json');
    icc_expect($badJson['component_contract']['field']['attributes'] === null, 'A malformed manifest file must degrade rather than fatal.');
} finally {
    unlink($badManifestPath);
}

// --- When a resolvable, well-formed manifest is present, the richer fields are merged in.
$goodManifestPath = sys_get_temp_dir() . '/dizge-good-manifest-' . bin2hex(random_bytes(4)) . '.json';
file_put_contents($goodManifestPath, json_encode(['components' => [
    ['tag' => 'dv-counter', 'attributes' => ['data-start', 'data-step'], 'events' => ['dv:change']],
    ['tag' => 'dv-unknown-component-not-in-renderer', 'attributes' => ['data-x'], 'events' => ['dv:x']],
]], JSON_THROW_ON_ERROR));
try {
    $enriched = icc_inspect('DEVINIM_MANIFEST=' . escapeshellarg($goodManifestPath) . ' php ' . $dizge . ' inspect --json');
    $counter = $enriched['component_contract']['counter'];
    icc_expect($counter['attributes'] === ['data-start', 'data-step'], 'A resolvable manifest must enrich attributes for a component Renderer also exposes.');
    icc_expect($counter['events'] === ['dv:change'], 'A resolvable manifest must enrich events for a component Renderer also exposes.');
    icc_expect(!array_key_exists('unknown-component-not-in-renderer', $enriched['component_contract']), 'A manifest entry Renderer::COMPONENTS does not allowlist must not be surfaced as if dv() accepted it.');
    icc_expect($enriched['component_contract']['tabs']['attributes'] === null, 'Components absent from the manifest keep null richer fields rather than guessing.');
} finally {
    unlink($goodManifestPath);
}

echo "DizgePHP inspect component-contract tests passed.\n";
