<?php declare(strict_types=1); $title = 'Eğitimler'; $description = 'Adım adım DizgePHP eğitimleri: ilk uygulama, CRUD kaynak, deployment.'; $active = 'tutorials'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <header class="dp-section-header">
      <p class="dp-kicker">EĞİTİM</p>
      <h2>Adım adım öğren</h2>
      <p>DizgePHP ile sıfırdan production'a. Her adım test edildi, her kod parçası çalışıyor.</p>
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
