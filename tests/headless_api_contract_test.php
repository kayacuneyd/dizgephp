<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Cms\SiteRepository;
use Dizge\Data\Database;
use Dizge\Data\Migrator;
use Dizge\Http\Request;

function headless_api_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

$database = new Database(new PDO('sqlite::memory:'));
(new Migrator($database))->migrate(Migrator::load(dirname(__DIR__) . '/database/migrations'));
app()->useDatabase($database);
$database->pdo()->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, ?)')->execute(['admin@example.test', 'not-used', 'admin', gmdate('c')]);
$insert = $database->pdo()->prepare('INSERT INTO content_items (type, title, slug, body_html, meta_description, status, author_id, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$now = gmdate('c');
$insert->execute(['page', 'Home', 'home', '<p>Welcome.</p>', 'Welcome page.', 'published', 1, $now, $now, $now]);
$homeId = (int) $database->pdo()->lastInsertId();
$insert->execute(['article', 'Private draft', 'private-draft', '<p>Private.</p>', '', 'draft', 1, null, $now, $now]);
$draftId = (int) $database->pdo()->lastInsertId();
$database->pdo()->prepare('INSERT INTO media (stored_name, original_name, mime, size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute(['0123456789abcdef0123456789abcdef.jpg', 'logo.jpg', 'image/jpeg', 42, 1, $now]);
$mediaId = (int) $database->pdo()->lastInsertId();
$site = new SiteRepository($database);
$site->saveSettings(['site_name' => 'Headless Example', 'home_page_id' => $homeId, 'logo_media_id' => $mediaId]);
$site->replaceMenu('primary', [
    ['kind' => 'content', 'label' => 'Home', 'content_id' => $homeId],
    ['kind' => 'content', 'label' => 'Draft', 'content_id' => $draftId],
    ['kind' => 'url', 'label' => 'Contact', 'url' => '/contact'],
]);

require dirname(__DIR__) . '/routes/services.php';
require dirname(__DIR__) . '/routes/api.php';

$siteResponse = app()->dispatch(new Request('GET', '/api/v1/site'));
$sitePayload = json_decode($siteResponse->body(), true, 32, JSON_THROW_ON_ERROR);
headless_api_expect($siteResponse->status() === 200 && $sitePayload['data']['name'] === 'Headless Example', 'Site endpoint must expose allowlisted public settings.');
headless_api_expect($sitePayload['data']['home_page']['url'] === '/pages/home' && $sitePayload['data']['logo']['url'] === '/media/' . $mediaId, 'Site endpoint must expose published home and public media URLs.');
headless_api_expect(!str_contains($siteResponse->body(), '0123456789abcdef'), 'Site endpoint must not expose private stored media names.');

$menuResponse = app()->dispatch(new Request('GET', '/api/v1/menus/primary'));
$menuPayload = json_decode($menuResponse->body(), true, 32, JSON_THROW_ON_ERROR);
headless_api_expect($menuResponse->status() === 200 && count($menuPayload['data']['items']) === 2, 'Public menus must omit references to unpublished content.');
headless_api_expect($menuPayload['data']['items'][0]['url'] === '/pages/home' && $menuPayload['data']['items'][1]['url'] === '/contact', 'Menu output must resolve published content and preserve safe URLs.');

$pageResponse = app()->dispatch(new Request('GET', '/api/v1/pages/home'));
$pagePayload = json_decode($pageResponse->body(), true, 32, JSON_THROW_ON_ERROR);
headless_api_expect($pageResponse->status() === 200 && $pagePayload['data']['canonical_url'] === 'https://dizgecms.digitaltamam.com/pages/home', 'Page endpoint must expose stable canonical and relative URLs.');
headless_api_expect(app()->dispatch(new Request('GET', '/api/v1/articles/private-draft'))->status() === 404, 'Draft content must remain unavailable from headless public endpoints.');

$mediaResponse = app()->dispatch(new Request('GET', '/api/v1/media/' . $mediaId));
$mediaPayload = json_decode($mediaResponse->body(), true, 32, JSON_THROW_ON_ERROR);
headless_api_expect($mediaResponse->status() === 200 && $mediaPayload['data']['mime'] === 'image/jpeg' && !isset($mediaPayload['data']['stored_name']), 'Media endpoint must expose only public delivery metadata.');
headless_api_expect(app()->dispatch(new Request('GET', '/api/v1/menus/unknown'))->status() === 404, 'Unsupported menu locations must use the API not-found behavior.');

echo "DizgeCMS headless API contract tests passed.\n";
