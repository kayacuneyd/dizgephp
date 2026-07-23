<?php declare(strict_types=1); $title = 'Dokümantasyon'; $description = 'DizgePHP framework dokümantasyonu: routing, views, güvenlik, veritabanı, CLI ve deployment.'; $active = 'docs'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <header class="dp-section-header">
      <p class="dp-kicker">DÖKÜMANTASYON</p>
      <h2>Framework'ü keşfet</h2>
      <p>DizgePHP'in her katmanı için baştan yazılmış referans dokümanlar. Routing'den deployment'a.</p>
    </header>
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
