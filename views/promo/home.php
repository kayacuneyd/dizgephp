<?php declare(strict_types=1); $title = 'DizgeCMS'; $description = 'Hızlı, güvenli, plugin-ready PHP CMS. SEO ve GEO için yapılandırılmış yayın altyapısı.'; $active = 'home'; ob_start(); ?>
<section class="dp-hero">
  <div class="ck-container ck-stack" style="--ck-stack-space:1.25rem">
    <p class="dp-kicker">DIZGECMS</p>
    <h1>Yayıncılığı <span>build-free</span> düşün.</h1>
    <p class="dp-lead">Hızlı, güvenli ve plugin-ready PHP CMS. SEO, GEO ve editorial workflow tek bir shared-hosting uyumlu full-stack platformda.</p>
    <div class="ck-cluster" style="justify-content:center;--ck-cluster-space:0.75rem">
      <a href="/docs" class="ck-button">Dokümantasyon</a>
      <a href="/download" class="ck-button ck-button--secondary">İndir</a>
    </div>
    <div class="dp-code-block" style="max-inline-size:42rem;margin-inline:auto">
      <code>git clone --branch v0.1.0 --depth 1 https://github.com/kayacuneyd/dizgephp.git my-app
cd my-app && cp .env.example .env
php -S localhost:8080 -t public public/index.php</code>
    </div>
  </div>
</section>

<section class="dp-section" id="features">
  <div class="ck-container ck-stack" style="--ck-stack-space:2.5rem">
    <header class="dp-section-header">
      <h2>Neden DizgeCMS?</h2>
      <p>WordPress’in yayın kolaylığını daha küçük, hızlı ve kontrollü bir PHP çekirdeğiyle yeniden düşünür.</p>
    </header>
    <div class="ck-grid ck-grid--3" style="--ck-grid-gap:1.25rem">
      <div class="dp-feature-card">
        <div class="dp-feature-icon" aria-hidden="true">&#9881;</div>
        <h3>Build-free</h3>
        <p>Composer, Node.js, Webpack yok. Bir Git clone veya ZIP indir — framework anında çalışır. CI süreniz saniyelerle ölçülür.</p>
      </div>
      <div class="dp-feature-card">
        <div class="dp-feature-icon" aria-hidden="true">&#9733;</div>
        <h3>SEO + GEO hazır</h3>
        <p>Canonical, structured data, kaynak ilişkileri ve makine tarafından anlaşılabilir içerik bağlamı çekirdekte.</p>
      </div>
      <div class="dp-feature-card">
        <div class="dp-feature-icon" aria-hidden="true">&#9729;</div>
        <h3>Shared-hosting</h3>
        <p>cPanel, Apache, SQLite. Karmaşık DevOps pipeline'ı yok. PHP 8.1+ olan her yerde çalışır.</p>
      </div>
      <div class="dp-feature-card">
        <div class="dp-feature-icon" aria-hidden="true">&#9878;</div>
        <h3>Önce güvenlik</h3>
        <p>CSRF otomatik, yetkilendirme deny-by-default, query builder parametrize. Her modülün threat model'i yazılı.</p>
      </div>
      <div class="dp-feature-card">
        <div class="dp-feature-icon" aria-hidden="true">&#9883;</div>
        <h3>DevinimJS Bridge</h3>
        <p>Sunucu authoritative, client optimistic. Custom element'ler PHP'den render edilir; bridge ile state düzeltilir.</p>
      </div>
      <div class="dp-feature-card">
        <div class="dp-feature-icon" aria-hidden="true">&#9986;</div>
        <h3>Plugin + tema</h3>
        <p>Çekirdek sabit kalır; içerik türleri, admin ekranları, SEO sağlayıcıları ve görsel katman plugin/tema ile genişler.</p>
      </div>
    </div>
  </div>
</section>

<section class="dp-section dp-section--alt" id="quickstart">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <header class="dp-section-header">
      <h2>30 saniyede başla</h2>
      <p>İlk rotandan CRUD kaynağına — hepsi terminalde.</p>
    </header>
    <div style="max-inline-size:44rem;margin-inline:auto;inline-size:100%">
      <ol class="dp-step">
        <li>
          <div>
            <strong>Depoyu klonla</strong>
            <div class="dp-code-block" style="margin:.5rem 0 0"><code>git clone --branch v0.1.0 --depth 1 https://github.com/kayacuneyd/dizgephp.git</code></div>
          </div>
        </li>
        <li>
          <div>
            <strong>İlk rotanı yaz</strong>
            <div class="dp-code-block" style="margin:.5rem 0 0"><code>app()->get('/hello/{name}', fn ($req, $p) => Response::html('Merhaba, ' . e($p['name'])));</code></div>
          </div>
        </li>
        <li>
          <div>
            <strong>CRUD iskelesi kur</strong>
            <div class="dp-code-block" style="margin:.5rem 0 0"><code>bin/dizge make:resource Product   # controller + migration + views
bin/dizge migrate up</code></div>
          </div>
        </li>
        <li>
          <div>
            <strong>Doğrula ve yayınla</strong>
            <div class="dp-code-block" style="margin:.5rem 0 0"><code>bin/dizge verify       # lint + test
# hosting'e yükle, .env yapılandır, çalışıyor</code></div>
          </div>
        </li>
      </ol>
    </div>
  </div>
</section>

<section class="dp-section" id="ecosystem">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <header class="dp-section-header">
      <h2>Üçlü ekosistem</h2>
      <p>DizgePHP HTTP ve güvenliği, CKCSS sunumu, DevinimJS tarayıcıyı yönetir. Her biri kendi alanında uzman.</p>
    </header>
    <div class="ck-grid ck-grid--3" style="--ck-grid-gap:1.25rem">
      <div class="dp-feature-card">
        <h3>DizgePHP</h3>
        <p>Rotalama, controller, validation, veritabanı, oturum, CSRF, yetkilendirme, job queue, mail, API token. Hepsi tek bir PHP checkout'unda.</p>
      </div>
      <div class="dp-feature-card">
        <h3>CKCSS</h3>
        <p>İznik çinisi renk paleti, Aleo tipografi, karanlık mod, responsive layout. CSS değişkenleri ile temalandırılabilir, build-free.</p>
      </div>
      <div class="dp-feature-card">
        <h3>DevinimJS</h3>
        <p>Custom element'ler, form doğrulama, veri tablosu, rich text editör. Build-free, vanilla JS class API.</p>
      </div>
    </div>
  </div>
</section>

<section class="dp-section dp-section--alt" style="text-align:center">
  <div class="ck-container ck-stack" style="--ck-stack-space:1.5rem">
    <h2 style="font-family:var(--ck-font-display);font-size:clamp(2rem,4vw,3rem);line-height:var(--ck-line-heading)">Hazır mısın?</h2>
    <p style="color:var(--ck-color-text-muted);max-inline-size:44ch;margin-inline:auto">Tek ihtiyacın PHP 8.1+. Ne Composer, ne Node.js, ne build pipeline.</p>
    <div class="ck-cluster" style="justify-content:center">
      <a href="/docs" class="ck-button">Dokümanı oku</a>
      <a href="/download" class="ck-button ck-button--secondary">İndir</a>
    </div>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
