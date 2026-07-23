<?php

declare(strict_types=1);

namespace Dizge\Security;

use Dizge\Http\Middleware;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Http\Problem;

/** Denies unauthenticated routes before the handler runs. */
final class RequireAuth implements Middleware
{
    public function __construct(private Auth $auth, private ?string $redirectTo = null) {}

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) return $next($request);
        if (str_starts_with($request->path(), '/api/')) return Problem::response(401, 'Unauthorized', 'Authentication is required.');
        return $this->redirectTo === null
            ? Response::html('<h1>401</h1><p>Authentication Required</p>', 401)
            : Response::redirect($this->redirectTo);
    }
}
