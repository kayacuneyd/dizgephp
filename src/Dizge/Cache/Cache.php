<?php

declare(strict_types=1);

namespace Dizge\Cache;

final class Cache
{
    public function __construct(private CacheStore $store) {}
    public function get(string $key, mixed $default = null): mixed { return $this->store->get($key, $default); }
    public function put(string $key, mixed $value, int $seconds): void { $this->store->put($key, $value, $seconds); }
    public function forget(string $key): void { $this->store->forget($key); }
    public function clear(): void { $this->store->clear(); }
    public function gc(): int { return $this->store->gc(); }
    public function remember(string $key, int $seconds, callable $factory): mixed { $marker = new \stdClass(); $value = $this->store->get($key, $marker); if ($value !== $marker) return $value; $value = $factory(); $this->store->put($key, $value, $seconds); return $value; }
}
