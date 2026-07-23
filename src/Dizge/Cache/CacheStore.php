<?php

declare(strict_types=1);

namespace Dizge\Cache;

interface CacheStore
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $seconds): void;
    public function forget(string $key): void;
    public function clear(): void;

    /**
     * Actively purges expired entries and returns how many were removed. Unlike get()'s
     * lazy per-key eviction, this is the only way to reclaim disk for entries that are
     * never read again after expiring — needed on shared hosting where disk quotas are tight
     * and there may be no cron to trigger it other than this explicit command.
     */
    public function gc(): int;
}
