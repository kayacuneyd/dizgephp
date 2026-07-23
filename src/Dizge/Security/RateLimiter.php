<?php

declare(strict_types=1);

namespace Dizge\Security;

/** File-backed fixed-window limiter for shared hosting. Uses flock for concurrent PHP requests. */
final class RateLimiter
{
    public function __construct(private string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Cannot create rate-limit storage.');
    }

    public function hit(string $key, int $max, int $seconds): RateLimit
    {
        if ($max < 1 || $seconds < 1) throw new \InvalidArgumentException('Rate limits require positive max and seconds.');
        $file = $this->directory . '/' . hash('sha256', $key) . '.json';
        $handle = fopen($file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) throw new \RuntimeException('Cannot lock rate-limit state.');
        $now = time();
        $state = json_decode(stream_get_contents($handle) ?: '{}', true) ?: [];
        $reset = (int) ($state['reset'] ?? 0);
        $count = $reset > $now ? (int) ($state['count'] ?? 0) : 0;
        $reset = $reset > $now ? $reset : $now + $seconds;
        $count++;
        rewind($handle); ftruncate($handle, 0); fwrite($handle, json_encode(['count' => $count, 'reset' => $reset], JSON_THROW_ON_ERROR)); fflush($handle); flock($handle, LOCK_UN); fclose($handle);
        return new RateLimit($count <= $max, max(0, $max - $count), max(0, $reset - $now));
    }
}
