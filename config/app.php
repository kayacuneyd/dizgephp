<?php

declare(strict_types=1);

return [
    'name' => 'DizgePHP',
    'environment' => getenv('DIZGE_ENV') ?: 'production',
    'api_prefix' => '/api/v1',
    // Global default rate limit applied to every route via Application's global middleware
    // (ADR-0006, docs/auth-threat-model.md). Keyed on client IP alone, one bucket per client for
    // the whole app — not a per-route budget; routes that need a stricter/looser budget keep
    // using their own explicit Dizge\Security\Throttle alongside this default.
    //
    // Off by default: the file-backed RateLimiter adds a real disk-lock-and-write cost to every
    // request (measured ~90x on the controller_dispatch benchmark when always-on — see ADR-0006's
    // "Consequences"). Set `'enabled' => true` to turn on the safety net; it costs nothing when
    // left off, and turning it on is a config change, not a code change.
    'rate_limit' => [
        'enabled' => false,
        'max' => 300,
        'seconds' => 60,
    ],
];
