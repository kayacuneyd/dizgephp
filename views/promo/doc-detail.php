<?php declare(strict_types=1); $title = e($doc['title']); $description = e($doc['description']); $active = 'docs'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <nav aria-label="Breadcrumb">
      <ol class="dp-breadcrumb">
        <li><a href="/docs">Dokümantasyon</a></li>
        <li><?= e($doc['title']) ?></li>
      </ol>
    </nav>
    <article class="dp-prose">
      <h1><?= e($doc['title']) ?></h1>
      <?= $doc['body']() ?>
    </article>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
