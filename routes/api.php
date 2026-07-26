<?php

declare(strict_types=1);

use App\Http\Controllers\FrameworkController;
use Dizge\Cms\ContentRepository;
use Dizge\Cms\HtmlSanitizer;
use Dizge\Cms\SiteRepository;
use Dizge\Cms\PreviewTokenStore;
use Dizge\Cms\PublicCors;
use Dizge\Cms\Slug;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Security\ApiTokenStore;
use Dizge\Security\RequireApiToken;
use Dizge\Validation\Validator;

/** @param array<string,mixed> $content @return array<string,mixed> */
function cms_api_content(array $content): array
{
    $path = match ($content['type']) { 'page' => '/pages/', 'service' => '/services/', default => '/articles/' };
    $base = rtrim((string) (getenv('DIZGECMS_SITE_URL') ?: 'https://dizgecms.digitaltamam.com'), '/');
    return [
        'id' => (int) $content['id'],
        'type' => (string) $content['type'],
        'title' => (string) $content['title'],
        'slug' => (string) $content['slug'],
        'url' => $path . rawurlencode((string) $content['slug']),
        'canonical_url' => $base . $path . rawurlencode((string) $content['slug']),
        'featured_media' => empty($content['featured_media_id']) ? null : ['id' => (int) $content['featured_media_id'], 'url' => '/media/' . (int) $content['featured_media_id']],
        'body_html' => (string) $content['body_html'],
        'meta_description' => (string) $content['meta_description'],
        'status' => (string) $content['status'],
        'published_at' => $content['published_at'],
        'updated_at' => (string) $content['updated_at'],
    ];
}

/** @param array<string,mixed> $content */
function cms_api_is_published(array $content): bool
{
    return ($content['status'] ?? null) === 'published' && !empty($content['published_at']);
}

/** @param array<string,mixed>|null $media @return array{id:int,url:string,mime:string,size:int}|null */
function cms_api_media(?array $media): ?array
{
    if ($media === null) return null;
    return [
        'id' => (int) $media['id'],
        'url' => '/media/' . (int) $media['id'],
        'mime' => (string) $media['mime'],
        'size' => (int) $media['size'],
    ];
}
function cms_api_public(): array { return [PublicCors::fromEnvironment()]; }

app()->get('/api/v1/framework', [FrameworkController::class, 'api']);

// This deliberate route is also a stable contract test for problem+json responses.
app()->get('/api/v1/problem-example', function (Request $request): Response {
    throw new Dizge\Http\HttpException(422, 'Example request is invalid.', 'Validation failed');
});

app()->get('/api/v1/content/{type}', function (Request $request, array $params): Response {
    if (!in_array($params['type'], ['page', 'article', 'service'], true)) return Response::notFound();
    return Response::api(['data' => array_map('cms_api_content', (new ContentRepository(db()))->published($params['type'], 100))]);
});

app()->get('/api/v1/content/{type}/{slug}', function (Request $request, array $params): Response {
    if (!in_array($params['type'], ['page', 'article', 'service'], true)) return Response::notFound();
    $content = (new ContentRepository(db()))->publicBySlug($params['type'], $params['slug']);
    if ($content === null) return Response::notFound();
    return Response::api(['data' => cms_api_content($content)]);
});

app()->get('/api/v1/site', function (Request $request): Response {
    $settings = (new SiteRepository(db()))->settings();
    $content = new ContentRepository(db());
    $home = $settings['home_page_id'] === null ? null : $content->find($settings['home_page_id']);
    $media = fn (?int $id): ?array => $id === null ? null : cms_api_media($content->media($id));
    return Response::api(['data' => [
        'name' => $settings['site_name'],
        'description' => $settings['site_description'],
        'url' => rtrim((string) (getenv('DIZGECMS_SITE_URL') ?: 'https://dizgecms.digitaltamam.com'), '/'),
        'home_page' => $home !== null && cms_api_is_published($home) ? [
            'id' => (int) $home['id'], 'title' => (string) $home['title'], 'slug' => (string) $home['slug'],
            'url' => '/pages/' . rawurlencode((string) $home['slug']),
        ] : null,
        'logo' => $media($settings['logo_media_id']),
        'favicon' => $media($settings['favicon_media_id']),
        'social_links' => $settings['social_links'],
    ]]);
}, cms_api_public());

app()->get('/api/v1/menus/{location}', function (Request $request, array $params): Response {
    try {
        $menu = (new SiteRepository(db()))->menu($params['location']);
    } catch (InvalidArgumentException) {
        return Response::notFound();
    }
    $content = new ContentRepository(db());
    $items = [];
    foreach ($menu['items'] as $item) {
        if ($item['kind'] !== 'content') {
            $items[] = ['id' => $item['id'], 'label' => $item['label'], 'kind' => $item['kind'], 'url' => $item['url'], 'parent_id' => $item['parent_id'], 'position' => $item['position']];
            continue;
        }
        $target = $item['content_id'] === null ? null : $content->find($item['content_id']);
        if ($target === null || !cms_api_is_published($target)) continue;
        $items[] = [
            'id' => $item['id'], 'label' => $item['label'], 'kind' => 'content',
            'url' => $target['type'] === 'page' ? '/pages/' . rawurlencode((string) $target['slug']) : '/articles/' . rawurlencode((string) $target['slug']),
            'position' => $item['position'],
            'parent_id' => $item['parent_id'],
            'content' => ['id' => (int) $target['id'], 'type' => (string) $target['type'], 'slug' => (string) $target['slug']],
        ];
    }
    return Response::api(['data' => ['location' => $menu['location'], 'items' => $items]]);
}, cms_api_public());

app()->get('/api/v1/media/{id}', function (Request $request, array $params): Response {
    $media = cms_api_media((new ContentRepository(db()))->media((int) $params['id']));
    return $media === null ? Response::notFound() : Response::api(['data' => $media]);
}, cms_api_public());

app()->get('/api/v1/search', function (Request $request): Response {
    $query = trim((string) $request->query('q', ''));
    return Response::api(['data' => (new ContentRepository(db()))->searchPublished($query, 20)]);
}, cms_api_public());

app()->get('/api/v1/preview/{token}', function (Request $request, array $params): Response {
    $secret = (string) (getenv('DIZGECMS_PREVIEW_SECRET') ?: '');
    if ($secret === '') return Response::notFound();
    $id = (new PreviewTokenStore(db(), $secret))->contentId($params['token']);
    $content = $id === null ? null : (new ContentRepository(db()))->find($id);
    return $content === null ? Response::notFound() : Response::api(['data' => cms_api_content($content)]);
});

// Stable resource-oriented aliases for headless frontends. The generic content routes remain
// available for integrations that need to enumerate the supported content types dynamically.
app()->get('/api/v1/pages', function (Request $request): Response {
    return Response::api(['data' => array_map('cms_api_content', (new ContentRepository(db()))->published('page', 100))]);
}, cms_api_public());

app()->get('/api/v1/pages/{slug}', function (Request $request, array $params): Response {
    $content = (new ContentRepository(db()))->publicBySlug('page', $params['slug']);
    return $content === null ? Response::notFound() : Response::api(['data' => cms_api_content($content)]);
}, cms_api_public());

app()->get('/api/v1/articles', function (Request $request): Response {
    return Response::api(['data' => array_map('cms_api_content', (new ContentRepository(db()))->published('article', 100))]);
}, cms_api_public());

app()->get('/api/v1/articles/{slug}', function (Request $request, array $params): Response {
    $content = (new ContentRepository(db()))->publicBySlug('article', $params['slug']);
    return $content === null ? Response::notFound() : Response::api(['data' => cms_api_content($content)]);
}, cms_api_public());

app()->get('/api/v1/services', function (Request $request): Response {
    return Response::api(['data' => array_map('cms_api_content', (new ContentRepository(db()))->published('service', 100))]);
}, cms_api_public());

app()->get('/api/v1/services/{slug}', function (Request $request, array $params): Response {
    $content = (new ContentRepository(db()))->publicBySlug('service', $params['slug']);
    return $content === null ? Response::notFound() : Response::api(['data' => cms_api_content($content)]);
}, cms_api_public());

app()->post('/api/v1/content', function (Request $request): Response {
    $input = [
        'type' => (string) $request->input('type'),
        'title' => trim((string) $request->input('title')),
        'slug' => trim((string) $request->input('slug')),
        'body_html' => (string) $request->input('body_html'),
        'meta_description' => trim((string) $request->input('meta_description')),
    ];
    (new Validator())->validate($input, ['type' => 'required|in:page,article,service', 'title' => 'required|string|max:160', 'slug' => 'nullable|string|max:160', 'body_html' => 'required|string|max:50000', 'meta_description' => 'nullable|string|max:160']);
    $input['slug'] = Slug::make($input['slug'] !== '' ? $input['slug'] : $input['title']);
    if ($input['slug'] === '') throw new InvalidArgumentException('Slug must contain at least one letter or number.');
    $input['body_html'] = (new HtmlSanitizer())->sanitize($input['body_html']);
    if (trim(strip_tags($input['body_html'])) === '') throw new InvalidArgumentException('Content must include readable text.');
    $authorId = (int) db()->pdo()->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    if ($authorId < 1) throw new RuntimeException('An administrator must exist before API content can be created.');
    $id = (new ContentRepository(db()))->create([...$input, 'author_id' => $authorId]);
    return Response::api(['data' => ['id' => $id]], 201);
}, [new RequireApiToken(app()->container()->get(ApiTokenStore::class), 'content.write')]);

app()->post('/api/v1/content/{id}', function (Request $request, array $params): Response {
    $id = (int) $params['id'];
    $repository = new ContentRepository(db());
    $current = $repository->find($id);
    if ($current === null) return Response::notFound();
    $input = [
        'type' => (string) $request->input('type', $current['type']),
        'title' => trim((string) $request->input('title', $current['title'])),
        'slug' => trim((string) $request->input('slug', $current['slug'])),
        'body_html' => (string) $request->input('body_html', $current['body_html']),
        'meta_description' => trim((string) $request->input('meta_description', $current['meta_description'])),
    ];
    (new Validator())->validate($input, ['type' => 'required|in:page,article,service', 'title' => 'required|string|max:160', 'slug' => 'required|string|max:160', 'body_html' => 'required|string|max:50000', 'meta_description' => 'nullable|string|max:160']);
    $input['slug'] = Slug::make($input['slug']);
    if ($input['slug'] === '') throw new InvalidArgumentException('Slug must contain at least one letter or number.');
    if ($repository->slugExists($input['slug'], $id)) throw new InvalidArgumentException('Slug is already in use.');
    $input['body_html'] = (new HtmlSanitizer())->sanitize($input['body_html']);
    if (trim(strip_tags($input['body_html'])) === '') throw new InvalidArgumentException('Content must include readable text.');
    $repository->update($id, $input, (int) $current['author_id']);
    return Response::api(['data' => cms_api_content($repository->find($id) ?? $current)]);
}, [new RequireApiToken(app()->container()->get(ApiTokenStore::class), 'content.write')]);

app()->post('/api/v1/content/{id}/delete', function (Request $request, array $params): Response {
    $deleted = (new ContentRepository(db()))->delete((int) $params['id']);
    return $deleted ? Response::api(['deleted' => true]) : Response::notFound();
}, [new RequireApiToken(app()->container()->get(ApiTokenStore::class), 'content.delete')]);
