<?php

declare(strict_types=1);

return [
    'slug' => 'security',
    'title' => 'Güvenlik',
    'description' => 'CSRF, oturum yönetimi, yetkilendirme (Gate) ve güvenlik temelleri.',
    'body' => function (): string { ob_start(); ?>

    <h2>CSRF koruması</h2>

    <p>State-changing (POST, PUT, DELETE) rotalara CSRF doğrulaması <strong>otomatik</strong> eklenir. Formlarınızda <code>csrf_field()</code> kullanmanız yeterlidir:</p>

    <pre><code>&lt;form method="post" action="/settings"&gt;
  &lt;?= csrf_field() ?&gt;
  &lt;button type="submit"&gt;Kaydet&lt;/button&gt;
&lt;/form&gt;</code></pre>

    <h2>Oturum ve kimlik doğrulama</h2>

    <pre><code>app()-&gt;post('/articles/{id}', $handler, [new RequireAuth(auth())]);</code></pre>

    <p><code>RequireAuth</code> middleware'i, oturum açmamış kullanıcıları belirttiğiniz login URL'sine yönlendirir.</p>

    <h2>Yetkilendirme (Gate)</h2>

    <p>Yetkilendirme kuralları <code>gate()-&gt;define()</code> ile tanımlanır. Tanımlanmamış bir yetki <strong>her zaman reddedilir</strong>:</p>

    <pre><code>gate()-&gt;define('article.update', fn (Identity $user, array $article) =&gt;
    $user-&gt;id() === $article['owner_id']
);</code></pre>

    <p>Rota seviyesinde kontrol:</p>

    <pre><code>app()-&gt;post('/articles/{id}', [ArticleController::class, 'update'], [
    new RequireAuth(auth()),
    new RequireAbility(auth(), gate(), 'article.update'),
]);</code></pre>

    <h2>Veritabanı güvenliği</h2>

    <pre><code>$rows = db()-&gt;table('products')
    -&gt;where('price', '&gt;=', 50)
    -&gt;orderBy('price', 'DESC')
    -&gt;get();</code></pre>

    <p>Query builder <strong>tüm değerleri parametrize eder</strong>. Sütun ve tablo isimleri allowlist ile doğrulanır. Ham SQL escape kapısı yoktur.</p>

    <h2>Güvenlik başlıkları</h2>

    <p><code>public/.htaccess</code> otomatik olarak şu başlıkları ekler:</p>
    <ul>
      <li><code>X-Content-Type-Options: nosniff</code></li>
      <li><code>X-Frame-Options: SAMEORIGIN</code></li>
      <li><code>Referrer-Policy: strict-origin-when-cross-origin</code></li>
    </ul>

    <p><a href="/docs/database" class="ck-button">Veritabanı →</a></p>
<?php return ob_get_clean(); }];
