<?php

declare(strict_types=1);

namespace Dizge\Security;

use Dizge\Http\Middleware;
use Dizge\Http\Request;
use Dizge\Http\Response;

/** Rejects state-changing browser requests without a matching session CSRF token. */
final class VerifyCsrf implements Middleware
{
    public function __construct(private Csrf $csrf) {}

    public function handle(Request $request, callable $next): Response
    {
        // API routes use explicit bearer/token authentication and do not share browser cookies.
        if (str_starts_with($request->path(), '/api/')) return $next($request);
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) return $next($request);
        $token = $request->input('_token', $request->header('x-csrf-token'));
        return $this->csrf->verify(is_string($token) ? $token : null)
            ? $next($request)
            : Response::html('<h1>419</h1><p>CSRF token mismatch</p>', 419);
    }
}
