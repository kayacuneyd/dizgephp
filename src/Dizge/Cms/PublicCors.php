<?php

declare(strict_types=1);

namespace Dizge\Cms;

use Dizge\Http\Middleware;
use Dizge\Http\Request;
use Dizge\Http\Response;

/** Exact-origin CORS for anonymous public reads; never enables credentials or wildcard origins. */
final class PublicCors implements Middleware
{
    /** @param list<string> $origins */
    public function __construct(private array $origins) {}
    public static function fromEnvironment(): self
    {
        $raw = (string) (getenv('DIZGECMS_PUBLIC_ORIGINS') ?: '');
        $origins = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $origin): bool => filter_var($origin, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($origin), 'https://')));
        return new self($origins);
    }
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request); $origin = $request->header('origin');
        if ($origin === null || !in_array($origin, $this->origins, true)) return $response;
        return $response->withHeader('Access-Control-Allow-Origin', $origin)->withHeader('Vary', 'Origin');
    }
}
