<?php

declare(strict_types=1);

// `php -S` has no rewrite rules; without this, its built-in router serves every request
// (including static assets under public/assets/) through this script instead of the file
// itself. Production Apache/Nginx already do this via public/.htaccess's `!-f` condition.
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
    if ($requested !== __DIR__ . '/index.php' && is_file($requested)) return false;
}

require dirname(__DIR__) . '/dizge.php';
require dirname(__DIR__) . '/routes/services.php';
require dirname(__DIR__) . '/routes/web.php';
require dirname(__DIR__) . '/routes/api.php';
require dirname(__DIR__) . '/routes/demo.php';

app()->run();
