<?php

declare(strict_types=1);

namespace Dizge\Security;

use Dizge\Http\Middleware;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Http\Problem;

final class Throttle implements Middleware
{
    /** @param callable(Request): string|null $key */
    public function __construct(private RateLimiter $limiter, private int $max, private int $seconds, private $key = null) {}
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->key ? ($this->key)($request) : $request->method() . ':' . $request->path();
        $limit = $this->limiter->hit($key, $this->max, $this->seconds);
        if ($limit->allowed()) return $next($request);
        $response = str_starts_with($request->path(), '/api/')
            ? Problem::response(429, 'Too Many Requests', 'Rate limit exceeded.')
            : Response::html('<h1>429</h1><p>Too Many Requests</p>', 429);
        return $response->withHeader('Retry-After', (string) $limit->retryAfter());
    }
}
