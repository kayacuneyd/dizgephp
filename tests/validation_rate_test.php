<?php
declare(strict_types=1);
require dirname(__DIR__) . '/dizge.php';
use Dizge\Security\RateLimiter;
use Dizge\Validation\ValidationException;
use Dizge\Validation\Validator;

function vr_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$validator = new Validator();
$valid = $validator->validate(['email' => 'ada@example.test', 'age' => '18'], ['email' => 'required|email', 'age' => 'required|integer|min:18']);
vr_expect($valid['email'] === 'ada@example.test', 'Valid fields must be returned.');
try { $validator->validate(['email' => 'bad'], ['email' => 'required|email']); throw new RuntimeException('Invalid email must fail.'); } catch (ValidationException $exception) { vr_expect($exception->errors()->has('email'), 'Error bag must name failed field.'); }
$dir = sys_get_temp_dir() . '/dizge-rate-' . bin2hex(random_bytes(4));
$limiter = new RateLimiter($dir);
vr_expect($limiter->hit('login:7', 2, 60)->allowed(), 'First attempt must pass.');
vr_expect($limiter->hit('login:7', 2, 60)->allowed(), 'Second attempt must pass.');
vr_expect(!$limiter->hit('login:7', 2, 60)->allowed(), 'Third attempt must throttle.');
array_map('unlink', glob($dir . '/*') ?: []); rmdir($dir);
echo "DizgePHP validation/rate tests passed.\n";
