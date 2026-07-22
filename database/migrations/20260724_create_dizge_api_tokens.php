<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '20260724_create_dizge_api_tokens'; }
    public function up(Database $database): void
    {
        $database->pdo()->exec('CREATE TABLE dizge_api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, subject_id TEXT NOT NULL, name TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE, abilities_json TEXT NOT NULL, expires_at TEXT NULL, last_used_at TEXT NULL, created_at TEXT NOT NULL)');
        $database->pdo()->exec('CREATE INDEX dizge_api_tokens_subject_idx ON dizge_api_tokens(subject_id)');
    }
    public function down(Database $database): void { $database->pdo()->exec('DROP TABLE IF EXISTS dizge_api_tokens'); }
};
