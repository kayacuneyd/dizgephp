<?php

declare(strict_types=1);

namespace Dizge\Cache;

/** JSON-only cache store. Values never use unserialize and keys never become file paths. */
final class FileStore implements CacheStore
{
    public function __construct(private string $directory, private string $namespace = 'app')
    {
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $namespace)) throw new \InvalidArgumentException('Cache namespace is invalid.');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Cannot create cache directory.');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);
        $raw = @file_get_contents($path);
        if ($raw === false) return $default;
        try { $entry = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); } catch (\JsonException) { @unlink($path); return $default; }
        if (!is_array($entry) || !isset($entry['expires_at']) || (int) $entry['expires_at'] < time()) { @unlink($path); return $default; }
        return $entry['value'] ?? $default;
    }

    public function put(string $key, mixed $value, int $seconds): void
    {
        if ($seconds < 1) throw new \InvalidArgumentException('Cache TTL must be at least one second.');
        $payload = json_encode(['expires_at' => time() + $seconds, 'value' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $path = $this->path($key); $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $payload, LOCK_EX) === false || !rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Cannot write cache entry.'); }
        @chmod($path, 0600);
    }

    public function forget(string $key): void { @unlink($this->path($key)); }
    public function clear(): void { foreach (glob($this->directory . '/' . $this->namespace . '-*.json') ?: [] as $file) @unlink($file); }

    public function gc(): int
    {
        $purged = 0;
        foreach (glob($this->directory . '/' . $this->namespace . '-*.json') ?: [] as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) continue;
            try { $entry = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); } catch (\JsonException) { @unlink($file); $purged++; continue; }
            if (!is_array($entry) || !isset($entry['expires_at']) || (int) $entry['expires_at'] < time()) { @unlink($file); $purged++; }
        }
        return $purged;
    }

    private function path(string $key): string
    {
        if ($key === '' || strlen($key) > 240) throw new \InvalidArgumentException('Cache key is invalid.');
        return $this->directory . '/' . $this->namespace . '-' . hash('sha256', $key) . '.json';
    }
}
