<?php

declare(strict_types=1);

use Dizge\Http\Request;
use App\Http\Controllers\FrameworkController;

app()->get('/', function (Request $request): Dizge\Http\Response {
    return view('home.php', ['title' => 'DizgePHP']);
});

app()->get('/health', function (Request $request): Dizge\Http\Response {
    return Dizge\Http\Response::json(['status' => 'ok']);
});

app()->get('/framework', [FrameworkController::class, 'web']);

app()->get('/robots.txt', function (Request $request): Dizge\Http\Response {
    return Dizge\Http\Response::raw("User-agent: *\nAllow: /demo\nDisallow: /demo/studio\nSitemap: https://dizgephp.digitaltamam.com/demo/sitemap.xml\n", 'text/plain; charset=UTF-8');
});
