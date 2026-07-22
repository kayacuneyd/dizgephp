<?php

declare(strict_types=1);

namespace Dizge\Jobs;

/** File queue for cron workers. Atomic rename claims work and flock protects idempotency records. */
final class FileQueue
{
    public function __construct(private string $directory)
    {
        foreach (['pending', 'processing', 'failed', 'done', 'keys'] as $part) {
            $path = $directory . '/' . $part;
            if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new \RuntimeException('Cannot create queue storage.');
        }
    }

    /** @param array<string,mixed> $payload */
    public function push(string $name, array $payload, ?string $idempotencyKey = null, int $maxAttempts = 3): string
    {
        if ($maxAttempts < 1 || $maxAttempts > 20) throw new \InvalidArgumentException('Invalid job retry limit.');
        if ($idempotencyKey !== null && !$this->claimKey($idempotencyKey)) return 'duplicate';
        $id = bin2hex(random_bytes(16));
        $job = ['id' => $id, 'name' => $name, 'payload' => $payload, 'attempts' => 0, 'max_attempts' => $maxAttempts, 'available_at' => time(), 'idempotency_key' => $idempotencyKey, 'created_at' => gmdate('c')];
        $this->write($this->directory . '/pending/' . $id . '.json', $job);
        return $id;
    }

    /** @return array<string,mixed>|null */
    public function claim(): ?array
    {
        foreach (glob($this->directory . '/pending/*.json') ?: [] as $pending) {
            $raw = file_get_contents($pending); if ($raw === false) continue;
            $job = json_decode($raw, true); if (!is_array($job) || (int) ($job['available_at'] ?? 0) > time()) continue;
            $processing = $this->directory . '/processing/' . basename($pending);
            if (@rename($pending, $processing)) { $job['_path'] = $processing; return $job; }
        }
        return null;
    }

    /** @param array<string,mixed> $job */
    public function complete(array $job): void { @rename((string) $job['_path'], $this->directory . '/done/' . basename((string) $job['_path'])); }
    /** @param array<string,mixed> $job */
    public function fail(array $job, \Throwable $exception): void
    {
        $job['attempts'] = (int) $job['attempts'] + 1;
        $job['last_error'] = substr($exception->getMessage(), 0, 500);
        unset($job['_path']);
        $processing = $this->directory . '/processing/' . $job['id'] . '.json';
        if ((int) $job['attempts'] >= (int) $job['max_attempts']) { $this->write($this->directory . '/failed/' . $job['id'] . '.json', $job); @unlink($processing); return; }
        $job['available_at'] = time() + min(300, 2 ** (int) $job['attempts']);
        $this->write($this->directory . '/pending/' . $job['id'] . '.json', $job); @unlink($processing);
    }

    private function claimKey(string $key): bool
    {
        if ($key === '' || strlen($key) > 200) throw new \InvalidArgumentException('Idempotency key is invalid.');
        $path = $this->directory . '/keys/' . hash('sha256', $key) . '.lock'; $handle = @fopen($path, 'x');
        if ($handle === false) return false; fclose($handle); @chmod($path, 0600); return true;
    }
    /** @param array<string,mixed> $data */
    private function write(string $path, array $data): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX) === false || !rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Cannot write queue job.'); }
        @chmod($path, 0600);
    }
}
