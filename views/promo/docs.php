<?php declare(strict_types=1); $title = 'DizgeCMS dokümantasyonu'; $description = 'DizgeCMS kurulum, Studio, headless API, tema, eklenti ve shared-hosting operasyon rehberleri.'; $active = 'docs'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <header class="dp-section-header">
      <p class="dp-kicker">DÖKÜMANTASYON</p>
      <h2>DizgeCMS’i kur ve yönet</h2>
      <p>Önce ürün rehberleriyle başlayın; aşağıdaki framework referansları geliştirici uzatmaları için korunur.</p>
    </header>
    <div class="ck-grid ck-grid--3"><article class="dp-feature-card"><h3><a href="/install-guide">Kurulum</a></h3><p>Release, checksum, CLI ve web wizard.</p></article><article class="dp-feature-card"><h3><a href="/headless">Headless API</a></h3><p>Studio’dan DevinimJS frontend’e içerik akışı.</p></article><article class="dp-feature-card"><h3><a href="/migrate-wordpress">WordPress geçişi</a></h3><p>WXR import ve güvenli uyumluluk sınırları.</p></article></div>
    <div class="dp-doc-grid" style="max-inline-size:48rem;margin-inline:auto;inline-size:100%">
      <?php foreach ($docs as $doc): ?>
      <article>
        <h3><a href="/docs/<?= e($doc['slug']) ?>"><?= e($doc['title']) ?></a></h3>
        <p><?= e($doc['description']) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
