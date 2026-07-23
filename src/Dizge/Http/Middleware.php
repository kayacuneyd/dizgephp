<?php

declare(strict_types=1);

namespace Dizge\Http;

/** A route boundary that may reject or enrich a request before its handler runs. */
interface Middleware
{
    /** @param callable(Request): Response $next */
    public function handle(Request $request, callable $next): Response;
}
