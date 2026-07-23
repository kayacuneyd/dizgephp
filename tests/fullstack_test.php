<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;
use Dizge\Cache\Cache;
use Dizge\Cache\FileStore;
use Dizge\Data\Database;
use Dizge\Data\Migrator;
use Dizge\Http\Controller;
use Dizge\Http\HttpException;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Jobs\FileQueue;
use Dizge\Jobs\JobRegistry;
use Dizge\Jobs\Worker;
use Dizge\Mail\FakeTransport;
use Dizge\Mail\Mailer;
use Dizge\Mail\Message;
use Dizge\Mail\QueuedMailer;
use Dizge\Security\ApiTokenStore;
use Dizge\Security\AuditLog;
use Dizge\Security\RequireApiToken;
use Dizge\Validation\Validator;

function fs_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

final class FullstackService { public function value(): string { return 'shared action'; } }
final class FullstackController extends Controller {
    public function __construct(private FullstackService $service) {}
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response { return Response::api(['data' => $this->service->value()]); }
}

$application = new Application(dirname(__DIR__));
$application->container()->singleton(FullstackService::class, fn (Dizge\Container $container): FullstackService => new FullstackService());
$application->container()->singleton(FullstackController::class, fn (Dizge\Container $container): FullstackController => new FullstackController($container->get(FullstackService::class)));
$application->get('/api/v1/status', [FullstackController::class, 'show']);
$application->get('/api/v1/failure', fn (Request $request): Response => throw new HttpException(422, 'Invalid input.', 'Validation failed'));
$response = $application->dispatch(new Request('GET', '/api/v1/status'));
fs_expect($response->body() === '{"data":"shared action"}', 'Controller actions must resolve only explicit dependencies.');
$problem = $application->dispatch(new Request('GET', '/api/v1/failure'));
fs_expect($problem->status() === 422 && $problem->header('Content-Type') === 'application/problem+json; charset=UTF-8', 'API errors must use stable problem responses.');
$application->get('/api/v1/validation', function (Request $request): Response { (new Validator())->validate(['email' => 'bad'], ['email' => 'required|email']); return Response::api([]); });
fs_expect(str_contains($application->dispatch(new Request('GET', '/api/v1/validation'))->body(), '"errors"'), 'API validation failures must expose a stable field-error object.');
fs_expect($application->dispatch(new Request('GET', '/api/v1/missing'))->header('Content-Type') === 'application/problem+json; charset=UTF-8', 'Missing API routes must return problem JSON.');

$directory = sys_get_temp_dir() . '/dizge-fullstack-' . bin2hex(random_bytes(4)); mkdir($directory, 0700, true);
$cache = new Cache(new FileStore($directory . '/cache', 'test'));
$cache->put('dashboard:1', ['count' => 2], 60);
fs_expect($cache->get('dashboard:1')['count'] === 2, 'File cache must persist JSON-safe values.');
fs_expect($cache->remember('computed', 60, fn (): string => 'first') === 'first' && $cache->remember('computed', 60, fn (): string => 'second') === 'first', 'Cache remember must compute once per key.');

$expiredFile = $directory . '/cache/test-' . hash('sha256', 'stale-entry') . '.json';
file_put_contents($expiredFile, json_encode(['expires_at' => time() - 60, 'value' => 'stale'], JSON_THROW_ON_ERROR));
$purged = $cache->gc();
fs_expect($purged === 1, 'gc() must actively purge entries expired outside of get(), not just lazily on read.');
fs_expect(!is_file($expiredFile), 'gc() must remove the expired cache file from disk.');
fs_expect($cache->get('dashboard:1')['count'] === 2, 'gc() must never remove still-valid cache entries.');

$auditFile = $directory . '/audit.log';
$audit = new AuditLog($auditFile);
$audit->write('example.event', ['id' => 1]);
fs_expect($audit->rotateIfNeeded(maxBytes: 1_000_000) === null, 'A log under the size threshold must not rotate.');
file_put_contents($auditFile, str_repeat('x', 200), FILE_APPEND);
$rotated = $audit->rotateIfNeeded(maxBytes: 100, keep: 1);
fs_expect($rotated !== null && is_file($rotated), 'An oversized log must rotate to a dated file.');
fs_expect(!is_file($auditFile), 'Rotation must clear the active log path so the next write() starts fresh.');
$audit->write('after.rotation', []);
fs_expect(is_file($auditFile) && str_contains((string) file_get_contents($auditFile), 'after.rotation'), 'Writes after rotation must recreate the active log file.');
file_put_contents($directory . '/audit-19990101-000000.log', 'old');
$second = $audit->rotateIfNeeded(maxBytes: 0, keep: 1);
fs_expect($second !== null, 'A second rotation must succeed once the active file has any content.');
$history = glob($directory . '/audit-*.log') ?: [];
fs_expect(count($history) === 1, 'Rotation must prune dated files beyond the retention count.');

$registry = new JobRegistry(); $handled = [];
$registry->register('test.notify', function (array $payload) use (&$handled): void { $handled[] = $payload['id']; });
$queue = new FileQueue($directory . '/jobs');
fs_expect($queue->push('test.notify', ['id' => 'one'], 'notice:one') !== 'duplicate', 'First idempotent job must be queued.');
fs_expect($queue->push('test.notify', ['id' => 'one'], 'notice:one') === 'duplicate', 'Duplicate idempotency key must not queue twice.');
fs_expect((new Worker($queue, $registry))->runOnce() && $handled === ['one'], 'Cron worker must dispatch allowlisted jobs.');
$registry->register('test.fail', static function (array $payload): void { throw new RuntimeException('Expected retry.'); });
$queue->push('test.fail', ['id' => 'retry'], null, 2); (new Worker($queue, $registry))->runOnce();
$retry = glob($directory . '/jobs/pending/*.json') ?: [];
fs_expect(count($retry) === 1 && (int) json_decode((string) file_get_contents($retry[0]), true)['attempts'] === 1, 'Failed jobs must retain retry state with bounded attempts.');

$fake = new FakeTransport(); (new Mailer($fake))->send(new Message('agent@example.test', 'Safe subject', 'Hello.'));
fs_expect(count($fake->sent()) === 1, 'Mail must be testable through a fake transport.');
$registry->register('mail.send', function (array $payload) use ($fake): void { $fake->send(Message::fromArray($payload)); });
(new QueuedMailer($queue))->queue(new Message('agent@example.test', 'Queued subject', 'Queued.'), 'mail:one'); (new Worker($queue, $registry))->runOnce();
fs_expect(count($fake->sent()) === 2, 'Mail can be delivered through the allowlisted cron queue.');

$database = new Database(new PDO('sqlite:' . $directory . '/tokens.sqlite'));
(new Migrator($database))->migrate(Migrator::load(dirname(__DIR__) . '/database/migrations'));
$tokens = new ApiTokenStore($database); $issued = $tokens->issue('agent-42', 'integration', ['content.read']);
$token = $tokens->authenticate($issued['token']);
fs_expect($token !== null && $token->subjectId() === 'agent-42' && $token->allows('content.read') && !$token->allows('content.write'), 'Bearer tokens must be hashed, scoped and authenticable.');
$secured = new Application(dirname(__DIR__));
$secured->get('/api/v1/private', fn (Request $request): Response => Response::api(['ok' => true]), [new RequireApiToken($tokens, 'content.read')]);
$secured->post('/api/v1/private-write', fn (Request $request): Response => Response::api(['ok' => true]), [new RequireApiToken($tokens, 'content.read')]);
fs_expect($secured->dispatch(new Request('GET', '/api/v1/private'))->status() === 401, 'API routes must reject missing bearer tokens.');
fs_expect($secured->dispatch(new Request('GET', '/api/v1/private', [], [], ['authorization' => 'Bearer ' . $issued['token']]))->status() === 200, 'Scoped bearer tokens must authorize matching API actions.');
fs_expect($secured->dispatch(new Request('POST', '/api/v1/private-write', [], [], ['authorization' => 'Bearer ' . $issued['token']]))->status() === 200, 'Bearer API writes must not depend on browser CSRF state.');

$manifest = shell_exec('php ' . escapeshellarg(dirname(__DIR__) . '/bin/dizge') . ' inspect --json');
$decoded = is_string($manifest) ? json_decode($manifest, true) : null;
fs_expect(is_array($decoded) && isset($decoded['routes'], $decoded['migrations'], $decoded['verification']), 'Inspect command must expose machine-readable agent context.');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); rmdir($directory);
echo "DizgePHP full-stack tests passed.\n";
