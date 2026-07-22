<?php
declare(strict_types=1);
require dirname(__DIR__) . '/dizge.php';
use Dizge\Application;
use Dizge\Data\Database;
use Dizge\Security\AuditLog;
use Dizge\Security\LoginService;
use Dizge\Security\PdoUserProvider;
use Dizge\Security\RateLimiter;

function login_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$db = new Database(new PDO('sqlite::memory:'));
$db->pdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL)');
$hash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
$db->table('users')->insert(['email' => 'ada@example.test', 'password_hash' => $hash, 'role' => 'editor']);
$dir = sys_get_temp_dir() . '/dizge-login-' . bin2hex(random_bytes(4)); mkdir($dir, 0700, true);
$app = new Application(dirname(__DIR__));
$service = new LoginService(new PdoUserProvider($db), $app->auth(), new RateLimiter($dir), new AuditLog($dir . '/audit.log'));
login_expect(!$service->attempt('ada@example.test', 'wrong'), 'Invalid password must fail without logging in.');
login_expect(!$app->auth()->check(), 'Failed login must not create identity.');
login_expect($service->attempt('ada@example.test', 'correct horse battery staple'), 'Valid credentials must authenticate.');
login_expect($app->auth()->requireUser()->attribute('role') === 'editor', 'Provider attributes must reach authorization identity.');
$log = file_get_contents($dir . '/audit.log') ?: '';
login_expect(str_contains($log, 'login.failed') && str_contains($log, 'login.succeeded') && !str_contains($log, 'correct horse'), 'Audit log must record outcomes but never passwords.');
array_map('unlink', glob($dir . '/*') ?: []); rmdir($dir);
echo "DizgePHP login tests passed.\n";
