<?php

declare(strict_types=1);

$root = dirname(__DIR__); $baseline = json_decode((string) file_get_contents($root . '/bench/baseline.json'), true, 32, JSON_THROW_ON_ERROR);
$samples = [];
for ($sample = 0; $sample < 5; $sample++) {
    $output = shell_exec('php ' . escapeshellarg($root . '/bench/fullstack.php') . ' --json'); $current = json_decode((string) $output, true, 32, JSON_THROW_ON_ERROR);
    foreach ($current as $metric) $samples[$metric['name']][] = $metric['ms_per_operation'];
}
$currentByName = [];
foreach ($samples as $name => $values) { sort($values, SORT_NUMERIC); $currentByName[$name] = $values[intdiv(count($values), 2)]; }
foreach ($baseline as $name => $milliseconds) {
    if (!isset($currentByName[$name])) throw new RuntimeException('Benchmark metric missing: ' . $name);
    $limit = (float) $milliseconds * 1.15;
    if ($currentByName[$name] > $limit) throw new RuntimeException(sprintf('%s regressed: %.4f ms/op exceeds %.4f ms/op baseline limit.', $name, $currentByName[$name], $limit));
    printf("%s: %.4f ms/op (limit %.4f)\n", $name, $currentByName[$name], $limit);
}
