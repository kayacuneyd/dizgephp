<?php

declare(strict_types=1);

namespace Dizge\Data;

/** Tracks and executes ordered, explicit PHP migration objects. */
final class Migrator
{
    public function __construct(private Database $database) {}

    /** @param list<Migration> $migrations @return list<string> Applied migration ids. */
    public function migrate(array $migrations): array
    {
        $this->ensureLedger();
        $applied = array_flip($this->appliedIds());
        $batch = $this->nextBatch();
        $ran = [];
        foreach ($migrations as $migration) {
            if (isset($applied[$migration->id()])) continue;
            $migration->up($this->database);
            $statement = $this->database->pdo()->prepare('INSERT INTO dizge_migrations (id, batch, applied_at) VALUES (?, ?, ?)');
            $statement->execute([$migration->id(), $batch, gmdate('c')]);
            $ran[] = $migration->id();
        }
        return $ran;
    }

    /** @param list<Migration> $migrations @return list<string> Reverted migration ids. */
    public function rollback(array $migrations): array
    {
        $this->ensureLedger();
        $latest = $this->latestBatch();
        if ($latest === null) return [];
        $byId = [];
        foreach ($migrations as $migration) $byId[$migration->id()] = $migration;
        $statement = $this->database->pdo()->prepare('SELECT id FROM dizge_migrations WHERE batch = ? ORDER BY id DESC');
        $statement->execute([$latest]);
        $ids = $statement->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            if (!isset($byId[$id])) throw new \RuntimeException(sprintf('Migration source for "%s" is missing.', $id));
            $byId[$id]->down($this->database);
            $this->database->pdo()->prepare('DELETE FROM dizge_migrations WHERE id = ?')->execute([$id]);
        }
        return $ids;
    }

    /** @return list<Migration> */
    public static function load(string $directory): array
    {
        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $migrations = [];
        foreach ($files as $file) {
            $migration = require $file;
            if (!$migration instanceof Migration) throw new \RuntimeException(sprintf('Migration "%s" must return a Migration instance.', $file));
            $migrations[] = $migration;
        }
        return $migrations;
    }

    private function ensureLedger(): void
    {
        $this->database->pdo()->exec('CREATE TABLE IF NOT EXISTS dizge_migrations (id VARCHAR(191) PRIMARY KEY, batch INTEGER NOT NULL, applied_at VARCHAR(64) NOT NULL)');
    }

    /** @return list<string> */
    private function appliedIds(): array
    {
        return $this->database->pdo()->query('SELECT id FROM dizge_migrations')->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function nextBatch(): int { return ($this->latestBatch() ?? 0) + 1; }

    private function latestBatch(): ?int
    {
        $value = $this->database->pdo()->query('SELECT MAX(batch) FROM dizge_migrations')->fetchColumn();
        return $value === null ? null : (int) $value;
    }
}
