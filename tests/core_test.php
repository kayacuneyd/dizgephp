<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;
use Dizge\Http\Request;
use Dizge\Http\Response;

function expect_true(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$application = new Application(dirname(__DIR__));
$application->get('/users/{id}', fn (Request $request, array $params): Response => Response::json(['id' => $params['id']]));

$matched = $application->dispatch(new Request('GET', '/users/42'));
expect_true($matched->status() === 200, 'Parameterized route must return 200.');
expect_true($matched->body() === '{"id":"42"}', 'Parameterized route must expose decoded parameter.');
expect_true($application->dispatch(new Request('POST', '/users/42'))->status() === 404, 'Method mismatch must not match a route.');
expect_true($application->dispatch(new Request('HEAD', '/users/42'))->status() === 200, 'HEAD must fall back to a matching GET route.');

$counter = $application->devinim()->render('counter', ['start' => 4, 'step' => 2, 'attrs' => ['id' => 'counter']]);
expect_true($counter === '<dv-counter id="counter" data-start="4" data-step="2"></dv-counter>', 'Devinim rendering must map props and attrs safely.');
expect_true(str_contains($application->devinim()->scripts(), 'dv-counter.js'), 'Rendered components must register one external module.');

try {
    $application->devinim()->render('counter', ['children' => '<p>unsafe</p>']);
    throw new RuntimeException('Raw children must be rejected.');
} catch (InvalidArgumentException) {
}

$facadeCounter = dv('counter', start: 9, step: 3);
expect_true(str_contains($facadeCounter, 'data-start="9"'), 'The public dv() facade must accept PHP 8 named arguments.');

echo "DizgePHP core tests passed.\n";
