<?php

declare(strict_types=1);

return [
    'site_url' => getenv('DIZGECMS_SITE_URL') ?: 'https://dizgecms.digitaltamam.com',
    'plugin_paths' => [__DIR__ . '/../plugins'],
    'theme_paths' => [__DIR__ . '/../themes'],
    'active_theme' => getenv('DIZGECMS_THEME') ?: 'starter',
];
