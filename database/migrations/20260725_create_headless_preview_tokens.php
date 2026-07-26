<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '20260725_create_headless_preview_tokens'; }
    public function up(Database $database): void
    {
        $database->pdo()->exec('CREATE TABLE headless_preview_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, token_hash TEXT NOT NULL UNIQUE, content_id INTEGER NOT NULL, created_by INTEGER NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT NULL, created_at TEXT NOT NULL, FOREIGN KEY(content_id) REFERENCES content_items(id) ON DELETE CASCADE, FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT)');
        $database->pdo()->exec('CREATE INDEX headless_preview_tokens_lookup_idx ON headless_preview_tokens(token_hash, expires_at)');
    }
    public function down(Database $database): void { $database->pdo()->exec('DROP TABLE IF EXISTS headless_preview_tokens'); }
};
