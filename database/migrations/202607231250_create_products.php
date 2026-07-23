<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '202607231250_create_products'; }
    public function up(Database $database): void { $database->pdo()->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)'); }
    public function down(Database $database): void { $database->pdo()->exec('DROP TABLE IF EXISTS products'); }
};
