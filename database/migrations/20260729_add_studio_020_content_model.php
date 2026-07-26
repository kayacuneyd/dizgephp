<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '20260729_add_studio_020_content_model'; }

    public function up(Database $database): void
    {
        $pdo = $database->pdo();
        // SQLite cannot widen the original CHECK constraint in place. Rebuild only the CMS
        // aggregate table and retain every pre-0.2 field before enabling foreign keys again.
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec("CREATE TABLE content_items_020 (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT NOT NULL CHECK(type IN ('page','article','service')), title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, body_html TEXT NOT NULL, status TEXT NOT NULL CHECK(status IN ('draft','review','approved','published','archived')), author_id INTEGER NOT NULL, published_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, meta_description TEXT NOT NULL DEFAULT '', featured_media_id INTEGER NULL REFERENCES media(id) ON DELETE SET NULL, scheduled_at TEXT NULL, blocks_json TEXT NOT NULL DEFAULT '[]', FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE RESTRICT)");
        $pdo->exec("INSERT INTO content_items_020 (id,type,title,slug,body_html,status,author_id,published_at,created_at,updated_at,meta_description,featured_media_id,scheduled_at,blocks_json) SELECT id,type,title,slug,body_html,status,author_id,published_at,created_at,updated_at,meta_description,featured_media_id,scheduled_at,'[]' FROM content_items");
        $pdo->exec('DROP TABLE content_items');
        $pdo->exec('ALTER TABLE content_items_020 RENAME TO content_items');
        $pdo->exec('CREATE INDEX content_items_public_idx ON content_items(type, status, published_at)');
        $pdo->exec('CREATE INDEX content_items_scheduled_idx ON content_items(status, scheduled_at)');
        $pdo->exec("ALTER TABLE content_revisions ADD COLUMN blocks_json TEXT NOT NULL DEFAULT '[]'");
        $pdo->exec('CREATE TABLE content_references (source_content_id INTEGER NOT NULL, target_content_id INTEGER NOT NULL, created_at TEXT NOT NULL, PRIMARY KEY(source_content_id,target_content_id), FOREIGN KEY(source_content_id) REFERENCES content_items(id) ON DELETE CASCADE, FOREIGN KEY(target_content_id) REFERENCES content_items(id) ON DELETE RESTRICT)');
        $pdo->exec('CREATE INDEX content_references_target_idx ON content_references(target_content_id)');
        $pdo->exec('CREATE TABLE contact_submissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, subject TEXT NOT NULL, message TEXT NOT NULL, received_at TEXT NOT NULL, expires_at TEXT NOT NULL, accessed_at TEXT NULL, accessed_by INTEGER NULL, FOREIGN KEY(accessed_by) REFERENCES users(id) ON DELETE SET NULL)');
        $pdo->exec('CREATE INDEX contact_submissions_expiry_idx ON contact_submissions(expires_at)');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    public function down(Database $database): void { /* Deliberately forward-only: narrowing SQLite constraints risks data loss. */ }
};
