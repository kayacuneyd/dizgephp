<?php

declare(strict_types=1);

use Dizge\Http\Request;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\ContactController;

app()->get('/', [PromoController::class, 'home']);

app()->get('/docs', [PromoController::class, 'docs']);
app()->get('/docs/{slug}', [PromoController::class, 'docDetail']);

app()->get('/tutorials', [PromoController::class, 'tutorials']);
app()->get('/tutorials/{slug}', [PromoController::class, 'tutorialDetail']);

app()->get('/download', [PromoController::class, 'download']);
app()->get('/about', [PromoController::class, 'about']);

app()->get('/contact', [ContactController::class, 'show']);
app()->post('/contact', [ContactController::class, 'submit']);

app()->get('/health', function (Request $request): Dizge\Http\Response {
    return Dizge\Http\Response::json(['status' => 'ok']);
});

app()->get('/framework', [FrameworkController::class, 'web']);

app()->get('/robots.txt', function (Request $request): Dizge\Http\Response {
    return Dizge\Http\Response::raw("User-agent: *\nAllow: /\nSitemap: https://dizgecms.digitaltamam.com/sitemap.xml\n", 'text/plain; charset=UTF-8');
});
