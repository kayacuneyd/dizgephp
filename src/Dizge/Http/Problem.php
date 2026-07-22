<?php

declare(strict_types=1);

namespace Dizge\Http;

/** Stable RFC 7807-inspired JSON error response for public APIs. */
final class Problem
{
    /** @param array<string,mixed> $extra */
    public static function response(int $status, string $title, string $detail, array $extra = []): Response
    {
        return Response::raw(json_encode([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            ...$extra,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'application/problem+json; charset=UTF-8', $status);
    }
}
