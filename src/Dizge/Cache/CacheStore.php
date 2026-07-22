<?php

declare(strict_types=1);

namespace Dizge\Cache;

interface CacheStore
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $seconds): void;
    public function forget(string $key): void;
    public function clear(): void;
}
