<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '20260725_create_headless_site_navigation'; }

    public function up(Database $database): void
    {
        $database->pdo()->exec('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $database->pdo()->exec("CREATE TABLE menus (id INTEGER PRIMARY KEY AUTOINCREMENT, location TEXT NOT NULL UNIQUE CHECK(location IN ('primary','footer')), created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $database->pdo()->exec("CREATE TABLE menu_items (id INTEGER PRIMARY KEY AUTOINCREMENT, menu_id INTEGER NOT NULL, kind TEXT NOT NULL CHECK(kind IN ('content','url','section')), label TEXT NOT NULL, content_id INTEGER NULL, url TEXT NULL, position INTEGER NOT NULL CHECK(position >= 0), created_at TEXT NOT NULL, updated_at TEXT NOT NULL, FOREIGN KEY(menu_id) REFERENCES menus(id) ON DELETE CASCADE, FOREIGN KEY(content_id) REFERENCES content_items(id) ON DELETE SET NULL, UNIQUE(menu_id, position))");
        $database->pdo()->exec('CREATE INDEX menu_items_menu_position_idx ON menu_items(menu_id, position)');
    }

    public function down(Database $database): void
    {
        $database->pdo()->exec('DROP TABLE IF EXISTS menu_items');
        $database->pdo()->exec('DROP TABLE IF EXISTS menus');
        $database->pdo()->exec('DROP TABLE IF EXISTS site_settings');
    }
};
