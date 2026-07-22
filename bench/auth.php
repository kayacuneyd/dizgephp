<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Application;

$application = new Application(dirname(__DIR__));
$csrf = $application->csrf();
$token = $csrf->token();
$iterations = 10000;
$started = hrtime(true);
for ($i = 0; $i < $iterations; $i++) $csrf->verify($token);
$elapsedMilliseconds = (hrtime(true) - $started) / 1_000_000;
printf("csrf: %d verifications in %.2f ms (%.4f ms/verification)\n", $iterations, $elapsedMilliseconds, $elapsedMilliseconds / $iterations);
