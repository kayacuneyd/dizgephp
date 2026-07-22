<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;
use Dizge\Cache\Cache;
use Dizge\Cache\MemoryStore;
use Dizge\Http\Controller;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Jobs\FileQueue;
use Dizge\Jobs\JobRegistry;
use Dizge\Jobs\Worker;

final class BenchController extends Controller { /** @param array<string,string> $params */ public function show(Request $request, array $params): Response { return Response::api(['ok' => true]); } }
function bench_metric(string $name, int $iterations, callable $work): array { $started = hrtime(true); for ($i = 0; $i < $iterations; $i++) $work(); $milliseconds = (hrtime(true) - $started) / 1_000_000; return ['name' => $name, 'iterations' => $iterations, 'ms_per_operation' => $milliseconds / $iterations]; }

$application = new Application(dirname(__DIR__));
$application->container()->singleton(BenchController::class, fn (Dizge\Container $container): BenchController => new BenchController());
$application->get('/api/v1/bench', [BenchController::class, 'show']);
$cache = new Cache(new MemoryStore());
$queueDir = sys_get_temp_dir() . '/dizge-bench-' . bin2hex(random_bytes(4)); $registry = new JobRegistry(); $registry->register('bench.job', static function (array $payload): void {}); $queue = new FileQueue($queueDir); $worker = new Worker($queue, $registry);
$metrics = [
    bench_metric('controller_dispatch', 10000, fn (): Response => $application->dispatch(new Request('GET', '/api/v1/bench'))),
    bench_metric('cache_round_trip', 10000, function () use ($cache): void { $cache->put('x', ['n' => 1], 60); $cache->get('x'); }),
    bench_metric('job_enqueue', 1000, function () use ($queue): void { $queue->push('bench.job', ['ok' => true]); }),
];
foreach (glob($queueDir . '/pending/*') ?: [] as $file) $worker->runOnce();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($queueDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); rmdir($queueDir);
if (($argv[1] ?? '') === '--json') { echo json_encode($metrics, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n"; exit; }
foreach ($metrics as $metric) printf("%s: %.4f ms/op (%d iterations)\n", $metric['name'], $metric['ms_per_operation'], $metric['iterations']);
