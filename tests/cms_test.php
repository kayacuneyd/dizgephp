<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Cms\ContentRepository;
use Dizge\Cms\HtmlSanitizer;
use Dizge\Cms\Policy;
use Dizge\Cms\Workflow;
use Dizge\Data\Database;
use Dizge\Data\Migrator;
use Dizge\Security\Identity;

function cms_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

$directory = sys_get_temp_dir() . '/dizge-cms-' . bin2hex(random_bytes(4));
mkdir($directory, 0700, true);
$database = new Database(new PDO('sqlite:' . $directory . '/app.sqlite'));
$migrator = new Migrator($database);
// Load from an isolated copy of only the shipped CMS/official-module migrations, not the live
// database/migrations/ directory: that directory also holds whatever migrations an application
// has added locally (e.g. via `bin/dizge make:migration`), which this test must stay agnostic to.
$shippedMigrations = ['20260722_create_dizgecms', '20260723_add_cms_publishing_features', '20260724_create_dizge_api_tokens'];
$fixtureDirectory = $directory . '/migrations';
mkdir($fixtureDirectory, 0700, true);
foreach ($shippedMigrations as $id) copy(dirname(__DIR__) . '/database/migrations/' . $id . '.php', $fixtureDirectory . '/' . $id . '.php');
$migrations = Migrator::load($fixtureDirectory);
cms_expect($migrator->migrate($migrations) === $shippedMigrations, 'CMS and official-module migrations must apply to an empty SQLite database in order.');

$database->pdo()->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)')->execute(['admin@example.test', password_hash('not-a-real-password', PASSWORD_DEFAULT), 'admin', gmdate('c')]);
$repository = new ContentRepository($database);
$sanitizer = new HtmlSanitizer();
$safe = $sanitizer->sanitize('<p>Hello <strong>world</strong><script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">unsafe</a><a href="https://example.test" target="_blank">safe</a></p>');
cms_expect(!str_contains($safe, 'script') && !str_contains($safe, 'onclick') && !str_contains($safe, 'javascript:'), 'Sanitizer must remove executable content.');
cms_expect(str_contains($safe, '<strong>world</strong>') && str_contains($safe, 'https://example.test'), 'Sanitizer must preserve allowlisted prose.');

$id = $repository->create(['type' => 'article', 'title' => 'First article', 'slug' => 'first-article', 'body_html' => $safe, 'meta_description' => 'A searchable article.', 'author_id' => 1]);
cms_expect($repository->publicBySlug('article', 'first-article') === null, 'Draft content must not be public.');
cms_expect(!Workflow::allows('draft', 'published'), 'Workflow must reject skipped states.');
cms_expect(Policy::canTransition(new Identity(2, ['role' => 'author']), 'draft', 'review'), 'Authors must be able to submit drafts for review.');
cms_expect(!Policy::canTransition(new Identity(2, ['role' => 'author']), 'review', 'approved'), 'Authors must not self-approve content.');
cms_expect(Policy::canTransition(new Identity(3, ['role' => 'publisher']), 'approved', 'published'), 'Publishers must be able to publish approved content.');
$repository->transition($id, 'review', 1, 'Ready for review.');
$repository->transition($id, 'approved', 1, 'Approved.');
$repository->transition($id, 'published', 1, 'Published.');
cms_expect($repository->publicBySlug('article', 'first-article') !== null, 'Published content must be public.');
$repository->update($id, ['type' => 'article', 'title' => 'First article revised', 'slug' => 'first-article-revised', 'body_html' => '<p>Revised content for search.</p>', 'meta_description' => 'Revised description.'], 1);
cms_expect(count($repository->revisions($id)) === 5, 'Create, transitions and save must create immutable revisions.');
$redirect = $repository->redirectBySlug('article', 'first-article');
cms_expect($redirect !== null && $redirect['slug'] === 'first-article-revised', 'Old published slugs must resolve to their new content.');
cms_expect(count($repository->searchPublished('revised search')) === 1, 'FTS must return only published indexed content.');
$revisionId = (int) $repository->revisions($id)[0]['id'];
$repository->restoreRevision($id, $revisionId, 1);
cms_expect($repository->find($id)['status'] === 'draft', 'Revision restore must return content to a non-public draft.');
$repository->transition($id, 'review', 1, 'Review after restore.');
$repository->transition($id, 'approved', 1, 'Approved after restore.');
$repository->transition($id, 'published', 1, 'Published after restore.');
$repository->transition($id, 'archived', 1, 'Archived.');
cms_expect($repository->publicBySlug('article', 'first-article') === null, 'Archived content must not remain public.');

try {
    $database->pdo()->prepare('INSERT INTO content_items (type, title, slug, body_html, status, author_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute(['page', 'Duplicate', 'first-article-revised', '<p>x</p>', 'draft', 1, gmdate('c'), gmdate('c')]);
    throw new RuntimeException('Slug uniqueness must be enforced by SQLite.');
} catch (PDOException) {
}

$migrator->rollback($migrations);
cms_expect($database->pdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'content_items'")->fetchColumn() === false, 'CMS migration rollback must remove content tables.');

foreach (['/app.sqlite', '/app.sqlite-shm', '/app.sqlite-wal'] as $file) {
    if (is_file($directory . $file)) unlink($directory . $file);
}
array_map('unlink', glob($fixtureDirectory . '/*.php') ?: []);
rmdir($fixtureDirectory);
rmdir($directory);
echo "DizgePHP CMS tests passed.\n";
