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

    /**
     * The log is append-only and otherwise never shrinks; on shared hosting with a tight disk
     * quota it must be bounded. When the active file exceeds $maxBytes, it's renamed to a dated
     * file (same idiom as bin/cms-backup) and only the newest $keep dated files are retained.
     * Returns the rotated file path, or null if rotation wasn't needed.
     */
    public function rotateIfNeeded(int $maxBytes = 5_000_000, int $keep = 14): ?string
    {
        clearstatcache(true, $this->file);
        if (!is_file($this->file) || filesize($this->file) < $maxBytes) return null;
        $directory = dirname($this->file);
        $base = basename($this->file, '.log');
        $rotated = $directory . '/' . $base . '-' . gmdate('Ymd-His') . '.log';
        $handle = fopen($this->file, 'ab');
        if ($handle === false || !flock($handle, LOCK_EX)) throw new \RuntimeException('Cannot rotate audit log.');
        rename($this->file, $rotated);
        flock($handle, LOCK_UN);
        fclose($handle);
        $history = glob($directory . '/' . $base . '-*.log') ?: [];
        rsort($history, SORT_STRING);
        foreach (array_slice($history, $keep) as $old) @unlink($old);
        return $rotated;
    }
}
