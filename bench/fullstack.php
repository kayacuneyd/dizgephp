<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;
use Dizge\Cache\Cache;
use Dizge\Cache\FileStore;
use Dizge\Cache\MemoryStore;
use Dizge\Http\Controller;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Jobs\FileQueue;
use Dizge\Jobs\JobRegistry;
use Dizge\Jobs\Worker;

final class BenchController extends Controller { /** @param array<string,string> $params */ public function show(Request $request, array $params): Response { return Response::api(['ok' => true]); } }
function bench_metric(string $name, int $iterations, callable $work): array { $started = hrtime(true); for ($i = 0; $i < $iterations; $i++) $work(); $milliseconds = (hrtime(true) - $started) / 1_000_000; return ['name' => $name, 'iterations' => $iterations, 'ms_per_operation' => $milliseconds / $iterations]; }
function bench_rmdir(string $directory): void { $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); rmdir($directory); }

$application = new Application(dirname(__DIR__));
$application->container()->singleton(BenchController::class, fn (Dizge\Container $container): BenchController => new BenchController());
$application->get('/api/v1/bench', [BenchController::class, 'show']);
$cache = new Cache(new MemoryStore());
$queueDir = sys_get_temp_dir() . '/dizge-bench-' . bin2hex(random_bytes(4)); $registry = new JobRegistry(); $registry->register('bench.job', static function (array $payload): void {}); $queue = new FileQueue($queueDir); $worker = new Worker($queue, $registry);

// FileStore is the cache backend actually registered in routes/services.php (MemoryStore above
// is a separate, purely in-process adapter); its disk-write cost had no regression coverage.
$fileCacheDir = sys_get_temp_dir() . '/dizge-bench-filecache-' . bin2hex(random_bytes(4));
$fileCache = new Cache(new FileStore($fileCacheDir));

// Router::dispatch() is an O(n) linear scan (Http/Router.php); registering many unrelated
// routes ahead of the target one benchmarks that growth instead of a best-case single-route app.
$scaledRouter = new Application(dirname(__DIR__));
for ($route = 0; $route < 200; $route++) $scaledRouter->get('/bench/route-' . $route . '/{id}', fn (Request $request, array $params): Response => Response::json($params));
$scaledRouter->get('/bench/target/{id}', fn (Request $request, array $params): Response => Response::json($params));

$metrics = [
    bench_metric('controller_dispatch', 10000, fn (): Response => $application->dispatch(new Request('GET', '/api/v1/bench'))),
    bench_metric('cache_round_trip', 10000, function () use ($cache): void { $cache->put('x', ['n' => 1], 60); $cache->get('x'); }),
    // A higher iteration count than the other metrics: disk I/O on a shared host is bursty, and
    // averaging over more operations is what keeps this metric's per-sample variance sane.
    bench_metric('file_cache_round_trip', 20000, function () use ($fileCache): void { $fileCache->put('x', ['n' => 1], 60); $fileCache->get('x'); }),
    bench_metric('router_dispatch_at_scale', 2000, fn (): Response => $scaledRouter->dispatch(new Request('GET', '/bench/target/42'))),
    bench_metric('job_enqueue', 1000, function () use ($queue): void { $queue->push('bench.job', ['ok' => true]); }),
];
foreach (glob($queueDir . '/pending/*') ?: [] as $file) $worker->runOnce();
bench_rmdir($queueDir);
bench_rmdir($fileCacheDir);
if (($argv[1] ?? '') === '--json') { echo json_encode($metrics, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n"; exit; }
foreach ($metrics as $metric) printf("%s: %.4f ms/op (%d iterations)\n", $metric['name'], $metric['ms_per_operation'], $metric['iterations']);
