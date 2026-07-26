<?php
declare(strict_types=1);
use Dizge\Data\Database; use Dizge\Data\Migration;
return new class implements Migration {
    public function id(): string { return '20260726_add_media_metadata'; }
    public function up(Database $database): void { $database->pdo()->exec("ALTER TABLE media ADD COLUMN alt_text TEXT NOT NULL DEFAULT ''"); $database->pdo()->exec("ALTER TABLE media ADD COLUMN caption TEXT NOT NULL DEFAULT ''"); }
    public function down(Database $database): void { /* SQLite cannot safely drop columns on supported shared-host versions. */ }
};
