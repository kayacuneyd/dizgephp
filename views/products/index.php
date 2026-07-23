<?php
/** @var list<array<string,mixed>> $rows */
?>
<!doctype html>
<html lang="en">
  <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?></title></head>
  <body>
    <main>
      <h1><?= e($title) ?></h1>
      <p><a href="/products/create">New Product</a></p>
      <?= dv('data-table', columns: ['id', 'name', 'created_at'], rows: $rows, label: $title) ?>
      <ul>
        <?php foreach ($rows as $row): ?>
          <li>
            <a href="/products/<?= e((string) $row['id']) ?>/edit">Edit #<?= e((string) $row['id']) ?></a>
            <form method="post" action="/products/<?= e((string) $row['id']) ?>/delete" style="display:inline">
              <?= csrf_field() ?>
              <button type="submit">Delete</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </main>
    <?= dv_scripts() ?>
  </body>
</html>
