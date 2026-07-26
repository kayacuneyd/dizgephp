<?php

declare(strict_types=1);

use Dizge\Cms\ContentRepository;
use Dizge\Cms\ExtensionRegistry;
use Dizge\Cms\HtmlSanitizer;
use Dizge\Cms\FtsRetrieval;
use Dizge\Cms\MediaStorage;
use Dizge\Cms\Policy;
use Dizge\Cms\PreviewTokenStore;
use Dizge\Cms\Slug;
use Dizge\Cms\SiteRepository;
use Dizge\Cms\View;
use Dizge\Cms\Workflow;
use Dizge\Data\Migrator;
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

function cms_web_install_allowed(): bool { return getenv('DIZGECMS_ALLOW_WEB_INSTALL') === '1'; }

app()->get('/install', function (Request $request): Response {
    if (!cms_web_install_allowed()) return Response::notFound();
    try { $installed = (int) db()->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0; } catch (Throwable) { $installed = false; }
    if ($installed) return Response::notFound();
    return Response::html('<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>DizgeCMS kurulum</title></head><body><main><h1>DizgeCMS kurulumu</h1><form method="post" action="/install"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>Site adı<input required name="site_name" maxlength="120"></label><label>Yönetici e-posta<input required type="email" name="email"></label><label>Parola<input required type="password" minlength="12" name="password"></label><button>Kurulumu tamamla</button></form></main></body></html>');
});

app()->post('/install', function (Request $request): Response {
    if (!cms_web_install_allowed()) return Response::notFound();
    (new Migrator(db()))->migrate(Migrator::load(app()->basePath() . '/database/migrations'));
    if ((int) db()->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) return Response::notFound();
    $email = strtolower(trim((string) $request->input('email'))); $password = (string) $request->input('password'); $name = trim((string) $request->input('site_name'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || $name === '') return Response::html('<h1>422</h1><p>Geçerli site adı, e-posta ve en az 12 karakter parola gerekir.</p>', 422);
    db()->pdo()->prepare('INSERT INTO users (email,password_hash,role,created_at) VALUES (?,?,?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),'admin',gmdate('c')]); $author=(int)db()->pdo()->lastInsertId(); $repo=new ContentRepository(db()); foreach ([['Ana Sayfa','ana-sayfa'],['Hakkımızda','hakkimizda'],['Hizmetler','hizmetler'],['İletişim','iletisim']] as [$title,$slug]) $repo->create(['type'=>'page','title'=>$title,'slug'=>$slug,'body_html'=>'<p>'.$title.'</p>','author_id'=>$author]); $site=new SiteRepository(db()); $site->saveSettings(['site_name'=>$name]); $site->replaceMenu('primary',[['kind'=>'url','label'=>'Ana Sayfa','url'=>'/'],['kind'=>'url','label'=>'Hakkımızda','url'=>'/pages/hakkimizda'],['kind'=>'url','label'=>'İletişim','url'=>'/pages/iletisim']]);
    return Response::redirect('/studio/login');
});

/** @return ContentRepository */
function cms_repo(): ContentRepository { return new ContentRepository(db()); }
function cms_sanitizer(): HtmlSanitizer { return new HtmlSanitizer(); }
function cms_audit(): AuditLog { return new AuditLog(app()->basePath() . '/storage/logs/cms-audit.log'); }
function cms_login(): LoginService { return new LoginService(new PdoUserProvider(db(), 'users', 'email', 'password_hash', 'id', 'is_active'), auth(), new RateLimiter(app()->basePath() . '/storage/rate-limits'), cms_audit()); }
function cms_media(): MediaStorage { return new MediaStorage(app()->basePath() . '/storage/media'); }
function cms_preview_tokens(): PreviewTokenStore { return new PreviewTokenStore(db(), (string) (getenv('DIZGECMS_PREVIEW_SECRET') ?: '')); }
function cms_site(): SiteRepository { return new SiteRepository(db()); }
function cms_extensions(): ExtensionRegistry { return app()->container()->get(ExtensionRegistry::class); }
function cms_is_admin(): bool { return (string) auth()->requireUser()->attribute('role') === 'admin'; }
function cms_active_admin_count(): int { return (int) db()->pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn(); }
/** @return list<Dizge\Http\Middleware> */
function cms_protected(): array { return [new RequireAuth(auth(), '/studio/login'), new RequireAbility(auth(), gate(), 'cms.manage')]; }

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
    $action = $isNew ? '/studio/content' : '/studio/content/' . (int) $content['id'];
    $heading = $isNew ? 'Yeni içerik' : 'İçeriği düzenle';
    $buttons = '<button class="ck-button" type="submit">' . ($isNew ? 'Taslak oluştur' : 'Değişiklikleri kaydet') . '</button>';
    if (!$isNew) $buttons .= '<a class="ck-button ck-button--secondary" href="/studio/content/' . (int) $content['id'] . '/preview">Önizleme</a>';
    $headlessPreview = $isNew ? '' : '<form method="post" action="/studio/content/' . (int) $content['id'] . '/preview-link"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><button class="ck-button ck-button--secondary" type="submit">Headless preview bağlantısı oluştur</button></form>';
    $options = $isNew ? '' : '<option value="">Kapak görseli yok</option>' . implode('', array_map(fn (array $media): string => '<option value="' . (int) $media['id'] . '"' . ((int) ($content['featured_media_id'] ?? 0) === (int) $media['id'] ? ' selected' : '') . '>' . e((string) $media['original_name']) . '</option>', cms_repo()->mediaList()));
    $pickerItems = $isNew ? '' : implode('', array_map(fn (array $media): string => '<button type="button" class="ck-button ck-button--secondary" data-media-url="/media/' . (int) $media['id'] . '" data-media-alt="' . e((string) $media['alt_text']) . '">' . e((string) $media['original_name']) . '</button>', cms_repo()->mediaList(20)));
    $picker = $pickerItems === '' ? '<p class="dc-help">Henüz seçilebilecek görsel yok.</p>' : '<div class="ck-cluster" data-cms-media-picker>' . $pickerItems . '</div>';
    $media = $isNew ? '' : '<section class="dc-media-panel"><h2>Görsel ekle</h2><p>Bir görsele tıklayarak editöre ekleyin. JPEG, PNG, WebP veya GIF; en fazla 5 MB.</p>' . $picker . '<form method="post" action="/studio/content/' . (int) $content['id'] . '/media" enctype="multipart/form-data"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><input required type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><button class="ck-button ck-button--secondary" type="submit">Yeni görsel yükle</button></form><form method="post" action="/studio/content/' . (int) $content['id'] . '/featured-media"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>Kapak görseli<select name="media_id">' . $options . '</select></label><button class="ck-button ck-button--secondary">Kapak görselini kaydet</button></form></section>';
    return '<header class="dc-toolbar"><div><p class="dc-kicker">STUDIO / ' . ($isNew ? 'CREATE' : 'EDIT') . '</p><h1>' . $heading . '</h1></div><a href="/studio">İçeriklere dön</a></header>'
        . View::errors($errors) . '<form class="dc-form ck-stack" method="post" action="' . $action . '"><input type="hidden" name="_token" value="' . e(csrf_token()) . '">'
        . '<label>İçerik türü<select name="type"><option value="article"' . ($type === 'article' ? ' selected' : '') . '>Makale</option><option value="page"' . ($type === 'page' ? ' selected' : '') . '>Sayfa</option><option value="service"' . ($type === 'service' ? ' selected' : '') . '>Hizmet</option></select></label>'
        . '<label>Başlık<input required maxlength="160" name="title" value="' . e($value('title')) . '"></label>'
        . '<label>Slug <small>Boş bırakırsan başlıktan üretilir. Makale: <code>/articles/slug</code>; Sayfa: <code>/pages/slug</code>. İçerik yalnızca <strong>published</strong> olduğunda public olur.</small><input maxlength="160" name="slug" value="' . e($value('slug')) . '"></label>'
        . '<label>Meta açıklama <small>Public arama sonucu ve sosyal paylaşım özeti için, en fazla 160 karakter.</small><textarea maxlength="160" name="meta_description" rows="2">' . e($value('meta_description')) . '</textarea></label>'
        . '<label>İçerik</label><dv-rich-editor data-name="body_html" data-value="' . e($body) . '"></dv-rich-editor><textarea class="dc-editor-source" name="body_html" required aria-label="İçerik HTML kaynağı">' . e($body) . '</textarea>'
        . '<p class="dc-help">Yalnızca güvenli başlık, paragraf, liste, link, görsel, alıntı ve kod biçimleri saklanır.</p><div class="ck-cluster">' . $buttons . '</div></form>' . $headlessPreview . $media . '<script type="module" src="/assets/demo/dv-rich-editor.js"></script><script type="module" src="/assets/demo/cms-autosave.js"></script><script type="module" src="/assets/demo/cms-media-picker.js"></script>';
}

/** @return array{type:string,title:string,slug:string,body_html:string} */
function cms_content_input(Request $request, ?int $exceptId = null): array
{
    $input = ['type' => $request->input('type'), 'title' => trim((string) $request->input('title')), 'slug' => trim((string) $request->input('slug')), 'body_html' => (string) $request->input('body_html')];
    $input['meta_description'] = trim((string) $request->input('meta_description'));
    (new Validator())->validate($input, ['type' => 'required|in:page,article,service', 'title' => 'required|string|max:160', 'slug' => 'nullable|string|max:160', 'body_html' => 'required|string|max:50000', 'meta_description' => 'nullable|string|max:160']);
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
    $media = !empty($content['featured_media_id']) ? cms_repo()->media((int) $content['featured_media_id']) : null;
    $cover = $media === null ? '' : '<figure class="dc-featured-media"><img src="/media/' . (int) $media['id'] . '" alt="' . e((string) ($media['alt_text'] ?? '')) . '">' . (($media['caption'] ?? '') === '' ? '' : '<figcaption>' . e((string) $media['caption']) . '</figcaption>') . '</figure>';
    return '<article class="dc-prose"><p class="dc-kicker">' . e(strtoupper((string) $content['type'])) . ($date === '' ? '' : ' · ' . e($date)) . '</p><h1>' . e((string) $content['title']) . '</h1>' . $cover . '<div class="dc-rich-text">' . cms_sanitizer()->sanitize((string) $content['body_html']) . '</div></article>';
}

app()->get('/demo', function (Request $request): Response {
    $repo = cms_repo();
    $pages = cache()->remember('demo:home:pages', 60, fn (): array => $repo->published('page', 6));
    $articles = cache()->remember('demo:home:articles', 60, fn (): array => $repo->published('article', 6));
    $pageLinks = $pages === [] ? '<p class="dc-empty">Henüz yayınlanmış sayfa yok.</p>' : '<div class="dc-card-grid">' . implode('', array_map(fn (array $page): string => '<a class="dc-card" href="/pages/' . e((string) $page['slug']) . '"><span>Sayfa</span><strong>' . e((string) $page['title']) . '</strong></a>', $pages)) . '</div>';
    $articleLinks = $articles === [] ? '<p class="dc-empty">İlk makale Studio’dan yayınlandığında burada görünecek.</p>' : '<div class="dc-card-grid">' . implode('', array_map(fn (array $article): string => '<a class="dc-card" href="/articles/' . e((string) $article['slug']) . '"><span>Makale</span><strong>' . e((string) $article['title']) . '</strong><small>' . e(gmdate('d.m.Y', strtotime((string) $article['published_at']))) . '</small></a>', $articles)) . '</div>';
    $main = '<section class="dc-hero"><div class="ck-container ck-stack"><p class="dc-kicker">DIZGEPHP REFERENCE APP</p><h1>İçeriğin karar süreci görünür olsun.</h1><p>DizgeCMS Studio; taslak, inceleme, onay ve yayın akışını sade bir SQLite çekirdeği üzerinde kurar.</p><div class="ck-cluster"><a class="ck-button" href="/articles">Makaleleri incele</a><a class="ck-button ck-button--secondary" href="/studio">Studio’ya git</a></div></div></section><section class="dc-section"><div class="ck-container ck-stack"><header><p class="dc-kicker">SAYFALAR</p><h2>Kalıcı anlatılar</h2></header>' . $pageLinks . '</div></section><section class="dc-section dc-section--muted"><div class="ck-container ck-stack"><header><p class="dc-kicker">MAKALELER</p><h2>Yayın akışından çıkanlar</h2></header>' . $articleLinks . '</div></section>';
    return Response::html(View::public('DizgeCMS Studio', 'Workflow-first CMS referans uygulaması.', $main));
});

app()->get('/pages/{slug}', function (Request $request, array $params): Response {
    $content = cms_repo()->publicBySlug('page', $params['slug']);
    if ($content === null) {
        $redirect = cms_repo()->redirectBySlug('page', $params['slug']);
        return $redirect === null ? Response::notFound() : Response::redirect('/pages/' . rawurlencode((string) $redirect['slug']), 301);
    }
    return Response::html(View::public((string) $content['title'], (string) ($content['meta_description'] ?: 'DizgeCMS sayfası.'), '<section class="dc-section"><div class="ck-container">' . cms_public_article($content) . '</div></section>', '/pages/' . rawurlencode($params['slug'])));
});

app()->get('/articles', function (Request $request): Response {
    $articles = cms_repo()->published('article', 50);
    $items = $articles === [] ? '<p class="dc-empty">Henüz yayınlanmış makale yok.</p>' : '<div class="dc-list">' . implode('', array_map(fn (array $article): string => '<article><p class="dc-kicker">' . e(gmdate('d.m.Y', strtotime((string) $article['published_at']))) . '</p><h2><a href="/articles/' . e((string) $article['slug']) . '">' . e((string) $article['title']) . '</a></h2></article>', $articles)) . '</div>';
    return Response::html(View::public('Makaleler', 'DizgeCMS yayınlanmış makaleleri.', '<section class="dc-section"><div class="ck-container ck-stack"><header><p class="dc-kicker">YAYIN</p><h1>Makaleler</h1></header>' . $items . '</div></section>', '/articles'));
});

app()->get('/articles/{slug}', function (Request $request, array $params): Response {
    $content = cms_repo()->publicBySlug('article', $params['slug']);
    if ($content === null) {
        $redirect = cms_repo()->redirectBySlug('article', $params['slug']);
        return $redirect === null ? Response::notFound() : Response::redirect('/articles/' . rawurlencode((string) $redirect['slug']), 301);
    }
    return Response::html(View::public((string) $content['title'], (string) ($content['meta_description'] ?: 'DizgeCMS makalesi.'), '<section class="dc-section"><div class="ck-container">' . cms_public_article($content) . '</div></section>', '/articles/' . rawurlencode($params['slug'])));
});

app()->get('/services/{slug}', function (Request $request, array $params): Response {
    $content = cms_repo()->publicBySlug('service', $params['slug']);
    if ($content === null) {
        $redirect = cms_repo()->redirectBySlug('service', $params['slug']);
        return $redirect === null ? Response::notFound() : Response::redirect('/services/' . rawurlencode((string) $redirect['slug']), 301);
    }
    return Response::html(View::public((string) $content['title'], (string) ($content['meta_description'] ?: 'DizgeCMS hizmeti.'), '<section class="dc-section"><div class="ck-container">' . cms_public_article($content) . '</div></section>', '/services/' . rawurlencode($params['slug'])));
});

app()->get('/search', function (Request $request): Response {
    $query = trim((string) $request->query('q', ''));
    $results = (new FtsRetrieval(cms_repo()))->search($query, 20);
    $items = $query === '' ? '<p class="dc-empty">Aramak istediğin kelimeyi yaz.</p>' : ($results === [] ? '<p class="dc-empty">Yayınlanmış içeriklerde sonuç bulunamadı.</p>' : '<div class="dc-list">' . implode('', array_map(fn (array $result): string => '<article><p class="dc-kicker">' . e($result['type']) . '</p><h2><a href="' . e($result['url']) . '">' . e($result['title']) . '</a></h2><p>' . $result['excerpt'] . '</p></article>', $results)) . '</div>');
    $main = '<section class="dc-section"><div class="ck-container ck-stack"><header><p class="dc-kicker">SEARCH</p><h1>İçerikte ara</h1></header><form class="dc-filter" method="get"><input name="q" value="' . e($query) . '" placeholder="Yayınlanmış içeriklerde ara"><button class="ck-button" type="submit">Ara</button></form>' . $items . '</div></section>';
    return Response::html(View::public('Arama', 'DizgeCMS yayınlanmış içerik araması.', $main, '/search' . ($query === '' ? '' : '?q=' . rawurlencode($query))));
});

app()->get('/cms-sitemap.xml', function (Request $request): Response {
    $repo = cms_repo();
    $urls = [
        ...cache()->remember('demo:sitemap:pages', 300, fn (): array => $repo->published('page', 1000)),
        ...cache()->remember('demo:sitemap:articles', 300, fn (): array => $repo->published('article', 1000)),
    ];
    $base = rtrim((string) (getenv('DIZGECMS_SITE_URL') ?: 'https://dizgecms.digitaltamam.com'), '/');
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . e($base . '/articles') . '</loc></url>';
    foreach ($urls as $item) $xml .= '<url><loc>' . e($base . '/' . ($item['type'] === 'page' ? 'pages/' : 'articles/') . rawurlencode((string) $item['slug'])) . '</loc><lastmod>' . e(substr((string) $item['updated_at'], 0, 10)) . '</lastmod></url>';
    return Response::raw($xml . '</urlset>', 'application/xml; charset=UTF-8');
});

app()->get('/api/context', function (Request $request): Response {
    $query = trim((string) $request->query('q', ''));
    return Response::json(['query' => $query, 'sources' => (new FtsRetrieval(cms_repo()))->search($query, 5)]);
}, [new Dizge\Security\Throttle(new RateLimiter(app()->basePath() . '/storage/rate-limits'), 30, 60, fn (Request $request): string => 'context:' . ($request->header('remote-addr') ?? 'anonymous'))]);

app()->get('/studio/login', function (Request $request): Response {
    if (auth()->check()) return Response::redirect('/studio');
    return Response::html(cms_login_screen());
});

app()->post('/studio/login', function (Request $request): Response {
    $email = trim((string) $request->input('email'));
    $password = (string) $request->input('password');
    if (!cms_login()->attempt($email, $password)) return Response::html(cms_login_screen('E-posta veya parola geçersiz.', $email), 422);
    return Response::redirect('/studio');
});

app()->post('/studio/logout', function (Request $request): Response {
    auth()->logout();
    return Response::redirect('/studio/login');
}, cms_protected());

app()->get('/studio', function (Request $request): Response {
    $query = trim((string) $request->query('q', ''));
    $type = (string) $request->query('type', '');
    $status = (string) $request->query('status', '');
    $rows = cms_repo()->studioList($query, $type, $status);
    $table = $rows === [] ? '<p class="dc-empty">Henüz içerik yok. İlk taslağı oluştur.</p>' : '<div class="dc-table-wrap"><table><thead><tr><th>Başlık</th><th>Tür</th><th>Durum</th><th>Güncelleme</th></tr></thead><tbody>' . implode('', array_map(fn (array $row): string => '<tr><td><a href="/studio/content/' . (int) $row['id'] . '">' . e((string) $row['title']) . '</a><small>/' . e((string) $row['slug']) . '</small></td><td>' . e((string) $row['type']) . '</td><td>' . View::badge($row) . '</td><td>' . e(gmdate('d.m.Y H:i', strtotime((string) $row['updated_at']))) . '</td></tr>', $rows)) . '</tbody></table></div>';
    $filter = '<form class="dc-filter" method="get" action="/studio"><input name="q" placeholder="Başlık veya slug ara" value="' . e($query) . '"><select name="type"><option value="">Tüm türler</option><option value="page"' . ($type === 'page' ? ' selected' : '') . '>Sayfa</option><option value="article"' . ($type === 'article' ? ' selected' : '') . '>Makale</option><option value="service"' . ($type === 'service' ? ' selected' : '') . '>Hizmet</option></select><select name="status"><option value="">Tüm durumlar</option>' . implode('', array_map(fn (string $item): string => '<option value="' . e($item) . '"' . ($status === $item ? ' selected' : '') . '>' . e($item) . '</option>', Workflow::statuses())) . '</select><button class="ck-button ck-button--secondary" type="submit">Filtrele</button></form>';
    $users = cms_is_admin() ? '<a class="ck-button ck-button--secondary" href="/studio/users">Kullanıcılar</a>' : '';
    $main = '<header class="dc-toolbar"><div><p class="dc-kicker">STUDIO</p><h1>İçerikler</h1></div><div class="ck-cluster">' . $users . '<a class="ck-button ck-button--secondary" href="/studio/media">Medya</a><a class="ck-button ck-button--secondary" href="/studio/site">Site ayarları</a><a class="ck-button" href="/studio/content/new">Yeni içerik</a></div></header>' . $filter . $table;
    return Response::html(View::studio('İçerikler', $main, auth()->user()));
}, cms_protected());

app()->get('/studio/users', function (Request $request): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
    $rows = db()->pdo()->query('SELECT id, email, role, is_active, created_at FROM users ORDER BY id')->fetchAll();
    $roles = Policy::roles();
    $items = implode('', array_map(fn (array $u): string => '<tr><td>' . e($u['email']) . '<small>' . ((int) $u['is_active'] === 1 ? 'Etkin' : 'Devre dışı') . '</small></td><td><form method="post" action="/studio/users/' . (int) $u['id'] . '"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><select name="role">' . implode('', array_map(fn (string $r): string => '<option value="' . e($r) . '"' . ($r === $u['role'] ? ' selected' : '') . '>' . e($r) . '</option>', $roles)) . '</select><select name="is_active"><option value="1"' . ((int) $u['is_active'] === 1 ? ' selected' : '') . '>Etkin</option><option value="0"' . ((int) $u['is_active'] === 0 ? ' selected' : '') . '>Devre dışı</option></select><button class="ck-button ck-button--secondary">Kaydet</button></form><form method="post" action="/studio/users/' . (int) $u['id'] . '/password"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>Yeni parola<input required minlength="12" type="password" name="password" autocomplete="new-password"></label><button class="ck-button ck-button--secondary">Parolayı yenile</button></form></td></tr>', $rows));
    $create = '<section class="dc-media-panel"><h2>Kullanıcı ekle</h2><form class="dc-form ck-stack" method="post" action="/studio/users"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>E-posta<input required type="email" name="email" autocomplete="email"></label><label>İlk parola <small>En az 12 karakter.</small><input required minlength="12" type="password" name="password" autocomplete="new-password"></label><label>Rol<select name="role">' . implode('', array_map(fn (string $r): string => '<option value="' . e($r) . '">' . e($r) . '</option>', $roles)) . '</select></label><button class="ck-button">Kullanıcı oluştur</button></form></section>';
    return Response::html(View::studio('Kullanıcılar', '<header class="dc-toolbar"><h1>Kullanıcılar</h1><a href="/studio">İçeriklere dön</a></header>' . $create . '<table><thead><tr><th>E-posta / durum</th><th>Rol, erişim ve parola</th></tr></thead><tbody>' . $items . '</tbody></table>', auth()->user()));
}, cms_protected());

app()->get('/studio/contact-submissions', function (Request $request): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
    $store = new Dizge\Cms\ContactSubmissionRepository(db(), (int) (getenv('DIZGECMS_CONTACT_RETENTION_DAYS') ?: 30));
    $rows = $store->recent();
    $items = $rows === [] ? '<p class="dc-empty">Saklama süresinde iletişim kaydı yok.</p>' : '<ol class="dc-timeline">' . implode('', array_map(fn (array $row): string => '<li><strong>' . e((string) $row['subject']) . '</strong><br><small>' . e((string) $row['received_at']) . ' · silinecek: ' . e((string) $row['expires_at']) . '</small><p>' . e((string) $row['name']) . ' · ' . e((string) $row['email']) . '</p><p>' . nl2br(e((string) $row['message'])) . '</p><form method="post" action="/studio/contact-submissions/' . (int) $row['id'] . '/access"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><button class="dc-link-button">Erişimi kayda al</button></form></li>', $rows)) . '</ol>';
    return Response::html(View::studio('İletişim kayıtları', '<header class="dc-toolbar"><h1>İletişim kayıtları</h1><a href="/studio">Studio’ya dön</a></header><p class="dc-help">Kayıtlar yalnızca yöneticilere açıktır ve saklama süresi sonunda otomatik silinir.</p>' . $items, auth()->user()));
}, cms_protected());

app()->post('/studio/contact-submissions/{id}/access', function (Request $request, array $params): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
    $id = (int) $params['id']; $actor = (int) auth()->requireUser()->id();
    $store = new Dizge\Cms\ContactSubmissionRepository(db(), (int) (getenv('DIZGECMS_CONTACT_RETENTION_DAYS') ?: 30));
    if (!$store->markAccessed($id, $actor)) return Response::notFound();
    cms_audit()->write('cms.contact_submission.accessed', ['submission_id' => $id, 'actor_id' => $actor]);
    return Response::redirect('/studio/contact-submissions');
}, cms_protected());

app()->post('/studio/users', function (Request $request): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
    $email = strtolower(trim((string) $request->input('email'))); $password = (string) $request->input('password'); $role = (string) $request->input('role');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || !in_array($role, Policy::roles(), true)) return Response::html('<h1>422</h1><p>Geçerli e-posta, en az 12 karakter parola ve rol gerekir.</p>', 422);
    try { db()->pdo()->prepare('INSERT INTO users (email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, 1, ?)')->execute([$email, password_hash($password, PASSWORD_DEFAULT), $role, gmdate('c')]); }
    catch (PDOException) { return Response::html('<h1>422</h1><p>Bu e-posta zaten kullanılıyor.</p>', 422); }
    cms_audit()->write('cms.user.created', ['user_id' => (string) db()->pdo()->lastInsertId(), 'actor_id' => (int) auth()->requireUser()->id(), 'role' => $role]);
    return Response::redirect('/studio/users');
}, cms_protected());

app()->post('/studio/users/{id}', function (Request $request, array $params): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
    $id = (int) $params['id']; $target = db()->table('users')->where('id', '=', $id)->first();
    if ($target === null) return Response::notFound();
    $role = (string) $request->input('role'); $active = (string) $request->input('is_active');
    if (!in_array($role, Policy::roles(), true) || !in_array($active, ['0', '1'], true)) return Response::html('<h1>422</h1><p>Invalid role or status.</p>', 422);
    $actorId = (int) auth()->requireUser()->id();
    if ($id === $actorId && ($role !== 'admin' || $active !== '1')) return Response::html('<h1>422</h1><p>Kendi yönetici erişimini veya hesabını devre dışı bırakamazsın.</p>', 422);
    if ((string) $target['role'] === 'admin' && (int) $target['is_active'] === 1 && ($role !== 'admin' || $active !== '1') && cms_active_admin_count() < 2) return Response::html('<h1>422</h1><p>En az bir etkin yönetici kalmalı.</p>', 422);
    db()->pdo()->prepare('UPDATE users SET role = ?, is_active = ? WHERE id = ?')->execute([$role, (int) $active, $id]); cms_audit()->write('cms.user.access_saved', ['user_id' => $id, 'actor_id' => $actorId, 'role' => $role, 'is_active' => $active === '1']); return Response::redirect('/studio/users');
}, cms_protected());

app()->post('/studio/users/{id}/password', function (Request $request, array $params): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
    $id = (int) $params['id']; $password = (string) $request->input('password');
    if (strlen($password) < 12) return Response::html('<h1>422</h1><p>Parola en az 12 karakter olmalı.</p>', 422);
    $updated = db()->pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?'); $updated->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    if ($updated->rowCount() !== 1) return Response::notFound();
    cms_audit()->write('cms.user.password_reset', ['user_id' => $id, 'actor_id' => (int) auth()->requireUser()->id()]);
    return Response::redirect('/studio/users');
}, cms_protected());

app()->get('/studio/media', function (Request $request): Response {
    $query = trim((string) $request->query('q', ''));
    $rows = cms_repo()->mediaList(100, $query);
    $canDelete = cms_is_admin();
    $cards = $rows === [] ? '<p class="dc-empty">Bu aramayla eşleşen görsel yok.</p>' : '<div class="dc-card-grid">' . implode('', array_map(fn (array $item): string => '<article class="dc-card"><img alt="' . e((string) $item['alt_text']) . '" src="/media/' . (int) $item['id'] . '"><strong>' . e((string) $item['original_name']) . '</strong><form method="post" action="/studio/media/' . (int) $item['id'] . '"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>Alt metin<input maxlength="160" name="alt_text" value="' . e((string) $item['alt_text']) . '"></label><label>Açıklama<textarea maxlength="500" name="caption">' . e((string) $item['caption']) . '</textarea></label><button class="ck-button ck-button--secondary">Kaydet</button></form>' . ($canDelete ? '<form method="post" action="/studio/media/' . (int) $item['id'] . '/delete"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><button class="dc-link-button" type="submit">Kullanılmıyorsa sil</button></form>' : '') . '<code>/media/' . (int) $item['id'] . '</code></article>', $rows)) . '</div>';
    $search = '<form class="dc-filter" method="get" action="/studio/media"><input name="q" value="' . e($query) . '" placeholder="Dosya adı, alt metin veya açıklama ara"><button class="ck-button ck-button--secondary">Ara</button></form>';
    return Response::html(View::studio('Medya kütüphanesi', '<header class="dc-toolbar"><h1>Medya kütüphanesi</h1><a href="/studio">İçeriklere dön</a></header>' . $search . $cards, auth()->user()));
}, cms_protected());

app()->post('/studio/media/{id}', function (Request $request, array $params): Response {
    try { $saved = cms_repo()->updateMediaMetadata((int) $params['id'], (string) $request->input('alt_text'), (string) $request->input('caption')); }
    catch (InvalidArgumentException $exception) { return Response::html('<h1>422</h1><p>' . e($exception->getMessage()) . '</p>', 422); }
    if (!$saved) return Response::notFound();
    cms_audit()->write('cms.media.metadata_saved', ['media_id' => (int) $params['id'], 'actor_id' => (int) auth()->requireUser()->id()]);
    return Response::redirect('/studio/media');
}, cms_protected());

app()->post('/studio/media/{id}/delete', function (Request $request, array $params): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
    $media = cms_repo()->media((int) $params['id']);
    if ($media === null) return Response::notFound();
    try { $deleted = cms_repo()->deleteMediaIfUnused((int) $params['id']); }
    catch (InvalidArgumentException $exception) { return Response::html(View::studio('Medya kütüphanesi', '<p class="ck-alert ck-alert--error">' . e($exception->getMessage()) . '</p><p><a href="/studio/media">Kütüphaneye dön</a></p>', auth()->user()), 422); }
    if (!$deleted) return Response::notFound();
    cms_media()->delete((string) $media['stored_name']);
    cms_audit()->write('cms.media.deleted', ['media_id' => (int) $params['id'], 'actor_id' => (int) auth()->requireUser()->id()]);
    return Response::redirect('/studio/media');
}, cms_protected());

app()->get('/studio/site', function (Request $request): Response {
    $s = cms_site()->settings();
    $pages = cms_repo()->studioList('', 'page'); $media = cms_repo()->mediaList();
    $pageOptions = '<option value="">Varsayılan ana sayfa</option>' . implode('', array_map(fn (array $p): string => '<option value="' . (int) $p['id'] . '"' . ((int) $s['home_page_id'] === (int) $p['id'] ? ' selected' : '') . '>' . e($p['title']) . '</option>', $pages));
    $mediaOptions = '<option value="">Seçilmedi</option>' . implode('', array_map(fn (array $m): string => '<option value="' . (int) $m['id'] . '"' . ((int) $s['logo_media_id'] === (int) $m['id'] ? ' selected' : '') . '>' . e($m['original_name']) . '</option>', $media));
    $faviconOptions = '<option value="">Seçilmedi</option>' . implode('', array_map(fn (array $m): string => '<option value="' . (int) $m['id'] . '"' . ((int) $s['favicon_media_id'] === (int) $m['id'] ? ' selected' : '') . '>' . e($m['original_name']) . '</option>', $media));
    $main = '<header class="dc-toolbar"><div><p class="dc-kicker">STUDIO / SITE</p><h1>Site ayarları</h1></div><a href="/studio">İçeriklere dön</a></header><p class="ck-cluster"><a class="ck-button ck-button--secondary" href="/studio/menu/primary">Ana menü</a><a class="ck-button ck-button--secondary" href="/studio/menu/footer">Footer menüsü</a><a class="ck-button ck-button--secondary" href="/studio/themes">Temalar</a><a class="ck-button ck-button--secondary" href="/studio/plugins">Eklentiler</a><a class="ck-button ck-button--secondary" href="/studio/backups">Yedekler</a></p><form class="dc-form ck-stack" method="post" action="/studio/site"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>Site adı<input required maxlength="120" name="site_name" value="' . e($s['site_name']) . '"></label><label>Açıklama<textarea maxlength="320" name="site_description">' . e($s['site_description']) . '</textarea></label><label>Ana sayfa<select name="home_page_id">' . $pageOptions . '</select></label><label>Logo<select name="logo_media_id">' . $mediaOptions . '</select></label><label>Favicon<select name="favicon_media_id">' . $faviconOptions . '</select></label><label>Vurgu rengi<input required pattern="#[0-9A-Fa-f]{6}" name="theme_accent" value="' . e($s['theme_accent']) . '"></label><label>İçerik genişliği<select name="theme_content_measure"><option value="48rem"' . ($s['theme_content_measure'] === '48rem' ? ' selected' : '') . '>Dar</option><option value="56rem"' . ($s['theme_content_measure'] === '56rem' ? ' selected' : '') . '>Orta</option><option value="68rem"' . ($s['theme_content_measure'] === '68rem' ? ' selected' : '') . '>Geniş</option><option value="76rem"' . ($s['theme_content_measure'] === '76rem' ? ' selected' : '') . '>Çok geniş</option></select></label><label>Sosyal bağlantılar <small>Her satıra bir HTTPS URL.</small><textarea name="social_links">' . e(implode("\n", $s['social_links'])) . '</textarea></label><button class="ck-button" type="submit">Ayarları kaydet</button></form>';
    return Response::html(View::studio('Site ayarları', $main, auth()->user()));
}, cms_protected());

app()->get('/studio/backups', function (Request $request): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
    $files = glob(app()->basePath() . '/storage/backups/dizgecms-*.sqlite') ?: []; rsort($files, SORT_STRING);
    $items = $files === [] ? '<p class="dc-empty">Yedek bulunamadı.</p>' : '<ul>' . implode('', array_map(fn (string $path): string => '<li>' . e(basename($path)) . ' · ' . e((string) filesize($path)) . ' byte</li>', $files)) . '</ul>';
    return Response::html(View::studio('Yedekler', '<header class="dc-toolbar"><h1>Yedekler</h1><a href="/studio/site">Site ayarları</a></header>' . $items . '<p class="dc-help">Geri yükleme yalnızca force-gated CLI ile yapılır: <code>bin/cms-restore … --force</code>.</p>', auth()->user()));
}, cms_protected());

app()->get('/studio/plugins', function (Request $request): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403); $extensions = cms_extensions(); $enabled = $extensions->enabledPluginIds();
    $items = implode('', array_map(fn ($plugin): string => '<li><strong>' . e($plugin->name()) . '</strong> <small>' . e($plugin->version()) . '</small> <form style="display:inline" method="post" action="/studio/plugins/' . e($plugin->id()) . '"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><input type="hidden" name="enabled" value="' . (in_array($plugin->id(), $enabled, true) ? '0' : '1') . '"><button class="ck-button ck-button--secondary">' . (in_array($plugin->id(), $enabled, true) ? 'Devre dışı bırak' : 'Etkinleştir') . '</button></form></li>', $extensions->plugins()));
    return Response::html(View::studio('Eklentiler', '<header class="dc-toolbar"><h1>Eklentiler</h1><a href="/studio/site">Site ayarları</a></header><ul class="ck-stack">' . $items . '</ul>', auth()->user()));
}, cms_protected());

app()->post('/studio/plugins/{id}', function (Request $request, array $params): Response {
    if (!cms_is_admin()) return Response::html('<h1>403</h1><p>Forbidden</p>', 403); try { (string) $request->input('enabled') === '1' ? cms_extensions()->enablePlugin($params['id']) : cms_extensions()->disablePlugin($params['id']); } catch (InvalidArgumentException) { return Response::notFound(); } return Response::redirect('/studio/plugins');
}, cms_protected());

app()->get('/studio/themes', function (Request $request): Response {
    $extensions = cms_extensions(); $active = $extensions->activeThemeId();
    $rows = implode('', array_map(fn ($theme): string => '<li><strong>' . e($theme->name()) . '</strong> <small>' . e($theme->version()) . '</small> <a class="ck-button ck-button--secondary" target="_blank" rel="noopener" href="/studio/themes/' . e($theme->id()) . '/preview">Önizle</a> ' . ($theme->id() === $active ? '<em>Etkin</em>' : '<form style="display:inline" method="post" action="/studio/themes/' . e($theme->id()) . '"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><button class="ck-button ck-button--secondary">Etkinleştir</button></form>') . '</li>', $extensions->themes()));
    return Response::html(View::studio('Temalar', '<header class="dc-toolbar"><h1>Temalar</h1><a href="/studio/site">Site ayarları</a></header><ul class="ck-stack">' . $rows . '</ul>', auth()->user()));
}, cms_protected());

app()->get('/studio/themes/{id}/preview', function (Request $request, array $params): Response {
    $theme = cms_extensions()->themes()[$params['id']] ?? null;
    if ($theme === null) return Response::notFound();
    $main = '<section class="dc-preview-note"><div class="ck-container">Studio tema önizlemesi · <strong>' . e($theme->name()) . '</strong> · Bu görünüm kaydedilmedi.</div></section><section class="dc-section"><div class="ck-container"><article class="dc-prose"><p class="dc-kicker">TEMA ÖNİZLEME</p><h1>İçeriğin görünümü</h1><p>Başlık, paragraf, bağlantı, liste ve genişlik tokenları bu tema ve mevcut site ayarlarıyla birlikte gösterilir.</p><h2>Örnek bölüm</h2><ul><li>Yayınlanmış sayfa</li><li>Makale</li><li>Medya ve navigasyon</li></ul><p><a href="/studio/themes">Studio’ya dön</a></p></article></div></section>';
    return Response::html(View::public('Tema önizleme', 'DizgeCMS Studio tema önizlemesi.', $main, '/studio/themes/' . rawurlencode($params['id']) . '/preview', $theme->id()));
}, cms_protected());

app()->post('/studio/themes/{id}', function (Request $request, array $params): Response {
    try { cms_extensions()->useTheme($params['id']); } catch (InvalidArgumentException) { return Response::notFound(); }
    cms_audit()->write('cms.theme.selected', ['theme_id' => $params['id'], 'actor_id' => (int) auth()->requireUser()->id()]);
    return Response::redirect('/studio/themes');
}, cms_protected());

app()->get('/studio/menu/{location}', function (Request $request, array $params): Response {
    try { $menu = cms_site()->menu($params['location']); } catch (InvalidArgumentException) { return Response::notFound(); }
    $lines = implode("\n", array_map(fn (array $item): string => ($item['parent_id'] === null ? '' : '> ') . $item['label'] . ' | ' . ($item['url'] ?? ''), $menu['items']));
    $main = '<header class="dc-toolbar"><div><p class="dc-kicker">STUDIO / NAVIGATION</p><h1>' . e($menu['location']) . ' menüsü</h1></div><a href="/studio/site">Site ayarları</a></header><form class="dc-form ck-stack" method="post" action="/studio/menu/' . e($menu['location']) . '"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>Menü öğeleri <small>Her satır: Etiket | /yerel-url veya https://ornek.com. Alt öğe için satıra <code>&gt; </code> ile başla; yalnızca bir seviye desteklenir. Alt öğe, ebeveyninin hemen altında olmalıdır.</small><textarea rows="10" name="items">' . e($lines) . '</textarea></label><button class="ck-button" type="submit">Menüyü kaydet</button></form>';
    return Response::html(View::studio('Menü yönetimi', $main, auth()->user()));
}, cms_protected());

app()->post('/studio/menu/{location}', function (Request $request, array $params): Response {
    try {
        $items = [];
        $parent = null;
        foreach (preg_split('/\R/', (string) $request->input('items')) ?: [] as $line) { $line = trim($line); if ($line === '') continue; $child = str_starts_with($line, '>'); if ($child) $line = trim(substr($line, 1)); $parts = array_map('trim', explode('|', $line, 2)); if (count($parts) !== 2) throw new InvalidArgumentException('Her menü satırı Etiket | URL biçiminde olmalı.'); if ($child && $parent === null) throw new InvalidArgumentException('Alt öğenin üstünde bir ebeveyn olmalı.'); $item = ['kind' => 'url', 'label' => $parts[0], 'url' => $parts[1]]; if ($child) $item['parent_index'] = $parent; else $parent = count($items); $items[] = $item; }
        cms_site()->replaceMenu($params['location'], $items);
    } catch (InvalidArgumentException $exception) { return Response::html(View::studio('Menü yönetimi', '<p class="ck-alert ck-alert--error">' . e($exception->getMessage()) . '</p><p><a href="/studio/menu/' . e($params['location']) . '">Geri dön</a></p>', auth()->user()), 422); }
    cms_audit()->write('cms.site.menu_saved', ['location' => $params['location'], 'actor_id' => (int) auth()->requireUser()->id()]);
    return Response::redirect('/studio/menu/' . $params['location']);
}, cms_protected());

app()->post('/studio/site', function (Request $request): Response {
    $links = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $request->input('social_links')) ?: [])));
    try { cms_site()->saveSettings(['site_name' => trim((string) $request->input('site_name')), 'site_description' => trim((string) $request->input('site_description')), 'home_page_id' => $request->input('home_page_id'), 'logo_media_id' => $request->input('logo_media_id'), 'favicon_media_id' => $request->input('favicon_media_id'), 'theme_accent' => (string) $request->input('theme_accent'), 'theme_content_measure' => (string) $request->input('theme_content_measure'), 'social_links' => $links]); }
    catch (InvalidArgumentException $exception) { return Response::html(View::studio('Site ayarları', '<p class="ck-alert ck-alert--error">' . e($exception->getMessage()) . '</p><p><a href="/studio/site">Geri dön</a></p>', auth()->user()), 422); }
    cms_audit()->write('cms.site.settings_saved', ['actor_id' => (int) auth()->requireUser()->id()]);
    return Response::redirect('/studio/site');
}, cms_protected());

app()->get('/studio/content/new', fn (Request $request): Response => Response::html(View::studio('Yeni içerik', cms_editor_form(), auth()->user())), cms_protected());

app()->post('/studio/content', function (Request $request): Response {
    if (!Policy::canCreate(auth()->requireUser())) return Response::html('<h1>403</h1><p>Bu rol içerik oluşturamaz.</p>', 403);
    try {
        $input = cms_content_input($request);
        $id = cms_repo()->create([...$input, 'author_id' => (int) auth()->requireUser()->id()]);
        cms_audit()->write('cms.content.created', ['content_id' => $id, 'actor_id' => (int) auth()->requireUser()->id()]);
        return Response::redirect('/studio/content/' . $id);
    } catch (ValidationException $exception) {
        return Response::html(View::studio('Yeni içerik', cms_editor_form($exception->errors()->all(), ['type' => $request->input('type'), 'title' => $request->input('title'), 'slug' => $request->input('slug'), 'body_html' => $request->input('body_html'), 'meta_description' => $request->input('meta_description')]), auth()->user()), 422);
    } catch (InvalidArgumentException $exception) {
        return Response::html(View::studio('Yeni içerik', cms_editor_form(['body_html' => [$exception->getMessage()]], ['type' => $request->input('type'), 'title' => $request->input('title'), 'slug' => $request->input('slug'), 'body_html' => $request->input('body_html'), 'meta_description' => $request->input('meta_description')]), auth()->user()), 422);
    }
}, cms_protected());

app()->get('/studio/content/{id}', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    if ($content === null) return Response::notFound();
    if (!Policy::canView(auth()->requireUser(), $content)) return Response::html('<h1>403</h1><p>Bu içeriği görüntüleyemezsin.</p>', 403);
    return Response::html(View::studio((string) $content['title'], cms_editor_form([], [], $content) . cms_workflow_panel($content, null, auth()->user()), auth()->user()));
}, cms_protected());

app()->post('/studio/content/{id}', function (Request $request, array $params): Response {
    $id = (int) $params['id'];
    $content = cms_repo()->find($id);
    if ($content === null) return Response::notFound();
    if (!Policy::canEdit(auth()->requireUser(), $content)) return Response::html('<h1>403</h1><p>Bu içeriği düzenleyemezsin.</p>', 403);
    try {
        cms_repo()->update($id, cms_content_input($request, $id), (int) auth()->requireUser()->id());
        cms_audit()->write('cms.content.saved', ['content_id' => $id, 'actor_id' => (int) auth()->requireUser()->id()]);
        return Response::redirect('/studio/content/' . $id);
    } catch (ValidationException $exception) {
        return Response::html(View::studio((string) $content['title'], cms_editor_form($exception->errors()->all(), ['type' => $request->input('type'), 'title' => $request->input('title'), 'slug' => $request->input('slug'), 'body_html' => $request->input('body_html'), 'meta_description' => $request->input('meta_description')], $content), auth()->user()), 422);
    } catch (InvalidArgumentException $exception) {
        return Response::html(View::studio((string) $content['title'], cms_editor_form(['body_html' => [$exception->getMessage()]], ['type' => $request->input('type'), 'title' => $request->input('title'), 'slug' => $request->input('slug'), 'body_html' => $request->input('body_html'), 'meta_description' => $request->input('meta_description')], $content), auth()->user()), 422);
    }
}, cms_protected());

app()->post('/studio/content/{id}/media', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    if ($content === null) return Response::notFound();
    if (!Policy::canUpload(auth()->requireUser()) || !Policy::canEdit(auth()->requireUser(), $content)) return Response::html('<h1>403</h1><p>Bu rol görsel yükleyemez.</p>', 403);
    try {
        $media = cms_media()->store($request->file('image') ?? []);
        $id = cms_repo()->createMedia($media, (int) auth()->requireUser()->id());
        cms_audit()->write('cms.media.uploaded', ['content_id' => (int) $content['id'], 'media_id' => $id, 'actor_id' => (int) auth()->requireUser()->id()]);
        $notice = '<div class="ck-alert ck-alert--success"><strong>Görsel yüklendi.</strong> Editörde Görsel aracına şu URL’yi gir: <code>/media/' . $id . '</code></div>';
        return Response::html(View::studio((string) $content['title'], $notice . cms_editor_form([], [], $content) . cms_workflow_panel($content, null, auth()->user()), auth()->user()));
    } catch (InvalidArgumentException $exception) {
        return Response::html(View::studio((string) $content['title'], cms_editor_form(['image' => [$exception->getMessage()]], [], $content) . cms_workflow_panel($content, null, auth()->user()), auth()->user()), 422);
    }
}, cms_protected());

app()->post('/studio/content/{id}/featured-media', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']); if ($content === null) return Response::notFound();
    try { cms_repo()->setFeaturedMedia((int) $content['id'], $request->input('media_id') === '' ? null : (int) $request->input('media_id')); } catch (InvalidArgumentException $exception) { return Response::html('<h1>422</h1><p>' . e($exception->getMessage()) . '</p>', 422); }
    return Response::redirect('/studio/content/' . (int) $content['id']);
}, cms_protected());

app()->post('/studio/content/{id}/schedule', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']); if ($content === null) return Response::notFound();
    if ((string) $content['status'] !== 'approved') return Response::html('<h1>422</h1><p>Only approved content can be scheduled.</p>', 422);
    $value = trim((string) $request->input('scheduled_at')); $time = strtotime($value . ' UTC');
    if ($time === false || $time <= time()) return Response::html('<h1>422</h1><p>Choose a future UTC time.</p>', 422);
    db()->pdo()->prepare('UPDATE content_items SET scheduled_at = ? WHERE id = ?')->execute([gmdate('c', $time), (int) $content['id']]);
    cms_audit()->write('cms.content.scheduled', ['content_id' => (int) $content['id'], 'actor_id' => (int) auth()->requireUser()->id()]); return Response::redirect('/studio/content/' . (int) $content['id']);
}, cms_protected());

app()->get('/media/{id}', function (Request $request, array $params): Response {
    $media = cms_repo()->media((int) $params['id']);
    if ($media === null) return Response::notFound();
    try { $path = cms_media()->path((string) $media['stored_name']); }
    catch (InvalidArgumentException) { return Response::notFound(); }
    if (!is_file($path)) return Response::notFound();
    $bytes = file_get_contents($path);
    return $bytes === false ? Response::notFound() : Response::raw($bytes, (string) $media['mime'])->withHeader('Cache-Control', 'public, max-age=86400');
});

app()->get('/studio/content/{id}/preview', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    return $content === null ? Response::notFound() : Response::html(View::public((string) $content['title'], 'Studio preview.', '<section class="dc-preview-note"><div class="ck-container">Studio önizlemesi · ' . View::badge($content) . '</div></section><section class="dc-section"><div class="ck-container">' . cms_public_article($content) . '</div></section>'));
}, cms_protected());

app()->post('/studio/content/{id}/preview-link', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    if ($content === null) return Response::notFound();
    try { $issued = cms_preview_tokens()->issue((int) $content['id'], (int) auth()->requireUser()->id()); }
    catch (RuntimeException) { return Response::html('<h1>503</h1><p>Preview signing is not configured.</p>', 503); }
    $url = '/api/v1/preview/' . $issued['token'];
    return Response::html(View::studio('Headless preview bağlantısı', '<section class="dc-section"><div class="ck-container"><h1>Headless preview bağlantısı</h1><p>Bu bağlantı ' . e($issued['expires_at']) . ' zamanına kadar geçerlidir. Yetkili olmayan kişilerle paylaşmayın.</p><p><a class="ck-button" href="' . e($url) . '">Preview bağlantısını aç</a></p><p><code>' . e($url) . '</code></p><p><a href="/studio/content/' . (int) $content['id'] . '">İçeriğe dön</a></p></div></section>', auth()->user()));
}, cms_protected());

app()->post('/studio/content/{id}/transition', function (Request $request, array $params): Response {
    $id = (int) $params['id'];
    $content = cms_repo()->find($id);
    if ($content === null) return Response::notFound();
    $to = (string) $request->input('to_status');
    $note = trim((string) $request->input('note'));
    if (strlen($note) > 1000 || !Policy::canTransition(auth()->requireUser(), (string) $content['status'], $to)) return Response::html(View::studio((string) $content['title'], cms_editor_form([], [], $content) . cms_workflow_panel($content, 'Bu durum geçişi bu rol için desteklenmiyor.', auth()->user()), auth()->user()), 422);
    cms_repo()->transition($id, $to, (int) auth()->requireUser()->id(), $note);
    cms_audit()->write('cms.content.transitioned', ['content_id' => $id, 'actor_id' => (int) auth()->requireUser()->id(), 'from' => (string) $content['status'], 'to' => $to]);
    cms_invalidate_public_cache();
    return Response::redirect('/studio/content/' . $id);
}, cms_protected());

app()->get('/studio/content/{id}/revisions', function (Request $request, array $params): Response {
    $content = cms_repo()->find((int) $params['id']);
    if ($content === null) return Response::notFound();
    $revisions = cms_repo()->revisions((int) $content['id']);
    $events = cms_repo()->events((int) $content['id']);
    $revisionRows = implode('', array_map(function (array $revision) use ($content): string {
        $restore = Policy::canRestore(auth()->requireUser()) ? '<form method="post" action="/studio/content/' . (int) $content['id'] . '/revisions/' . (int) $revision['id'] . '/restore"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><button class="dc-link-button" type="submit">Bu revision’ı taslak olarak geri yükle</button></form>' : '';
        $summary = cms_revision_summary($content, $revision);
        return '<li><strong>Revision #' . (int) $revision['id'] . '</strong> · ' . e((string) $revision['status']) . ' · ' . e((string) $revision['actor_email']) . '<br><small>' . e((string) $revision['created_at']) . '</small><p class="dc-diff">' . $summary . '</p>' . $restore . '</li>';
    }, $revisions));
    $eventRows = implode('', array_map(fn (array $event): string => '<li><strong>' . e((string) $event['event']) . '</strong> · ' . e((string) $event['actor_email']) . ($event['note'] === null ? '' : '<p>' . e((string) $event['note']) . '</p>') . '</li>', $events));
    $main = '<header class="dc-toolbar"><div><p class="dc-kicker">HISTORY</p><h1>' . e((string) $content['title']) . '</h1></div><a href="/studio/content/' . (int) $content['id'] . '">Düzenlemeye dön</a></header><div class="dc-two-column"><section><h2>Revision’lar</h2><ol class="dc-timeline">' . $revisionRows . '</ol></section><section><h2>Akış notları</h2><ol class="dc-timeline">' . $eventRows . '</ol></section></div>';
    return Response::html(View::studio('Revision geçmişi', $main, auth()->user()));
}, cms_protected());

app()->post('/studio/content/{id}/revisions/{revision}/restore', function (Request $request, array $params): Response {
    if (!Policy::canRestore(auth()->requireUser())) return Response::html('<h1>403</h1><p>Revision geri yükleme yalnızca admin içindir.</p>', 403);
    $id = (int) $params['id'];
    cms_repo()->restoreRevision($id, (int) $params['revision'], (int) auth()->requireUser()->id());
    cms_audit()->write('cms.content.restored', ['content_id' => $id, 'actor_id' => (int) auth()->requireUser()->id(), 'revision_id' => (int) $params['revision']]);
    cms_invalidate_public_cache();
    return Response::redirect('/studio/content/' . $id);
}, cms_protected());

/** @param array<string,mixed> $current @param array<string,mixed> $revision */
function cms_revision_summary(array $current, array $revision): string
{
    $changes = [];
    foreach (['title' => 'Başlık', 'slug' => 'Slug', 'status' => 'Durum'] as $key => $label) {
        if ((string) $current[$key] !== (string) $revision[$key]) $changes[] = '<dt>' . $label . '</dt><dd><del>' . e((string) $revision[$key]) . '</del> <ins>' . e((string) $current[$key]) . '</ins></dd>';
    }
    $currentText = trim(preg_replace('/\s+/', ' ', strip_tags(cms_sanitizer()->sanitize((string) $current['body_html']))) ?? '');
    $revisionText = trim(preg_replace('/\s+/', ' ', strip_tags(cms_sanitizer()->sanitize((string) $revision['body_html']))) ?? '');
    if ($currentText !== $revisionText) $changes[] = '<dt>İçerik</dt><dd><del>' . e(mb_strimwidth($revisionText, 0, 120, '…', 'UTF-8')) . '</del><br><ins>' . e(mb_strimwidth($currentText, 0, 120, '…', 'UTF-8')) . '</ins></dd>';
    return $changes === [] ? 'Mevcut içerikle aynı.' : '<dl class="dc-diff-list">' . implode('', $changes) . '</dl>';
}

function cms_workflow_panel(array $content, ?string $error = null, ?Dizge\Security\Identity $identity = null): string
{
    $next = array_values(array_filter(Workflow::next((string) $content['status']), fn (string $status): bool => $identity === null || Policy::canTransition($identity, (string) $content['status'], $status)));
    $buttons = $next === [] ? '<p>Bu içerik için geçiş yok.</p>' : implode('', array_map(fn (string $status): string => '<button class="ck-button ck-button--secondary" type="submit" name="to_status" value="' . e($status) . '">' . e($status) . ' durumuna al</button>', $next));
    $publicUrl = (string) $content['status'] === 'published' ? '/' . ((string) $content['type'] === 'page' ? 'pages/' : 'articles/') . rawurlencode((string) $content['slug']) : '';
    $guidance = $publicUrl === '' ? '<p class="dc-help">Public URL için sıradaki zorunlu adım: <strong>published</strong>. Şu anki durum: ' . e((string) $content['status']) . '.</p>' : '<p class="dc-help">Yayınlandı: <a href="' . e($publicUrl) . '" target="_blank" rel="noopener">' . e($publicUrl) . '</a></p>';
    $schedule = (string) $content['status'] !== 'approved' ? '' : '<form method="post" action="/studio/content/' . (int) $content['id'] . '/schedule"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>UTC yayın zamanı<input required type="datetime-local" name="scheduled_at"></label><button class="ck-button ck-button--secondary">Planla</button></form>';
    return '<section class="dc-workflow"><header><p class="dc-kicker">WORKFLOW</p><h2>Yayın akışı ' . View::badge($content) . '</h2></header>' . $guidance . $schedule . ($error === null ? '' : '<p class="dc-error">' . e($error) . '</p>') . '<form method="post" action="/studio/content/' . (int) $content['id'] . '/transition"><input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>İç review notu<textarea name="note" maxlength="1000" rows="3"></textarea></label><div class="ck-cluster">' . $buttons . '<a href="/studio/content/' . (int) $content['id'] . '/revisions">Revision geçmişi</a></div></form></section>';
}

function cms_login_screen(?string $error = null, string $email = ''): string
{
    $errorHtml = $error === null ? '' : '<div class="ck-alert ck-alert--danger" role="alert">' . e($error) . '</div>';
    $main = '<section class="dc-login"><div class="dc-login-copy"><p class="dc-kicker">DIZGECMS STUDIO</p><h1>İçeriği aceleye değil, akışa bağla.</h1><p>Taslak, inceleme, onay ve yayın adımları; bir SQLite dosyasında açık ve izlenebilir.</p><a href="/demo">Public görünüm</a></div><form class="dc-login-form ck-stack" method="post" action="/studio/login"><h2>Giriş yap</h2>' . $errorHtml . '<input type="hidden" name="_token" value="' . e(csrf_token()) . '"><label>E-posta<input type="email" required name="email" autocomplete="email" value="' . e($email) . '"></label><label>Parola<input type="password" required name="password" autocomplete="current-password"></label><button class="ck-button" type="submit">Studio’ya gir</button></form></section>';
    return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Giriş · DizgeCMS Studio</title><meta name="robots" content="noindex,nofollow"><link rel="icon" href="/assets/dizgecms-mark.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/ckcss.min.css"><link rel="stylesheet" href="/assets/dizgecms.css"></head><body>' . $main . '</body></html>';
}

// The product paths above are canonical. These redirects preserve old reference URLs.
app()->get('/admin', static fn (Request $request): Response => Response::redirect('/studio', 301));
app()->get('/demo/studio', static fn (Request $request): Response => Response::redirect('/studio', 301));
app()->get('/demo/studio/login', static fn (Request $request): Response => Response::redirect('/studio/login', 301));
app()->get('/demo/articles', static fn (Request $request): Response => Response::redirect('/articles', 301));
app()->get('/demo/articles/{slug}', static fn (Request $request, array $params): Response => Response::redirect('/articles/' . rawurlencode($params['slug']), 301));
app()->get('/demo/pages/{slug}', static fn (Request $request, array $params): Response => Response::redirect('/pages/' . rawurlencode($params['slug']), 301));
app()->get('/demo/search', static fn (Request $request): Response => Response::redirect('/search', 301));
app()->get('/demo/sitemap.xml', static fn (Request $request): Response => Response::redirect('/cms-sitemap.xml', 301));
app()->get('/demo/api/context', static fn (Request $request): Response => Response::redirect('/api/context', 301));
