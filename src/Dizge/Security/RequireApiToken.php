<?php

declare(strict_types=1);

namespace Dizge\Security;

use Dizge\Http\Middleware;
use Dizge\Http\Problem;
use Dizge\Http\Request;
use Dizge\Http\Response;

final class RequireApiToken implements Middleware
{
    public function __construct(private ApiTokenStore $tokens, private ?string $ability = null) {}
    public function handle(Request $request, callable $next): Response
    {
        $header = $request->header('authorization', '');
        $secret = preg_match('/^Bearer\\s+(.+)$/i', $header, $match) === 1 ? $match[1] : '';
        $token = $this->tokens->authenticate($secret);
        if ($token === null || ($this->ability !== null && !$token->allows($this->ability))) return Problem::response(401, 'Unauthorized', 'A valid bearer token is required.');
        return $next($request);
    }
}
