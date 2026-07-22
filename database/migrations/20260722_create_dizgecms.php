<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '20260722_create_dizgecms'; }

    public function up(Database $database): void
    {
        $database->pdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL, created_at TEXT NOT NULL)');
        $database->pdo()->exec("CREATE TABLE content_items (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT NOT NULL CHECK(type IN ('page','article')), title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, body_html TEXT NOT NULL, status TEXT NOT NULL CHECK(status IN ('draft','review','approved','published','archived')), author_id INTEGER NOT NULL, published_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE RESTRICT)");
        $database->pdo()->exec('CREATE INDEX content_items_public_idx ON content_items(type, status, published_at)');
        $database->pdo()->exec("CREATE TABLE content_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT, content_id INTEGER NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL, body_html TEXT NOT NULL, status TEXT NOT NULL CHECK(status IN ('draft','review','approved','published','archived')), actor_id INTEGER NOT NULL, created_at TEXT NOT NULL, FOREIGN KEY(content_id) REFERENCES content_items(id) ON DELETE CASCADE, FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE RESTRICT)");
        $database->pdo()->exec("CREATE TABLE content_events (id INTEGER PRIMARY KEY AUTOINCREMENT, content_id INTEGER NOT NULL, actor_id INTEGER NOT NULL, event TEXT NOT NULL, note TEXT NULL, from_status TEXT NULL, to_status TEXT NULL, created_at TEXT NOT NULL, FOREIGN KEY(content_id) REFERENCES content_items(id) ON DELETE CASCADE, FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE RESTRICT)");
    }

    public function down(Database $database): void
    {
        $database->pdo()->exec('DROP TABLE IF EXISTS content_events');
        $database->pdo()->exec('DROP TABLE IF EXISTS content_revisions');
        $database->pdo()->exec('DROP TABLE IF EXISTS content_items');
        $database->pdo()->exec('DROP TABLE IF EXISTS users');
    }
};
