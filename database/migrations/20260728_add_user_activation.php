<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '20260728_add_user_activation'; }
    public function up(Database $database): void { $database->pdo()->exec('ALTER TABLE users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0,1))'); }
    public function down(Database $database): void {}
};
