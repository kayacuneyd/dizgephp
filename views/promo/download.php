<?php declare(strict_types=1); $title = 'İndir'; $description = 'DizgePHP\'i indirin: Git clone, GitHub Release ZIP veya doğrudan indirme.'; $active = 'download'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:2.5rem">
    <header class="dp-section-header">
      <p class="dp-kicker">İNDİR</p>
      <h2>DizgePHP'i edin</h2>
      <p>Composer, Node.js veya build adımı gerekmez. Tek ihtiyacınız PHP 8.1+.</p>
    </header>

    <div class="ck-grid ck-grid--2" style="--ck-grid-gap:1.5rem;max-inline-size:52rem;margin-inline:auto;inline-size:100%">
      <div class="dp-feature-card ck-stack" style="--ck-stack-space:1rem">
        <h3>Git ile klonla</h3>
        <p style="color:var(--ck-color-text-muted);font-size:.95rem">Belirli bir sürümü pin'leyerek güvenle kurun.</p>
        <div class="dp-code-block"><code>git clone --branch v0.1.0 --depth 1 \
  https://github.com/kayacuneyd/dizgephp.git \
  my-app</code></div>
      </div>
      <div class="dp-feature-card ck-stack" style="--ck-stack-space:1rem">
        <h3>ZIP olarak indir</h3>
        <p style="color:var(--ck-color-text-muted);font-size:.95rem">GitHub Releases sayfasından en son sürümü indirin.</p>
        <a href="https://github.com/kayacuneyd/dizgephp/releases" class="ck-button" style="align-self:start">GitHub Releases →</a>
      </div>
    </div>

    <div style="max-inline-size:44rem;margin-inline:auto;inline-size:100%">
      <h3 style="font-family:var(--ck-font-display);font-size:1.5rem;margin:0 0 1rem">Gereksinimler</h3>
      <table class="ck-table-wrap">
        <table class="ck-table">
          <thead><tr><th>Gereksinim</th><th>Detay</th></tr></thead>
          <tbody>
            <tr><td>PHP</td><td>8.1 veya üstü</td></tr>
            <tr><td>PDO SQLite</td><td>Varsayılan veritabanı için</td></tr>
            <tr><td>Apache / Nginx</td><td>Document root <code>public/</code> olarak ayarlanmalı</td></tr>
            <tr><td>Disk</td><td>~2 MB (framework) + uygulama dosyalarınız</td></tr>
          </tbody>
        </table>
      </table>
    </div>

    <div style="max-inline-size:44rem;margin-inline:auto;inline-size:100%">
      <h3 style="font-family:var(--ck-font-display);font-size:1.5rem;margin:0 0 1rem">30 saniyede çalıştır</h3>
      <div class="dp-code-block"><code>cd my-app
cp .env.example .env
php -S localhost:8080 -t public public/index.php
# http://localhost:8080</code></div>
      <p style="color:var(--ck-color-text-muted);font-size:.9rem;margin:.75rem 0 0">PHP'nin built-in sunucusu geliştirme için yeterlidir. Production'da Apache veya Nginx kullanın.</p>
    </div>

    <div style="max-inline-size:44rem;margin-inline:auto;inline-size:100%">
      <h3 style="font-family:var(--ck-font-display);font-size:1.5rem;margin:0 0 1rem">Doğrula</h3>
      <div class="dp-code-block"><code>bin/dizge verify</code></div>
      <p style="color:var(--ck-color-text-muted);font-size:.9rem;margin:.75rem 0 0">Tüm .php dosyalarını lint'ler ve test suite'i çalıştırır. Her şey yeşilse hazırsınız.</p>
    </div>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
