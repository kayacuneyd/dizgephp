<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Cms\SiteRepository;
use Dizge\Data\Database;
use Dizge\Data\Migration;

function headless_domain_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

$directory = sys_get_temp_dir() . '/dizge-headless-domain-' . bin2hex(random_bytes(4));
mkdir($directory, 0700, true);
$database = new Database(new PDO('sqlite:' . $directory . '/app.sqlite'));
/** @var Migration $base */
$base = require dirname(__DIR__) . '/database/migrations/20260722_create_dizgecms.php';
/** @var Migration $publishing */
$publishing = require dirname(__DIR__) . '/database/migrations/20260723_add_cms_publishing_features.php';
/** @var Migration $headless */
$headless = require dirname(__DIR__) . '/database/migrations/20260725_create_headless_site_navigation.php';
$base->up($database);
$publishing->up($database);
$headless->up($database);
/** @var Migration $hierarchy */
$hierarchy = require dirname(__DIR__) . '/database/migrations/20260727_add_menu_hierarchy.php';
$hierarchy->up($database);

$repository = new SiteRepository($database);
headless_domain_expect($repository->settings()['site_name'] === 'DizgeCMS' && $repository->menu('primary')['items'] === [], 'Fresh headless domain state must have safe defaults.');
$repository->saveSettings(['site_name' => 'Example Site', 'site_description' => 'A test site.', 'home_page_id' => 7, 'theme_accent' => '#123abc', 'theme_content_measure' => '56rem', 'social_links' => ['https://example.test/social']]);
$settings = $repository->settings();
headless_domain_expect($settings['site_name'] === 'Example Site' && $settings['home_page_id'] === 7 && $settings['theme_accent'] === '#123abc' && $settings['theme_content_measure'] === '56rem' && $settings['social_links'] === ['https://example.test/social'], 'Settings must round trip through their allowlisted schema.');
try { $repository->saveSettings(['database_path' => '/private']); throw new RuntimeException('Unknown settings must be rejected.'); } catch (InvalidArgumentException) {}

$database->pdo()->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)')->execute(['admin@example.test', 'not-used', 'admin', gmdate('c')]);
$database->pdo()->prepare('INSERT INTO content_items (type, title, slug, body_html, meta_description, status, author_id, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute(['page', 'About', 'about', '<p>About</p>', '', 'published', 1, gmdate('c'), gmdate('c'), gmdate('c')]);
$contentId = (int) $database->pdo()->lastInsertId();
$repository->replaceMenu('primary', [
    ['kind' => 'url', 'label' => 'Contact', 'url' => '/contact'],
    ['kind' => 'content', 'label' => 'About', 'content_id' => $contentId],
    ['kind' => 'section', 'label' => 'Top', 'url' => '#top'],
    ['kind' => 'url', 'label' => 'Child', 'url' => '/child', 'parent_index' => 0],
]);
$menu = $repository->menu('primary');
headless_domain_expect(array_column($menu['items'], 'position') === [0, 1, 2, 3] && $menu['items'][1]['content_id'] === $contentId && $menu['items'][3]['parent_id'] === $menu['items'][0]['id'], 'Menu items must keep deterministic order, content references and one-level hierarchy.');
try { $repository->replaceMenu('primary', [['kind' => 'url', 'label' => 'Unsafe', 'url' => 'javascript:alert(1)']]); throw new RuntimeException('Unsafe menu URLs must be rejected.'); } catch (InvalidArgumentException) {}
try { $repository->replaceMenu('secondary', []); throw new RuntimeException('Unknown menu locations must be rejected.'); } catch (InvalidArgumentException) {}

$hierarchy->down($database);
$headless->down($database);
headless_domain_expect($database->pdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'menus'")->fetchColumn() === false, 'Headless migration rollback must remove navigation tables.');
$publishing->down($database);
$base->down($database);
foreach (['/app.sqlite', '/app.sqlite-shm', '/app.sqlite-wal'] as $file) if (is_file($directory . $file)) unlink($directory . $file);
rmdir($directory);
echo "DizgeCMS headless domain tests passed.\n";
