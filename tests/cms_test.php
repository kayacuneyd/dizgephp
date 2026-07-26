<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Cms\ContentRepository;
use Dizge\Cms\ExtensionRegistry;
use Dizge\Cms\HtmlSanitizer;
use Dizge\Cms\PluginManifest;
use Dizge\Cms\Policy;
use Dizge\Cms\ThemeManifest;
use Dizge\Cms\Workflow;
use Dizge\Cms\WxrImporter;
use Dizge\Data\Database;
use Dizge\Data\Migrator;
use Dizge\Security\Identity;
use Dizge\Security\PdoUserProvider;

function cms_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

$directory = sys_get_temp_dir() . '/dizge-cms-' . bin2hex(random_bytes(4));
mkdir($directory, 0700, true);
$database = new Database(new PDO('sqlite:' . $directory . '/app.sqlite'));
$migrator = new Migrator($database);
// Load from an isolated copy of only the shipped CMS/official-module migrations, not the live
// database/migrations/ directory: that directory also holds whatever migrations an application
// has added locally (e.g. via `bin/dizge make:migration`), which this test must stay agnostic to.
$shippedMigrations = ['20260722_create_dizgecms', '20260723_add_cms_publishing_features', '20260724_create_dizge_api_tokens', '20260725_create_headless_preview_tokens', '20260725_create_headless_site_navigation', '20260726_add_content_featured_media', '20260726_add_media_metadata', '20260726_add_scheduled_publish', '20260727_add_menu_hierarchy', '20260728_add_user_activation', '20260729_add_studio_020_content_model'];
$fixtureDirectory = $directory . '/migrations';
mkdir($fixtureDirectory, 0700, true);
foreach ($shippedMigrations as $id) copy(dirname(__DIR__) . '/database/migrations/' . $id . '.php', $fixtureDirectory . '/' . $id . '.php');
$migrations = Migrator::load($fixtureDirectory);
cms_expect($migrator->migrate($migrations) === $shippedMigrations, 'CMS and official-module migrations must apply to an empty SQLite database in order.');

$database->pdo()->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)')->execute(['admin@example.test', password_hash('not-a-real-password', PASSWORD_DEFAULT), 'admin', gmdate('c')]);
$database->pdo()->prepare('UPDATE users SET is_active = 0 WHERE email = ?')->execute(['admin@example.test']);
$activeProvider = new PdoUserProvider($database, 'users', 'email', 'password_hash', 'id', 'is_active');
cms_expect($activeProvider->findByIdentifier('admin@example.test') === null, 'Inactive CMS users must not resolve for login.');
$database->pdo()->prepare('UPDATE users SET is_active = 1 WHERE email = ?')->execute(['admin@example.test']);
cms_expect($activeProvider->findByIdentifier('admin@example.test') !== null, 'Active CMS users must resolve for login.');
$repository = new ContentRepository($database);
$serviceId = $repository->create(['type' => 'service', 'title' => 'Consulting', 'slug' => 'consulting', 'body_html' => '<p>Service</p>', 'blocks_json' => '[{"type":"hero","data":{"title":"Consulting"}}]', 'author_id' => 1]);
cms_expect($repository->find($serviceId)['type'] === 'service', '0.2 content model must support services and validated page blocks.');
$repository->replaceReferences($serviceId, []);
$sanitizer = new HtmlSanitizer();
$safe = $sanitizer->sanitize('<p>Hello <strong>world</strong><script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">unsafe</a><a href="https://example.test" target="_blank">safe</a></p>');
cms_expect(!str_contains($safe, 'script') && !str_contains($safe, 'onclick') && !str_contains($safe, 'javascript:'), 'Sanitizer must remove executable content.');
cms_expect(str_contains($safe, '<strong>world</strong>') && str_contains($safe, 'https://example.test'), 'Sanitizer must preserve allowlisted prose.');
cms_expect($sanitizer->sanitize('<p>Before</p><hr><p>After</p>') === '<p>Before</p><hr><p>After</p>', 'Editor divider blocks must survive sanitization.');
$mediaHtml = $sanitizer->sanitize('<p><img src="/media/42" alt="Studio image"><img src="https://evil.example/image.png" alt="blocked"></p>');
cms_expect(str_contains($mediaHtml, '<img src="/media/42" alt="Studio image">') && !str_contains($mediaHtml, 'evil.example'), 'Studio media URLs must survive sanitization while external image sources remain blocked.');

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

$unusedMedia = $repository->createMedia(['stored_name' => str_repeat('a', 32) . '.jpg', 'mime' => 'image/jpeg', 'size' => 12, 'original_name' => 'unused.jpg'], 1);
cms_expect($repository->deleteMediaIfUnused($unusedMedia), 'Unreferenced media must be deletable.');
$usedMedia = $repository->createMedia(['stored_name' => str_repeat('b', 32) . '.jpg', 'mime' => 'image/jpeg', 'size' => 12, 'original_name' => 'used.jpg'], 1);
$usedContent = $repository->create(['type' => 'page', 'title' => 'Media reference', 'slug' => 'media-reference', 'body_html' => '<p><img src="/media/' . $usedMedia . '" alt="x"></p>', 'author_id' => 1]);
try {
    $repository->deleteMediaIfUnused($usedMedia);
    throw new RuntimeException('Media embedded in content must not be deleted.');
} catch (InvalidArgumentException) {
}

$pluginDirectory = $directory . '/plugin';
mkdir($pluginDirectory, 0700, true);
file_put_contents($pluginDirectory . '/plugin.php', '<?php');
$plugin = PluginManifest::fromJson(json_encode(['id' => 'example-search', 'name' => 'Example Search', 'version' => '1.0.0', 'entrypoint' => 'plugin.php', 'capabilities' => ['routes', 'seo']], JSON_THROW_ON_ERROR), $pluginDirectory);
cms_expect($plugin->id() === 'example-search' && $plugin->capabilities() === ['routes', 'seo'], 'Plugin manifests must validate and expose only declared capabilities.');
try {
    PluginManifest::fromJson(json_encode(['id' => 'example-search', 'name' => 'Unsafe', 'version' => '1.0.0', 'entrypoint' => '../escape.php', 'capabilities' => ['routes']], JSON_THROW_ON_ERROR), $pluginDirectory);
    throw new RuntimeException('Plugin entrypoints must remain inside the plugin directory.');
} catch (InvalidArgumentException) {
}
$theme = ThemeManifest::fromJson('{"id":"starter","name":"Starter","version":"1.0.0"}');
cms_expect($theme->id() === 'starter', 'Theme manifests must expose stable identity metadata.');
$extensions = new ExtensionRegistry(dirname(__DIR__));
cms_expect(isset($extensions->plugins()['example-health']) && isset($extensions->themes()['starter']), 'Local plugin and theme discovery must find valid manifests.');
cms_expect($extensions->enabledPluginIds() === [] && $extensions->activeThemeId() === 'starter', 'Extensions must be disabled by default and the starter theme must be active by default.');

$wxrPath = $directory . '/wordpress.xml';
file_put_contents($wxrPath, '<?xml version="1.0"?><rss xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wp="http://wordpress.org/export/1.2/"><channel><item><title>Imported page</title><wp:post_type>page</wp:post_type><wp:post_name>imported-page</wp:post_name><wp:status>publish</wp:status><wp:post_date_gmt>2024-01-02 03:04:05</wp:post_date_gmt><wp:postmeta><wp:meta_key>_yoast_wpseo_metadesc</wp:meta_key><wp:meta_value>Imported description.</wp:meta_value></wp:postmeta><content:encoded><![CDATA[<p>Imported content.</p>]]></content:encoded></item><item><title>Ignored attachment</title><wp:post_type>attachment</wp:post_type></item></channel></rss>');
$wxrRecords = [];
$imported = (new WxrImporter())->import($wxrPath, static function (array $record) use (&$wxrRecords): int { $wxrRecords[] = $record; return 1; }, true);
cms_expect($imported['items'] === 1 && $imported['skipped'] === 1 && $imported['dry_run'] === true, 'WXR dry-run must import supported content and skip unsupported post types.');
cms_expect($wxrRecords === [] && $imported['media'] === 0, 'WXR dry-run must not write content or media.');
$imported = (new WxrImporter())->import($wxrPath, static function (array $record) use (&$wxrRecords): int { $wxrRecords[] = $record; return 1; }, false);
cms_expect(count($wxrRecords) === 1 && $wxrRecords[0]['meta_description'] === 'Imported description.' && $wxrRecords[0]['status'] === 'published', 'WXR import must preserve supported metadata and publication state.');

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
if (is_file($pluginDirectory . '/plugin.php')) unlink($pluginDirectory . '/plugin.php');
if (is_dir($pluginDirectory)) rmdir($pluginDirectory);
if (is_file($wxrPath)) unlink($wxrPath);
array_map('unlink', glob($fixtureDirectory . '/*.php') ?: []);
rmdir($fixtureDirectory);
rmdir($directory);
echo "DizgePHP CMS tests passed.\n";
