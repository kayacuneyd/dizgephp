<?php declare(strict_types=1); $title = 'DizgeCMS eğitimleri'; $description = 'DizgeCMS kurulumu, Studio kullanımı, headless yayın ve geliştirici uzatma eğitimleri.'; $active = 'tutorials'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <header class="dp-section-header">
      <p class="dp-kicker">EĞİTİM</p>
      <h2>Adım adım site kur</h2>
      <p>Önce release’i kurun, Studio’dan içerik yönetin, gerektiğinde headless frontend’e geçin.</p>
    </header>
    <div class="dp-doc-grid" style="max-inline-size:48rem;margin-inline:auto;inline-size:100%">
      <?php foreach ($tutorials as $tutorial): ?>
      <article>
        <h3><a href="/tutorials/<?= e($tutorial['slug']) ?>"><?= e($tutorial['title']) ?></a></h3>
        <p><?= e($tutorial['description']) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
