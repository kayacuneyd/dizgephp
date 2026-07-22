<?php
declare(strict_types=1);
namespace Dizge\Security;
/** Append-only JSON lines audit trail. Never pass credentials or request bodies to this API. */
final class AuditLog
{
    public function __construct(private string $file)
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create audit-log storage.');
        }
    }
    /** @param array<string, scalar|null> $context */
    public function write(string $event, array $context = []): void
    {
        $record = json_encode(['at' => gmdate('c'), 'event' => $event, ...$context], JSON_THROW_ON_ERROR) . "\n";
        $handle = fopen($this->file, 'ab');
        if ($handle === false || !flock($handle, LOCK_EX)) throw new \RuntimeException('Cannot write audit log.');
        fwrite($handle, $record); fflush($handle); flock($handle, LOCK_UN); fclose($handle);
    }
}
