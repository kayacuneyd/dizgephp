<?php

declare(strict_types=1);

namespace Dizge\Data;

use PDO;
use Throwable;

/** PDO composition root with conservative connection defaults and explicit transactions. */
final class Database
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public static function fromEnvironment(?string $basePath = null): self
    {
        $dsn = getenv('DIZGE_DB_DSN');
        if (($dsn === false || $dsn === '') && $basePath !== null) {
            $directory = $basePath . '/storage';
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Cannot create SQLite storage directory.');
            }
            $dsn = 'sqlite:' . $directory . '/app.sqlite';
        }
        if ($dsn === false || $dsn === '') {
            throw new \LogicException('Set DIZGE_DB_DSN before calling db().');
        }
        $user = getenv('DIZGE_DB_USER');
        $password = getenv('DIZGE_DB_PASSWORD');
        return new self(new PDO($dsn, $user === false ? null : $user, $password === false ? null : $password));
    }

    public function pdo(): PDO { return $this->pdo; }
    public function table(string $table): Query { return new Query($this, $table); }

    /** @template T @param callable(self): T $operation @return T */
    public function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
