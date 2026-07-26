<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Cms\ContentRepository;
use Dizge\Data\Database;
use Dizge\Data\Migrator;

function headless_security_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

$database = new Database(new PDO('sqlite::memory:'));
(new Migrator($database))->migrate(Migrator::load(dirname(__DIR__) . '/database/migrations'));
$database->pdo()->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)')->execute(['admin@example.test', 'not-used', 'admin', gmdate('c')]);
$repository = new ContentRepository($database);
$draft = $repository->create(['type' => 'page', 'title' => 'Draft', 'slug' => 'draft', 'body_html' => '<p>Hidden</p>', 'author_id' => 1]);
headless_security_expect($repository->publicBySlug('page', 'draft') === null && $repository->published('page') === [], 'Draft content must not enter any public headless retrieval path.');
$repository->transition($draft, 'review', 1);
headless_security_expect($repository->publicBySlug('page', 'draft') === null, 'Review content must remain non-public.');
$repository->transition($draft, 'approved', 1);
headless_security_expect($repository->publicBySlug('page', 'draft') === null, 'Approved content must remain non-public until published.');
echo "DizgeCMS headless API security tests passed.\n";
