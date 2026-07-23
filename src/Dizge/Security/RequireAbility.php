<?php

declare(strict_types=1);

namespace Dizge\Security;

use Dizge\Http\Middleware;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Http\Problem;

/** Requires an explicitly registered authorization ability. */
final class RequireAbility implements Middleware
{
    public function __construct(private Auth $auth, private Gate $gate, private string $ability) {}

    public function handle(Request $request, callable $next): Response
    {
        $identity = $this->auth->user();
        if ($identity === null || !$this->gate->allows($identity, $this->ability)) {
            if (str_starts_with($request->path(), '/api/')) return Problem::response(403, 'Forbidden', 'This action is not permitted.');
            return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
        }
        return $next($request);
    }
}
