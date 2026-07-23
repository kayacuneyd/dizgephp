<?php

declare(strict_types=1);

namespace Dizge\Security;

use Dizge\Http\Middleware;
use Dizge\Http\Problem;
use Dizge\Http\Request;
use Dizge\Http\Response;

final class ApiThrottle implements Middleware
{
    public function __construct(private RateLimiter $limiter, private int $max, private int $seconds) {}
    public function handle(Request $request, callable $next): Response
    {
        $key = 'api:' . ($request->header('remote-addr') ?? 'anonymous') . ':' . $request->path();
        $limit = $this->limiter->hit($key, $this->max, $this->seconds);
        return $limit->allowed() ? $next($request) : Problem::response(429, 'Too Many Requests', 'Rate limit exceeded.')->withHeader('Retry-After', (string) $limit->retryAfter());
    }
}
