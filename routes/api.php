<?php

declare(strict_types=1);

use App\Http\Controllers\FrameworkController;
use Dizge\Http\Request;
use Dizge\Http\Response;

app()->get('/api/v1/framework', [FrameworkController::class, 'api']);

// This deliberate route is also a stable contract test for problem+json responses.
app()->get('/api/v1/problem-example', function (Request $request): Response {
    throw new Dizge\Http\HttpException(422, 'Example request is invalid.', 'Validation failed');
});
