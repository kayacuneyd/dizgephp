<?php

declare(strict_types=1);

return [
    'slug' => 'routing',
    'title' => 'Rotalama',
    'description' => 'DizgePHP\'te rotalar nasıl tanımlanır, parametreler nasıl yakalanır.',
    'body' => function (): string { ob_start(); ?>

    <p>Rotalar <strong>açıkça</strong> <code>routes/web.php</code> (HTML) veya <code>routes/api.php</code> (JSON) dosyasında tanımlanır. İlk eşleşen rota kazanır.</p>

    <h2>Basit closure rotası</h2>

    <pre><code>app()-&gt;get('/hello/{name}', function (Request $request, array $params): Response {
    return Response::html('&lt;p&gt;Merhaba, ' . e($params['name']) . '!&lt;/p&gt;');
});</code></pre>

    <h2>Controller rotası</h2>

    <p>İki öğeli bir dizi: <code>[Sınıf::class, 'metod']</code>.</p>

    <pre><code>app()-&gt;get('/projects', [ProjectController::class, 'index']);
app()-&gt;get('/projects/{id}', [ProjectController::class, 'show']);
app()-&gt;post('/projects', [ProjectController::class, 'store']);</code></pre>

    <h2>HTTP metodları</h2>

    <p><code>app()-&gt;get()</code>, <code>app()-&gt;post()</code>, <code>app()-&gt;put()</code>, <code>app()-&gt;delete()</code> kullanılabilir. <code>get()</code> otomatik olarak HEAD isteklerini de karşılar.</p>

    <h2>Parametreler</h2>

    <p>Süslü parantez <code>{param}</code> ile yakalanır ve handler'a <code>$params</code> dizisi olarak geçilir:</p>

    <pre><code>app()-&gt;get('/articles/{slug}', function (Request $request, array $params): Response {
    $article = db()-&gt;table('articles')-&gt;where('slug', $params['slug'])-&gt;first();
    return $article === null ? Response::notFound() : view('article.php', $article);
});</code></pre>

    <h2>Middleware</h2>

    <p>Üçüncü parametre olarak middleware dizisi geçilebilir:</p>

    <pre><code>app()-&gt;post('/admin/settings', $handler, [new RequireAuth(auth())]);</code></pre>

    <p>State-changing rotalara (POST, PUT, DELETE) CSRF middleware'i otomatik eklenir.</p>

    <h2>Controller üretmek</h2>

    <pre><code>bin/dizge make:controller Project</code></pre>

    <p>Üretilen controller <code>app/Http/Controllers/ProjectController.php</code> dosyasına yazılır. Container binding'ini <code>routes/services.php</code> dosyasına elle eklemelisiniz.</p>

    <p><a href="/docs/views" class="ck-button">Görünümler ve Şablonlar →</a></p>
<?php return ob_get_clean(); }];
