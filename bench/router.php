<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;
use Dizge\Http\Request;
use Dizge\Http\Response;

$application = new Application(dirname(__DIR__));
$application->get('/products/{id}', fn (Request $request, array $params): Response => Response::json(['id' => $params['id']]));

$iterations = 10000;
$started = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $application->dispatch(new Request('GET', '/products/42'));
}
$elapsedMilliseconds = (hrtime(true) - $started) / 1_000_000;

printf("router: %d requests in %.2f ms (%.4f ms/request)\n", $iterations, $elapsedMilliseconds, $elapsedMilliseconds / $iterations);
