<?php declare(strict_types=1); $title = 'Shared hosting için açık kaynak CMS'; $description = 'Studio ile yönetin, PHP shared hostingte kurun, DevinimJS ile headless yayınlayın.'; $active = 'home'; ob_start(); ?>
<section class="dp-hero">
  <div class="ck-container ck-stack" style="--ck-stack-space:1.25rem">
    <p class="dp-kicker">DIZGECMS</p>
    <h1>Web siteni kur. <span>İçeriğini yönet.</span> İstersen headless yayınla.</h1>
    <p class="dp-lead">DizgeCMS; Studio, medya kütüphanesi, yayın akışı, REST API ve tema/eklenti altyapısını PHP shared hostingte tek pakette sunar.</p>
    <div class="ck-cluster" style="justify-content:center;--ck-cluster-space:0.75rem">
      <a href="/download" class="ck-button">Release’i indir</a>
      <a href="/install-guide" class="ck-button ck-button--secondary">5 dakikada kur</a>
      <a href="/headless" class="ck-button ck-button--secondary">Headless API</a>
    </div>
    <div class="dp-code-block" style="max-inline-size:42rem;margin-inline:auto">
      <code>tar -xzf dizgecms-0.1.0-rc4.tar.gz
cd dizgecms
DIZGE_ADMIN_PASSWORD='güçlü-parola' php bin/dizge install admin@example.com</code>
    </div>
  </div>
</section>

<section class="dp-section" id="features">
  <div class="ck-container ck-stack" style="--ck-stack-space:2.5rem">
    <header class="dp-section-header">
      <h2>Neden DizgeCMS?</h2>
      <p>Teknik olmayan site sahipleri Studio’dan yönetir; geliştiriciler aynı içeriği REST API ile kendi frontend’lerinde kullanır.</p>
    </header>
    <div class="ck-grid ck-grid--3" style="--ck-grid-gap:1.25rem">
      <div class="dp-feature-card">
        <div class="dp-feature-icon" aria-hidden="true">&#9881;</div>
        <h3>Build-free</h3>
        <p>PHP 8.1+, SQLite ve public/ document root yeterli. Node.js veya zorunlu Composer runtime yok.</p>
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
        <h3>Studio + Headless</h3>
        <p>Sayfa, makale, medya ve menüleri Studio’dan yönetin; aynı veriyi DevinimJS veya başka frontend’de API ile yayınlayın.</p>
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
      <h2>Üç adımda yayına çık</h2>
      <p>Paketi kurun, Studio’dan içeriği oluşturun, klasik tema veya API ile yayınlayın.</p>
    </header>
    <div style="max-inline-size:44rem;margin-inline:auto;inline-size:100%">
      <ol class="dp-step">
        <li>
          <div>
            <strong>Release paketini indir</strong>
            <div class="dp-code-block" style="margin:.5rem 0 0"><code>tar -xzf dizgecms-0.1.0-rc4.tar.gz
cd dizgecms
bash bin/publish-assets</code></div>
          </div>
        </li>
        <li>
          <div>
            <strong>Yönetici hesabını oluştur</strong>
            <div class="dp-code-block" style="margin:.5rem 0 0"><code>DIZGE_ADMIN_PASSWORD='güçlü-parola' \
php bin/dizge install admin@example.com</code></div>
          </div>
        </li>
        <li>
          <div>
            <strong>Studio’dan içeriğini yönet</strong>
            <div class="dp-code-block" style="margin:.5rem 0 0"><code>/studio  # sayfalar, makaleler, medya, menüler
           # taslak, inceleme, onay ve planlı yayın</code></div>
          </div>
        </li>
        <li>
          <div>
            <strong>İstediğin kanalda yayınla</strong>
            <div class="dp-code-block" style="margin:.5rem 0 0"><code>/              # varsayılan tema
/api/v1/pages  # DevinimJS veya başka frontend</code></div>
          </div>
        </li>
      </ol>
    </div>
  </div>
</section>

<section class="dp-section" id="ecosystem">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <header class="dp-section-header">
      <h2>İki yayın biçimi, tek içerik kaynağı</h2>
      <p>DizgeCMS’i yalnızca bir site kurucu olarak kullanabilir veya Studio’yu headless içerik merkezi yapabilirsiniz.</p>
    </header>
    <div class="ck-grid ck-grid--3" style="--ck-grid-gap:1.25rem">
      <div class="dp-feature-card">
        <h3>Klasik CMS</h3>
        <p>Varsayılan tema ile sayfalarınızı hemen yayınlayın. Menü, logo, sosyal bağlar, medya ve SEO ayarları Studio’da kalır.</p>
      </div>
      <div class="dp-feature-card">
        <h3>Headless CMS</h3>
        <p>Versioned REST API; site ayarları, menüler, sayfalar, makaleler, medya ve arama için güvenli public uçlar sağlar.</p>
      </div>
      <div class="dp-feature-card">
        <h3>Geliştirilebilir çekirdek</h3>
        <p>Tema manifestleri ve imzalı eklenti manifestleriyle çekirdeği çatallamadan yeni ekranlar ve içerik davranışları ekleyin.</p>
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
