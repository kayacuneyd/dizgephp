<?php

declare(strict_types=1);

use Dizge\Cms\ContentRepository;
use Dizge\Cms\HtmlSanitizer;
use Dizge\Cms\FtsRetrieval;
use Dizge\Cms\MediaStorage;
use Dizge\Cms\Policy;
use Dizge\Cms\Slug;
use Dizge\Cms\View;
use Dizge\Cms\Workflow;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Security\AuditLog;
use Dizge\Security\LoginService;
use Dizge\Security\PdoUserProvider;
use Dizge\Security\RateLimiter;
use Dizge\Security\RequireAbility;
use Dizge\Security\RequireAuth;
use Dizge\Validation\ValidationException;
use Dizge\Validation\Validator;

app()->gate()->define('cms.manage', fn (Dizge\Security\Identity $identity): bool => Policy::canAccess($identity));

/** @return ContentRepository */
function cms_repo(): ContentRepository { return new ContentRepository(db()); }
function cms_sanitizer(): HtmlSanitizer { return new HtmlSanitizer(); }
function cms_audit(): AuditLog { return new AuditLog(app()->basePath() . '/storage/logs/cms-audit.log'); }
function cms_login(): LoginService { return new LoginService(new PdoUserProvider(db()), auth(), new RateLimiter(app()->basePath() . '/storage/rate-limits'), cms_audit()); }
function cms_media(): MediaStorage { return new MediaStorage(app()->basePath() . '/storage/media'); }
/** @return list<Dizge\Http\Middleware> */
function cms_protected(): array { return [new RequireAuth(auth(), '/demo/studio/login'), new RequireAbility(auth(), gate(), 'cms.manage')]; }

/**
 * Public read views (home cards, sitemap) are cached with a short TTL since they run an
 * unconditional SQL query on every request; workflow transitions invalidate immediately so a
 * publish/archive/restore never waits out the TTL.
 */
const CMS_PUBLIC_CACHE_KEYS = ['demo:home:pages', 'demo:home:articles', 'demo:sitemap:pages', 'demo:sitemap:articles'];
function cms_invalidate_public_cache(): void { foreach (CMS_PUBLIC_CACHE_KEYS as $key) cache()->forget($key); }

/** @param array<string, list<string>> $errors @param array<string,mixed> $old */
function cms_editor_form(array $errors = [], array $old = [], ?array $content = null): string
{
    $isNew = $content === null;
    $value = fn (string $key, string $fallback = ''): string => (string) ($old[$key] ?? $content[$key] ?? $fallback);
    $type = $value('type', 'article');
    $body = $value('body_html');
    $action = $isNew ? '/demo/studio/content' : '/demo/studio/content/' . (int) $content['id'];
    $heading = $isNew ? 'Yeni içerik' : 'İçeriği düzenle';
    $buttons = '<button class="ck-button" type="submit">' . ($isNew ? 'Taslak oluştur' : 'Değişiklikleri kaydet') . '</button>';
    if (!$isNew) $buttons .= '<a class="ck-button ck-button--secondary" href="/demo/studio/content/' . (int) $content['id'] . '/preview">Önizleme</a>';
    $media = $isNew ? '' : '<section class="dc-media-panel"><h2>Görsel ekle</h2><p>JPEG, PNG, WebP veya GIF; en fazla 5 MB. Yükleme sonrası verilen <code>/demo/media/ID</code> URL’sini editördeki Görsel aracına yapıştır.</p><form method="post" action="/demo/studio/content/' . (int) $content['id'] . '/media" enctype="multipart/form-data"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><input required type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><button class="ck-button ck-button--secondary" type="submit">Görseli yükle</button></form></section>';
    return '<header class="dc-toolbar"><div><p class="dc-kicker">STUDIO / ' . ($isNew ? 'CREATE' : 'EDIT') . '</p><h1>' . $heading . '</h1></div><a href="/demo/studio">İçeriklere dön</a></header>'
        . View::errors($errors) . '<form class="dc-form ck-stack" method="post" action="' . $action . '"><input type="hidden" name="_token" value="' . e(csrf_token()) . '">'
        . '<label>İçerik türü<select name="type"><option value="article"' . ($type === 'article' ? ' selected' : '') . '>Makale</option><option value="page"' . ($type === 'page' ? ' selected' : '') . '>Sayfa</option></select></label>'
        . '<label>Başlık<input required maxlength="160" name="title" value="' . e($value('title')) . '"></label>'
        . '<label>Slug <small>Boş bırakırsan başlıktan üretilir. Makale: <code>/demo/articles/slug</code>; Sayfa: <code>/demo/pages/slug</code>. İçerik yalnızca <strong>published</strong> olduğunda public olur.</small><input maxlength="160" name="slug" value="' . e($value('slug')) . '"></label>'
        . '<label>Meta açıklama <small>Public arama sonucu ve sosyal paylaşım özeti için, en fazla 160 karakter.</small><textarea maxlength="160" name="meta_description" rows="2">' . e($value('meta_description')) . '</textarea></label>'
        . '<label>İçerik</label><dv-rich-editor data-name="body_html" data-value="' . e($body) . '"></dv-rich-editor><textarea class="dc-editor-source" name="body_html" required aria-label="İçerik HTML kaynağı">' . e($body) . '</textarea>'
        . '<p class="dc-help">Yalnızca güvenli başlık, paragraf, liste, link, görsel, alıntı ve kod biçimleri saklanır.</p><div class="ck-cluster">' . $buttons . '</div></form>' . $media . '<script type="module" src="/assets/demo/dv-rich-editor.js"></script>';
}

/** @return array{type:string,title:string,slug:string,body_html:string} */
function cms_content_input(Request $request, ?int $exceptId = null): array
{
    $input = ['type' => $request->input('type'), 'title' => trim((string) $request->input('title')), 'slug' => trim((string) $request->input('slug')), 'body_html' => (string) $request->input('body_html')];
    $input['meta_description'] = trim((string) $request->input('meta_description'));
    (new Validator())->validate($input, ['type' => 'required|in:page,article', 'title' => 'required|string|max:160', 'slug' => 'nullable|string|max:160', 'body_html' => 'required|string|max:50000', 'meta_description' => 'nullable|string|max:160']);
    $input['slug'] = Slug::make($input['slug'] !== '' ? $input['slug'] : $input['title']);
    if ($input['slug'] === '') throw new InvalidArgumentException('Slug must contain at least one letter or number.');
    $input['body_html'] = cms_sanitizer()->sanitize($input['body_html']);
    if (trim(strip_tags($input['body_html'])) === '') throw new InvalidArgumentException('Content must include readable text.');
    $base = $input['slug'];
    $suffix = 2;
    while (cms_repo()->slugExists($input['slug'], $exceptId)) $input['slug'] = $base . '-' . $suffix++;
    return $input;
}

/** @param array<string,mixed> $content */
function cms_public_article(array $content): string
{
    $date = $content['published_at'] === null ? '' : gmdate('d.m.Y', strtotime((string) $content['published_at']));
    return '<article class="dc-prose"><p class="dc-kicker">' . e(strtoupper((string) $content['type'])) . ($date === '' ? '' : ' · ' . e($date)) . '</p><h1>' . e((string) $content['title']) . '</h1><div class="dc-rich-text">' . cms_sanitizer()->sanitize((string) $content['body_html']) . '</div></article>';
}

app()->get('/demo', function (Request $request): Response {
    $repo = cms_repo();
    $pages = cache()->remember('demo:home:pages', 60, fn (): array => $repo->published('page', 6));
    $articles = cache()->remember('demo:home:articles', 60, fn (): array => $repo->published('article', 6));
    $pageLinks = $pages === [] ? '<p class="dc-empty">Henüz yayınlanmış sayfa yok.</p>' : '<div class="dc-card-grid">' . implode('', array_map(fn (array $page): string => '<a class="dc-card" href="/demo/pages/' . e((string) $page['slug']) . '"><span>Sayfa</span><strong>' . e((string) $page['title']) . '</strong></a>', $pages)) . '</div>';
    $articleLinks = $articles === [] ? '<p class="dc-empty">İlk makale Studio’dan yayınlandığında burada görünecek.</p>' : '<div class="dc-card-grid">' . implode('', array_map(fn (array $article): string => '<a class="dc-card" href="/demo/articles/' . e((string) $article['slug']) . '"><span>Makale</span><strong>' . e((string) $article['title']) . '</strong><small>' . e(gmdate('d.m.Y', strtotime((string) $article['published_at']))) . '</small></a>', $articles)) . '</div>';
    $main = '<section class="dc-hero"><div class="ck-container ck-stack"><p class="dc-kicker">DIZGEPHP REFERENCE APP</p><h1>İçeriğin karar süreci görünür olsun.</h1><p>DizgeCMS Studio; taslak, inceleme, onay ve yayın akışını sade bir SQLite çekirdeği üzerinde kurar.</p><div class="ck-cluster"><a class="ck-button" href="/demo/articles">Makaleleri incele</a><a class="ck-button ck-button--secondary" href="/demo/studio">Studio’ya git</a></div></div></section><section class="dc-section"><div class="ck-container ck-stack"><header><p class="dc-kicker">SAYFALAR</p><h2>Kalıcı anlatılar</h2></header>' . $pageLinks . '</div></section><section class="dc-section dc-section--muted"><div class="ck-container ck-stack"><header><p class="dc-kicker">MAKALELER</p><h2>Yayın akışından çıkanlar</h2></header>' . $articleLinks . '</div></section>';
    return Response::html(View::public('DizgeCMS Studio', 'Workflow-first CMS referans uygulaması.', $main));
});

app()->get('/demo/pages/{slug}', function (Request $request, array $params): Response {
    $content = cms_repo()->publicBySlug('page', $params['slug']);
    if ($content === null) {
        $redirect = cms_repo()->redirectBySlug('page', $params['slug']);
        return $redirect === null ? Response::notFound() : Response::redirect('/demo/pages/' . rawurlencode((string) $redirect['slug']), 301);
    }
    return Response::html(View::public((string) $content['title'], (string) ($content['meta_description'] ?: 'DizgeCMS sayfası.'), '<section class="dc-section"><div class="ck-container">' . cms_public_article($content) . '</div></section>', '/demo/pages/' . rawurlencode($params['slug'])));
});

app()->get('/demo/articles', function (Request $request): Response {
    $articles = cms_repo()->published('article', 50);
    $items = $articles === [] ? '<p class="dc-empty">Henüz yayınlanmış makale yok.</p>' : '<div class="dc-list">' . implode('', array_map(fn (array $article): string => '<article><p class="dc-kicker">' . e(gmdate('d.m.Y', strtotime((string) $article['published_at']))) . '</p><h2><a href="/demo/articles/' . e((string) $article['slug']) . '">' . e((string) $article['title']) . '</a></h2></article>', $articles)) . '</div>';
    return Response::html(View::public('Makaleler', 'DizgeCMS yayınlanmış makaleleri.', '<section class="dc-section"><div class="ck-container ck-stack"><header><p class="dc-kicker">YAYIN</p><h1>Makaleler</h1></header>' . $items . '</div></section>', '/demo/articles'));
});

app()->get('/demo/articles/{slug}', function (Request $request, array $params): Response {
    $content = cms_repo()->publicBySlug('article', $params['slug']);
    if ($content === null) {
        $redirect = cms_repo()->redirectBySlug('article', $params['slug']);
        return $redirect === null ? Response::notFound() : Response::redirect('/demo/articles/' . rawurlencode((string) $redirect['slug']), 301);
    }
    return Response::html(View::public((string) $content['title'], (string) ($content['meta_description'] ?: 'DizgeCMS makalesi.'), '<section class="dc-section"><div class="ck-container">' . cms_public_article($content) . '</div></section>', '/demo/articles/' . rawurlencode($params['slug'])));
});

app()->get('/demo/search', function (Request $request): Response {
    $query = trim((string) $request->query('q', ''));
    $results = (new FtsRetrieval(cms_repo()))->search($query, 20);
    $items = $query === '' ? '<p class="dc-empty">Aramak istediğin kelimeyi yaz.</p>' : ($results === [] ? '<p class="dc-empty">Yayınlanmış içeriklerde sonuç bulunamadı.</p>' : '<div class="dc-list">' . implode('', array_map(fn (array $result): string => '<article><p class="dc-kicker">' . e($result['type']) . '</p><h2><a href="' . e($result['url']) . '">' . e($result['title']) . '</a></h2><p>' . $result['excerpt'] . '</p></article>', $results)) . '</div>');
    $main = '<section class="dc-section"><div class="ck-container ck-stack"><header><p class="dc-kicker">SEARCH</p><h1>İçerikte ara</h1></header><form class="dc-filter" method="get"><input name="q" value="' . e($query) . '" placeholder="Yayınlanmış içeriklerde ara"><button class="ck-button" type="submit">Ara</button></form>' . $items . '</div></section>';
    return Response::html(View::public('Arama', 'DizgeCMS yayınlanmış içerik araması.', $main, '/demo/search' . ($query === '' ? '' : '?q=' . rawurlencode($query))));
});

app()->get('/demo/sitemap.xml', function (Request $request): Response {
    $repo = cms_repo();
    $urls = [
        ...cache()->remember('demo:sitemap:pages', 300, fn (): array => $repo->published('page', 1000)),
        ...cache()->remember('demo:sitemap:articles', 300, fn (): array => $repo->published('article', 1000)),
    ];
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://dizgephp.digitaltamam.com/demo</loc></url><url><loc>https://dizgephp.digitaltamam.com/demo/articles</loc></url>';
    foreach ($urls as $item) $xml .= '<url><loc>https://dizgephp.digitaltamam.com/demo/' . ($item['type'] === 'page' ? 'pages/' : 'articles/') . rawurlencode((string) $item['slug']) . '</loc><lastmod>' . e(substr((string) $item['updated_at'], 0, 10)) . '</lastmod></url>';
    return Response::raw($xml . '</urlset>', 'application/xml; charset=UTF-8');
});

app()->get('/demo/api/context', function (Request $request): Response {
    $query = trim((string) $request->query('q', ''));
    return Response::json(['query' => $query, 'sources' => (new FtsRetrieval(cms_repo()))->search($query, 5)]);
}, [new Dizge\Security\Throttle(new RateLimiter(app()->basePath() . '/storage/rate-limits'), 30, 60, fn (Request $request): string => 'context:' . ($request->header('remote-addr') ?? 'anonymous'))]);

app()->get('/demo/studio/login', function (Request $request): Response {
    if (auth()->check()) return Response::redirect('/demo/studio');
    return Response::html(cms_login_screen());
});

app()->post('/demo/studio/login', function (Request $request): Response {
    $email = trim((string) $request->input('email'));
    $password = (string) $request->input('password');
    if (!cms_login()->attempt($email, $password)) return Response::html(cms_login_screen('E-posta veya parola geçersiz.', $email), 422);
    return Response::redirect('/demo/studio');
});

app()->post('/demo/studio/logout', function (Request $request): Response {
    auth()->logout();
    return Response::redirect('/demo/studio/login');
}, cms_protected());

app()->get('/demo/studio', function (Request $request): Response {
    $query = trim((string) $request->query('q', ''));
    $type = (string) $request->query('type', '');
    $status = (string) $request->query('status', '');
    $rows = cms_repo()->studioList($query, $type, $status);
    $table = $rows === [] ? '<p class="dc-empty">Henüz içerik yok. İlk taslağı oluştur.</p>' : '<div class="dc-table-wrap"><table><thead><tr><th>Başlık</th><th>Tür</th><th>Durum</th><th>Güncelleme</th></tr></thead><tbody>' . implode('', array_map(fn (array $row): string => '<tr><td><a href="/demo/studio/content/' . (int) $row['id'] . '">' . e((string) $row['title']) . '</a><small>/' . e((string) $row['slug']) . '</small></td><td>' . e((string) $row['type']) . '</td><td>' . View::badge($row) . '</td><td>' . e(gmdate('d.m.Y H:i', strtotime((string) $row['updated_at']))) . '</td></tr>', $rows)) . '</tbody></table></div>';
    $filter = '<form class="dc-filter" method="get" action="/demo/studio"><input name="q" placeholder="Başlık veya slug ara" value="' . e($query) . '"><select name="type"><option value="">Tüm türler</option><option value="page"' . ($type === 'page' ? ' selected' : '') . '>Sayfa</option><option value="article"' . ($type === 'article' ? ' selected' : '') . '>Makale</option></select><select name="status"><option value="">Tüm durumlar</option>' . implode('', array_map(fn (string $item): string => '<option value="' . e($item) . '"' . ($status === $item ? ' selected' : '') . '>' . e($item) . '</option>', Workflow::statuses())) . '</select><button class="ck-button ck-button--secondary" type="submit">Filtrele</button></form>';
    $main = '<header class="dc-toolbar"><div><p class="dc-kicker">STUDIO</p><h1>İçerikler</h1></div><a class="ck-button" href="/demo/studio/content/new">Yeni içerik</a></header>' . $filter . $table;
    return Response::html(View::studio('İçerikler', $main, auth()->user()));
}, cms_protected());

app()->get('/demo/studio/content/new', fn (Request $request): Response => Response::html(View::studio('Yeni içerik', cms_editor_form(), auth()->user())), cms_protected());

app()->post('/demo/studio/content', function (Request $request): Response {
    if (!Policy::canCreate(auth()->requireUser())) return Response::html('<h1>403</h1><p>Bu rol içerik oluşturamaz.</p>', 403);
    try {
        $input = cms_content_input($request);
        $id = cms_repo()->create([...$input, 'author_id' => (int) auth()->requireUser()->id()]);
        cms_audit()->write('cms.content.created', ['content_id' => $id, 'actor_id' => (int) auth()->requireUser()->id()]);
        return Response::redirect('/demo/studio/content/' . $id);
    } catch (ValidationException $exception) {
        return Response::html(View::studio('Yeni içerik', cms_editor_form($exception->errors()->all(), ['type' => $request->input('type'), 'title' => $request->input('title'), 'slug' => $request->input('slug'), 'body_html' => $request->input('body_html'), 'meta_description' => $request->input('meta_description')]), auth()->user()), 422);
    } catch (InvalidArgumentException $exception) {
        return Response::html(View::studio('Yeni içerik', cms_editor_form(['body_html' => [$exception->getMessage()]], ['type' => $request->input('type'), 'title' => $request->input('title'), 'slug' => $request->input('slug'), 'body_html' => $request->input('body_html'), 'meta_description' => $request->input('meta_description')]), auth()->user()), 422);
    }
}, cms_protected());

app()->get('/demo/studio/content/{id}', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    if ($content === null) return Response::notFound();
    if (!Policy::canView(auth()->requireUser(), $content)) return Response::html('<h1>403</h1><p>Bu içeriği görüntüleyemezsin.</p>', 403);
    return Response::html(View::studio((string) $content['title'], cms_editor_form([], [], $content) . cms_workflow_panel($content, null, auth()->user()), auth()->user()));
}, cms_protected());

app()->post('/demo/studio/content/{id}', function (Request $request, array $params): Response {
    $id = (int) $params['id'];
    $content = cms_repo()->find($id);
    if ($content === null) return Response::notFound();
    if (!Policy::canEdit(auth()->requireUser(), $content)) return Response::html('<h1>403</h1><p>Bu içeriği düzenleyemezsin.</p>', 403);
    try {
        cms_repo()->update($id, cms_content_input($request, $id), (int) auth()->requireUser()->id());
        cms_audit()->write('cms.content.saved', ['content_id' => $id, 'actor_id' => (int) auth()->requireUser()->id()]);
        return Response::redirect('/demo/studio/content/' . $id);
    } catch (ValidationException $exception) {
        return Response::html(View::studio((string) $content['title'], cms_editor_form($exception->errors()->all(), ['type' => $request->input('type'), 'title' => $request->input('title'), 'slug' => $request->input('slug'), 'body_html' => $request->input('body_html'), 'meta_description' => $request->input('meta_description')], $content), auth()->user()), 422);
    } catch (InvalidArgumentException $exception) {
        return Response::html(View::studio((string) $content['title'], cms_editor_form(['body_html' => [$exception->getMessage()]], ['type' => $request->input('type'), 'title' => $request->input('title'), 'slug' => $request->input('slug'), 'body_html' => $request->input('body_html'), 'meta_description' => $request->input('meta_description')], $content), auth()->user()), 422);
    }
}, cms_protected());

app()->post('/demo/studio/content/{id}/media', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    if ($content === null) return Response::notFound();
    if (!Policy::canUpload(auth()->requireUser()) || !Policy::canEdit(auth()->requireUser(), $content)) return Response::html('<h1>403</h1><p>Bu rol görsel yükleyemez.</p>', 403);
    try {
        $media = cms_media()->store($request->file('image') ?? []);
        $id = cms_repo()->createMedia($media, (int) auth()->requireUser()->id());
        cms_audit()->write('cms.media.uploaded', ['content_id' => (int) $content['id'], 'media_id' => $id, 'actor_id' => (int) auth()->requireUser()->id()]);
        $notice = '<div class="ck-alert ck-alert--success"><strong>Görsel yüklendi.</strong> Editörde Görsel aracına şu URL’yi gir: <code>/demo/media/' . $id . '</code></div>';
        return Response::html(View::studio((string) $content['title'], $notice . cms_editor_form([], [], $content) . cms_workflow_panel($content, null, auth()->user()), auth()->user()));
    } catch (InvalidArgumentException $exception) {
        return Response::html(View::studio((string) $content['title'], cms_editor_form(['image' => [$exception->getMessage()]], [], $content) . cms_workflow_panel($content, null, auth()->user()), auth()->user()), 422);
    }
}, cms_protected());

app()->get('/demo/media/{id}', function (Request $request, array $params): Response {
    $media = cms_repo()->media((int) $params['id']);
    if ($media === null) return Response::notFound();
    try { $path = cms_media()->path((string) $media['stored_name']); }
    catch (InvalidArgumentException) { return Response::notFound(); }
    if (!is_file($path)) return Response::notFound();
    $bytes = file_get_contents($path);
    return $bytes === false ? Response::notFound() : Response::raw($bytes, (string) $media['mime'])->withHeader('Cache-Control', 'public, max-age=86400');
});

app()->get('/demo/studio/content/{id}/preview', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    return $content === null ? Response::notFound() : Response::html(View::public((string) $content['title'], 'Studio preview.', '<section class="dc-preview-note"><div class="ck-container">Studio önizlemesi · ' . View::badge($content) . '</div></section><section class="dc-section"><div class="ck-container">' . cms_public_article($content) . '</div></section>'));
}, cms_protected());

app()->post('/demo/studio/content/{id}/transition', function (Request $request, array $params): Response {
    $id = (int) $params['id'];
    $content = cms_repo()->find($id);
    if ($content === null) return Response::notFound();
    $to = (string) $request->input('to_status');
    $note = trim((string) $request->input('note'));
    if (strlen($note) > 1000 || !Policy::canTransition(auth()->requireUser(), (string) $content['status'], $to)) return Response::html(View::studio((string) $content['title'], cms_editor_form([], [], $content) . cms_workflow_panel($content, 'Bu durum geçişi bu rol için desteklenmiyor.', auth()->user()), auth()->user()), 422);
    cms_repo()->transition($id, $to, (int) auth()->requireUser()->id(), $note);
    cms_audit()->write('cms.content.transitioned', ['content_id' => $id, 'actor_id' => (int) auth()->requireUser()->id(), 'from' => (string) $content['status'], 'to' => $to]);
    cms_invalidate_public_cache();
    return Response::redirect('/demo/studio/content/' . $id);
}, cms_protected());

app()->get('/demo/studio/content/{id}/revisions', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    if ($content === null) return Response::notFound();
    $revisions = cms_repo()->revisions((int) $content['id']);
    $events = cms_repo()->events((int) $content['id']);
    $revisionRows = implode('', array_map(function (array $revision) use ($content): string {
        $restore = Policy::canRestore(auth()->requireUser()) ? '<form method="post" action="/demo/studio/content/' . (int) $content['id'] . '/revisions/' . (int) $revision['id'] . '/restore"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><button class="dc-link-button" type="submit">Bu revision’ı taslak olarak geri yükle</button></form>' : '';
        $summary = cms_revision_summary((string) $content['body_html'], (string) $revision['body_html']);
        return '<li><strong>Revision #' . (int) $revision['id'] . '</strong> · ' . e((string) $revision['status']) . ' · ' . e((string) $revision['actor_email']) . '<br><small>' . e((string) $revision['created_at']) . '</small><p class="dc-diff">' . $summary . '</p>' . $restore . '</li>';
    }, $revisions));
    $eventRows = implode('', array_map(fn (array $event): string => '<li><strong>' . e((string) $event['event']) . '</strong> · ' . e((string) $event['actor_email']) . ($event['note'] === null ? '' : '<p>' . e((string) $event['note']) . '</p>') . '</li>', $events));
    $main = '<header class="dc-toolbar"><div><p class="dc-kicker">HISTORY</p><h1>' . e((string) $content['title']) . '</h1></div><a href="/demo/studio/content/' . (int) $content['id'] . '">Düzenlemeye dön</a></header><div class="dc-two-column"><section><h2>Revision’lar</h2><ol class="dc-timeline">' . $revisionRows . '</ol></section><section><h2>Akış notları</h2><ol class="dc-timeline">' . $eventRows . '</ol></section></div>';
    return Response::html(View::studio('Revision geçmişi', $main, auth()->user()));
}, cms_protected());

app()->post('/demo/studio/content/{id}/revisions/{revision}/restore', function (Request $request, array $params): Response {
    if (!Policy::canRestore(auth()->requireUser())) return Response::html('<h1>403</h1><p>Revision geri yükleme yalnızca admin içindir.</p>', 403);
    $id = (int) $params['id'];
    cms_repo()->restoreRevision($id, (int) $params['revision'], (int) auth()->requireUser()->id());
    cms_audit()->write('cms.content.restored', ['content_id' => $id, 'actor_id' => (int) auth()->requireUser()->id(), 'revision_id' => (int) $params['revision']]);
    cms_invalidate_public_cache();
    return Response::redirect('/demo/studio/content/' . $id);
}, cms_protected());

function cms_revision_summary(string $current, string $revision): string
{
    $currentText = trim(preg_replace('/\s+/', ' ', strip_tags(cms_sanitizer()->sanitize($current))) ?? '');
    $revisionText = trim(preg_replace('/\s+/', ' ', strip_tags(cms_sanitizer()->sanitize($revision))) ?? '');
    if ($currentText === $revisionText) return 'Mevcut gövdeyle aynı.';
    return 'Mevcut: “' . e(mb_strimwidth($currentText, 0, 90, '…', 'UTF-8')) . '” · Revision: “' . e(mb_strimwidth($revisionText, 0, 90, '…', 'UTF-8')) . '”';
}

function cms_workflow_panel(array $content, ?string $error = null, ?Dizge\Security\Identity $identity = null): string
{
    $next = array_values(array_filter(Workflow::next((string) $content['status']), fn (string $status): bool => $identity === null || Policy::canTransition($identity, (string) $content['status'], $status)));
    $buttons = $next === [] ? '<p>Bu içerik için geçiş yok.</p>' : implode('', array_map(fn (string $status): string => '<button class="ck-button ck-button--secondary" type="submit" name="to_status" value="' . e($status) . '">' . e($status) . ' durumuna al</button>', $next));
    $publicUrl = (string) $content['status'] === 'published' ? '/demo/' . ((string) $content['type'] === 'page' ? 'pages/' : 'articles/') . rawurlencode((string) $content['slug']) : '';
    $guidance = $publicUrl === '' ? '<p class="dc-help">Public URL için sıradaki zorunlu adım: <strong>published</strong>. Şu anki durum: ' . e((string) $content['status']) . '.</p>' : '<p class="dc-help">Yayınlandı: <a href="' . e($publicUrl) . '" target="_blank" rel="noopener">' . e($publicUrl) . '</a></p>';
    return '<section class="dc-workflow"><header><p class="dc-kicker">WORKFLOW</p><h2>Yayın akışı ' . View::badge($content) . '</h2></header>' . $guidance . ($error === null ? '' : '<p class="dc-error">' . e($error) . '</p>') . '<form method="post" action="/demo/studio/content/' . (int) $content['id'] . '/transition"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>İç review notu<textarea name="note" maxlength="1000" rows="3"></textarea></label><div class="ck-cluster">' . $buttons . '<a href="/demo/studio/content/' . (int) $content['id'] . '/revisions">Revision geçmişi</a></div></form></section>';
}

function cms_login_screen(?string $error = null, string $email = ''): string
{
    $errorHtml = $error === null ? '' : '<div class="ck-alert ck-alert--danger" role="alert">' . e($error) . '</div>';
    $main = '<section class="dc-login"><div class="dc-login-copy"><p class="dc-kicker">DIZGECMS STUDIO</p><h1>İçeriği aceleye değil, akışa bağla.</h1><p>Taslak, inceleme, onay ve yayın adımları; bir SQLite dosyasında açık ve izlenebilir.</p><a href="/demo">Public görünüm</a></div><form class="dc-login-form ck-stack" method="post" action="/demo/studio/login"><h2>Giriş yap</h2>' . $errorHtml . '<input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>E-posta<input type="email" required name="email" autocomplete="email" value="' . e($email) . '"></label><label>Parola<input type="password" required name="password" autocomplete="current-password"></label><button class="ck-button" type="submit">Studio’ya gir</button></form></section>';
    return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Giriş · DizgeCMS Studio</title><meta name="robots" content="noindex,nofollow"><link rel="icon" href="/assets/dizgecms-mark.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/ckcss.min.css"><link rel="stylesheet" href="/assets/dizgecms.css"></head><body>' . $main . '</body></html>';
}
