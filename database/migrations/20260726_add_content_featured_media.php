<?php
declare(strict_types=1);
use Dizge\Data\Database; use Dizge\Data\Migration;
return new class implements Migration { public function id(): string { return '20260726_add_content_featured_media'; } public function up(Database $database): void { $database->pdo()->exec('ALTER TABLE content_items ADD COLUMN featured_media_id INTEGER NULL REFERENCES media(id) ON DELETE SET NULL'); } public function down(Database $database): void {} };
