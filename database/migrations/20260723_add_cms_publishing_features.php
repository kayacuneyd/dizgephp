<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '20260723_add_cms_publishing_features'; }

    public function up(Database $database): void
    {
        $database->pdo()->exec("ALTER TABLE content_items ADD COLUMN meta_description TEXT NOT NULL DEFAULT ''");
        $database->pdo()->exec("ALTER TABLE content_revisions ADD COLUMN type TEXT NULL");
        $database->pdo()->exec('CREATE TABLE content_redirects (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT NOT NULL CHECK(type IN (\'page\',\'article\')), old_slug TEXT NOT NULL UNIQUE, content_id INTEGER NOT NULL, created_at TEXT NOT NULL, FOREIGN KEY(content_id) REFERENCES content_items(id) ON DELETE CASCADE)');
        $database->pdo()->exec('CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT, stored_name TEXT NOT NULL UNIQUE, original_name TEXT NOT NULL, mime TEXT NOT NULL, size INTEGER NOT NULL, uploaded_by INTEGER NOT NULL, created_at TEXT NOT NULL, FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE RESTRICT)');
        $database->pdo()->exec('CREATE VIRTUAL TABLE content_search USING fts5(content_id UNINDEXED, title, body_html)');
        $database->pdo()->exec("INSERT INTO content_search (content_id, title, body_html) SELECT id, title, body_html FROM content_items");
    }

    public function down(Database $database): void
    {
        $database->pdo()->exec('DROP TABLE IF EXISTS content_search');
        $database->pdo()->exec('DROP TABLE IF EXISTS media');
        $database->pdo()->exec('DROP TABLE IF EXISTS content_redirects');
    }
};
