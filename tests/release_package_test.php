<?php

declare(strict_types=1);

function package_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

$root = dirname(__DIR__);
$version = 'package-test-' . bin2hex(random_bytes(4));
$files = ["$root/dist/dizgecms-$version.tar.gz", "$root/dist/dizgecms-$version.zip", "$root/dist/dizgecms-$version.tar.gz.sha256", "$root/dist/dizgecms-$version.zip.sha256"];
try {
    exec('bash ' . escapeshellarg($root . '/bin/package-release') . ' ' . escapeshellarg($version) . ' 2>&1', $output, $status);
    package_expect($status === 0, 'Release package must build: ' . implode("\n", $output));
    exec('tar -tzf ' . escapeshellarg($files[0]) . ' 2>&1', $tarNames, $tarStatus);
    exec('unzip -Z1 ' . escapeshellarg($files[1]) . ' 2>&1', $zipNames, $zipStatus);
    package_expect($tarStatus === 0 && $zipStatus === 0, 'Both release archives must be readable without PHP extensions.');
    sort($tarNames); sort($zipNames);
    // Archive readers expose different physical prefixes, so compare the normalized member tail.
    $normalize = static fn (array $names): array => array_values(array_filter(array_map(static fn (string $name): string => preg_replace('~^dizgecms/~', '', rtrim($name, '/')) ?? $name, $names), static fn (string $name): bool => $name !== 'dizgecms'));
    package_expect($normalize($tarNames) === $normalize($zipNames), 'Tar and ZIP must contain equivalent members.');
    foreach (array_merge($tarNames, $zipNames) as $name) package_expect(!preg_match('~/(?:\.agents|\.codex|\.claude|\.cursor|storage|dist)/~', $name), 'Release must exclude runtime and agent directories.');
    foreach ([$files[2], $files[3]] as $checksum) package_expect(preg_match('/^[a-f0-9]{64}  dizgecms-/', trim((string) file_get_contents($checksum))) === 1, 'Release checksum must be SHA-256 formatted.');
} finally { foreach ($files as $file) if (is_file($file)) unlink($file); }

echo "DizgeCMS release package tests passed.\n";
