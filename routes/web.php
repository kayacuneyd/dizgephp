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

app()->get('/sitemap.xml', function (Request $request): Dizge\Http\Response {
    $base = rtrim((string) (getenv('DIZGECMS_SITE_URL') ?: 'https://dizgecms.digitaltamam.com'), '/');
    $paths = ['/', '/docs', '/tutorials', '/download', '/about', '/contact'];
    $urls = array_map(static fn (string $path): string => '<url><loc>'
        . htmlspecialchars($base . ($path === '/' ? '/' : $path), ENT_XML1 | ENT_QUOTES, 'UTF-8')
        . '</loc></url>', $paths);

    return Dizge\Http\Response::raw(
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">"
        . implode('', $urls)
        . '</urlset>',
        'application/xml; charset=UTF-8'
    );
});

app()->get('/llms.txt', function (Request $request): Dizge\Http\Response {
    return Dizge\Http\Response::raw(
        "# DizgeCMS\n\nDizgeCMS, DizgePHP üzerinde çalışan hızlı, güvenli ve plugin-ready bir PHP CMS ürünüdür.\n\n## Kaynaklar\n- https://dizgecms.digitaltamam.com/docs\n- https://dizgecms.digitaltamam.com/tutorials\n- https://dizgecms.digitaltamam.com/download\n",
        'text/plain; charset=UTF-8'
    );
});
