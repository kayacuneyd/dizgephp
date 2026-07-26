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
app()->get('/releases/dizgecms-0.1.0-rc4.tar.gz', function (Request $request): Dizge\Http\Response {
    $path = app()->basePath() . '/dist/dizgecms-0.1.0-rc4.tar.gz';
    $bytes = is_file($path) ? file_get_contents($path) : false;
    return $bytes === false ? Dizge\Http\Response::notFound() : Dizge\Http\Response::raw($bytes, 'application/gzip')->withHeader('Content-Disposition', 'attachment; filename="dizgecms-0.1.0-rc4.tar.gz"')->withHeader('Cache-Control', 'public, max-age=86400');
});
app()->get('/releases/dizgecms-0.1.0-rc4.tar.gz.sha256', function (Request $request): Dizge\Http\Response {
    $path = app()->basePath() . '/dist/dizgecms-0.1.0-rc4.tar.gz.sha256';
    $bytes = is_file($path) ? file_get_contents($path) : false;
    return $bytes === false ? Dizge\Http\Response::notFound() : Dizge\Http\Response::raw($bytes, 'text/plain; charset=UTF-8')->withHeader('Content-Disposition', 'attachment; filename="dizgecms-0.1.0-rc4.tar.gz.sha256"');
});
app()->get('/releases/dizgecms-0.1.0-rc4.zip', function (Request $request): Dizge\Http\Response {
    $path = app()->basePath() . '/dist/dizgecms-0.1.0-rc4.zip';
    $bytes = is_file($path) ? file_get_contents($path) : false;
    return $bytes === false ? Dizge\Http\Response::notFound() : Dizge\Http\Response::raw($bytes, 'application/zip')->withHeader('Content-Disposition', 'attachment; filename="dizgecms-0.1.0-rc4.zip"')->withHeader('Cache-Control', 'public, max-age=86400');
});
app()->get('/releases/dizgecms-0.1.0-rc4.zip.sha256', function (Request $request): Dizge\Http\Response {
    $path = app()->basePath() . '/dist/dizgecms-0.1.0-rc4.zip.sha256';
    $bytes = is_file($path) ? file_get_contents($path) : false;
    return $bytes === false ? Dizge\Http\Response::notFound() : Dizge\Http\Response::raw($bytes, 'text/plain; charset=UTF-8')->withHeader('Content-Disposition', 'attachment; filename="dizgecms-0.1.0-rc4.zip.sha256"');
});
app()->get('/releases/dizgecms-0.2.0.tar.gz', function (Request $request): Dizge\Http\Response {
    $path = app()->basePath() . '/dist/dizgecms-0.2.0.tar.gz';
    $bytes = is_file($path) ? file_get_contents($path) : false;
    return $bytes === false ? Dizge\Http\Response::notFound() : Dizge\Http\Response::raw($bytes, 'application/gzip')->withHeader('Content-Disposition', 'attachment; filename="dizgecms-0.2.0.tar.gz"')->withHeader('Cache-Control', 'public, max-age=86400');
});
app()->get('/releases/dizgecms-0.2.0.tar.gz.sha256', function (Request $request): Dizge\Http\Response {
    $path = app()->basePath() . '/dist/dizgecms-0.2.0.tar.gz.sha256';
    $bytes = is_file($path) ? file_get_contents($path) : false;
    return $bytes === false ? Dizge\Http\Response::notFound() : Dizge\Http\Response::raw($bytes, 'text/plain; charset=UTF-8')->withHeader('Content-Disposition', 'attachment; filename="dizgecms-0.2.0.tar.gz.sha256"');
});
app()->get('/releases/dizgecms-0.2.0.zip', function (Request $request): Dizge\Http\Response {
    $path = app()->basePath() . '/dist/dizgecms-0.2.0.zip';
    $bytes = is_file($path) ? file_get_contents($path) : false;
    return $bytes === false ? Dizge\Http\Response::notFound() : Dizge\Http\Response::raw($bytes, 'application/zip')->withHeader('Content-Disposition', 'attachment; filename="dizgecms-0.2.0.zip"')->withHeader('Cache-Control', 'public, max-age=86400');
});
app()->get('/releases/dizgecms-0.2.0.zip.sha256', function (Request $request): Dizge\Http\Response {
    $path = app()->basePath() . '/dist/dizgecms-0.2.0.zip.sha256';
    $bytes = is_file($path) ? file_get_contents($path) : false;
    return $bytes === false ? Dizge\Http\Response::notFound() : Dizge\Http\Response::raw($bytes, 'text/plain; charset=UTF-8')->withHeader('Content-Disposition', 'attachment; filename="dizgecms-0.2.0.zip.sha256"');
});
app()->get('/features', [PromoController::class, 'features']);
app()->get('/headless', [PromoController::class, 'headless']);
app()->get('/install-guide', [PromoController::class, 'installGuide']);
app()->get('/migrate-wordpress', [PromoController::class, 'migrateWordpress']);
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
    $paths = ['/', '/features', '/headless', '/install-guide', '/download', '/migrate-wordpress', '/docs', '/tutorials', '/about', '/contact'];
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
        "# DizgeCMS\n\nDizgeCMS, Studio ile yönetilebilen, shared hosting uyumlu ve headless REST API sunan açık kaynak PHP CMS ürünüdür.\n\n## Kaynaklar\n- https://dizgecms.digitaltamam.com/features\n- https://dizgecms.digitaltamam.com/headless\n- https://dizgecms.digitaltamam.com/install-guide\n- https://dizgecms.digitaltamam.com/download\n- https://dizgecms.digitaltamam.com/migrate-wordpress\n",
        'text/plain; charset=UTF-8'
    );
});
