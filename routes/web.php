<?php

declare(strict_types=1);

use Dizge\Http\Request;
use App\Http\Controllers\FrameworkController;

app()->get('/', function (Request $request): Dizge\Http\Response {
    return view('home.php', ['title' => 'DizgePHP']);
});

// A minimal, real (not theoretical) use of the DevinimJS bridge: the client counter is
// optimistic, but the server is authoritative for the business rule ("10 is the free-tier
// cap"). No patch is sent back when the client is already within bounds — see
// docs/bridge-threat-model.md for the full contract.
dv_bridge('welcome-counter', '#welcome-counter', 'change', function (array $detail): ?Dizge\Bridge\Patch {
    $count = is_numeric($detail['count'] ?? null) ? (int) $detail['count'] : 0;
    $cap = 10;
    return $count > $cap ? dv_patch(['start' => $cap]) : null;
});

app()->get('/health', function (Request $request): Dizge\Http\Response {
    return Dizge\Http\Response::json(['status' => 'ok']);
});

app()->get('/framework', [FrameworkController::class, 'web']);

app()->get('/robots.txt', function (Request $request): Dizge\Http\Response {
    return Dizge\Http\Response::raw("User-agent: *\nAllow: /demo\nDisallow: /demo/studio\nSitemap: https://dizgephp.digitaltamam.com/demo/sitemap.xml\n", 'text/plain; charset=UTF-8');
});
