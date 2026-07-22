<?php

declare(strict_types=1);

namespace Dizge\Cache;

/** Per-request/testing adapter; it deliberately does not persist across PHP requests. */
final class MemoryStore implements CacheStore
{
    /** @var array<string,array{expires_at:int,value:mixed}> */
    private array $items = [];
    public function get(string $key, mixed $default = null): mixed { $item = $this->items[$key] ?? null; return $item !== null && $item['expires_at'] >= time() ? $item['value'] : $default; }
    public function put(string $key, mixed $value, int $seconds): void { if ($seconds < 1) throw new \InvalidArgumentException('Cache TTL must be at least one second.'); $this->items[$key] = ['expires_at' => time() + $seconds, 'value' => $value]; }
    public function forget(string $key): void { unset($this->items[$key]); }
    public function clear(): void { $this->items = []; }
}
