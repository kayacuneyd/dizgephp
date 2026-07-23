<?php
declare(strict_types=1);
require dirname(__DIR__) . '/dizge.php';
use Dizge\Http\Request;
use Dizge\Http\TrustedProxy;
use Dizge\Security\Session;

function tp_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

tp_expect(TrustedProxy::isTrusted('127.0.0.1', ['127.0.0.1/32']), 'Exact IPv4 match must be trusted.');
tp_expect(TrustedProxy::isTrusted('10.0.0.42', ['10.0.0.0/24']), 'IPv4 CIDR range must match a contained address.');
tp_expect(!TrustedProxy::isTrusted('10.0.1.42', ['10.0.0.0/24']), 'IPv4 CIDR range must not match an address outside it.');
tp_expect(TrustedProxy::isTrusted('::1', ['::1/128']), 'IPv6 loopback must match its exact CIDR.');
tp_expect(!TrustedProxy::isTrusted('203.0.113.9', ['10.0.0.0/8']), 'An untrusted public IP must never match.');
tp_expect(!TrustedProxy::isTrusted('not-an-ip', ['10.0.0.0/8']), 'A malformed address must fail closed, not throw.');

tp_expect(TrustedProxy::resolveClientIp('10.0.0.1', '203.0.113.5, 10.0.0.1', ['10.0.0.0/24']) === '203.0.113.5', "A trusted proxy's X-Forwarded-For must resolve to the left-most (original) client IP.");
tp_expect(TrustedProxy::resolveClientIp('203.0.113.9', '203.0.113.5', ['10.0.0.0/24']) === '203.0.113.9', "An untrusted peer's X-Forwarded-For must be ignored so a client cannot spoof its own IP.");
tp_expect(TrustedProxy::resolveClientIp('10.0.0.1', null, ['10.0.0.0/24']) === '10.0.0.1', 'A trusted proxy with no forwarded header must fall back to REMOTE_ADDR.');
tp_expect(TrustedProxy::resolveClientIp('10.0.0.1', 'not-an-ip', ['10.0.0.0/24']) === '10.0.0.1', 'A malformed X-Forwarded-For value must fall back to REMOTE_ADDR rather than trusting garbage.');
tp_expect(TrustedProxy::resolveClientIp('203.0.113.9', '198.51.100.1', []) === '203.0.113.9', 'With no configured trusted proxies, X-Forwarded-For must always be ignored.');

$originalServer = $_SERVER;
putenv('DIZGE_TRUSTED_PROXIES=10.0.0.0/24');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5';
$request = Request::fromGlobals();
tp_expect($request->header('remote-addr') === '203.0.113.5', 'Request::fromGlobals must resolve the real client IP behind a trusted proxy.');
putenv('DIZGE_TRUSTED_PROXIES');
$_SERVER = $originalServer;

putenv('DIZGE_TRUSTED_PROXIES=127.0.0.1/32');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
$session = new Session('dizge_test_secure_' . bin2hex(random_bytes(4)));
$session->put('probe', true);
$params = session_get_cookie_params();
tp_expect($params['secure'] === true, 'Session must mark its cookie Secure when behind a trusted HTTPS-terminating proxy.');
session_write_close();
putenv('DIZGE_TRUSTED_PROXIES');
$_SERVER = $originalServer;

echo "DizgePHP trusted-proxy tests passed.\n";
