<?php

declare(strict_types=1);

return [
    'name' => 'DizgePHP',
    'environment' => getenv('DIZGE_ENV') ?: 'production',
    'api_prefix' => '/api/v1',
];
