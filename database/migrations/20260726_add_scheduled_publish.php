<?php
declare(strict_types=1);
use Dizge\Data\Database; use Dizge\Data\Migration;
return new class implements Migration { public function id(): string { return '20260726_add_scheduled_publish'; } public function up(Database $database): void { $database->pdo()->exec('ALTER TABLE content_items ADD COLUMN scheduled_at TEXT NULL'); $database->pdo()->exec('CREATE INDEX content_items_scheduled_idx ON content_items(status, scheduled_at)'); } public function down(Database $database): void { $database->pdo()->exec('DROP INDEX IF EXISTS content_items_scheduled_idx'); } };
