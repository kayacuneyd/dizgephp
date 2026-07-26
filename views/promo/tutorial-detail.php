<?php declare(strict_types=1); $title = e($tutorial['title']); $description = e($tutorial['description']); $active = 'tutorials'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <nav aria-label="Breadcrumb">
      <ol class="dp-breadcrumb">
        <li><a href="/tutorials">Eğitimler</a></li>
        <li><?= e($tutorial['title']) ?></li>
      </ol>
    </nav>
    <article class="dp-prose">
      <h1><?= e($tutorial['title']) ?></h1>
      <?= $tutorial['body']() ?>
    </article>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
