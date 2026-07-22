<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Security\AuthorizationException;
use Dizge\Security\Identity;
use Dizge\Security\RequireAuth;

function auth_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$application = new Application(dirname(__DIR__));
$auth = $application->auth();
$csrf = $application->csrf();
$application->post('/transfer', fn (Request $request): Response => Response::json(['ok' => true]), [new RequireAuth($auth)]);

$token = $csrf->token();
auth_expect($application->dispatch(new Request('POST', '/transfer', [], ['_token' => $token]))->status() === 401, 'Unauthenticated routes must stop before CSRF or handler execution.');

$identity = new Identity(7, ['role' => 'editor']);
$auth->login($identity);
auth_expect($auth->check() && $auth->requireUser()->id() === 7, 'Login must persist an identity in the session.');
auth_expect($application->dispatch(new Request('POST', '/transfer', [], ['_token' => 'invalid']))->status() === 419, 'Default CSRF middleware must reject invalid tokens.');
auth_expect($application->dispatch(new Request('POST', '/transfer', [], ['_token' => $csrf->token()]))->status() === 200, 'Authenticated requests with valid CSRF tokens must reach the handler.');

$gate = $application->gate();
$gate->define('article.update', fn (Identity $user, array $article): bool => $user->id() === $article['owner_id']);
auth_expect($gate->allows($identity, 'article.update', ['owner_id' => 7]), 'Registered ownership policy must allow its owner.');
auth_expect(!$gate->allows($identity, 'missing.policy'), 'Missing policies must deny by default.');
try {
    $gate->authorize($identity, 'article.update', ['owner_id' => 8]);
    throw new RuntimeException('Denied authorization must throw.');
} catch (AuthorizationException) {
}

$auth->logout();
auth_expect(!$auth->check(), 'Logout must remove the identity.');

echo "DizgePHP auth tests passed.\n";
