<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '20260727_add_menu_hierarchy'; }
    public function up(Database $database): void
    {
        $database->pdo()->exec('ALTER TABLE menu_items ADD COLUMN parent_id INTEGER NULL REFERENCES menu_items(id) ON DELETE CASCADE');
        $database->pdo()->exec('CREATE INDEX menu_items_parent_position_idx ON menu_items(menu_id, parent_id, position)');
    }
    public function down(Database $database): void { $database->pdo()->exec('DROP INDEX IF EXISTS menu_items_parent_position_idx'); }
};
