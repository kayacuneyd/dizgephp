<?php
/** @var array<string,mixed>|null $row @var array<string,list<string>> $errors @var bool $readonly */
$row ??= [];
$errors ??= [];
$readonly ??= false;
$isNew = !isset($row['id']);
$action = $isNew ? '/products' : '/products/' . (int) $row['id'];
?>
<!doctype html>
<html lang="en">
  <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?></title></head>
  <body>
    <main>
      <h1><?= e($title) ?></h1>
      <?php if ($errors !== []): ?>
        <ul>
          <?php foreach ($errors as $field => $messages): foreach ((array) $messages as $message): ?>
            <li><?= e((string) $field) ?>: <?= e((string) $message) ?></li>
          <?php endforeach; endforeach; ?>
        </ul>
      <?php endif; ?>
      <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <?= dv('field', id: 'name', name: 'name', label: 'Name', control: 'input', type: 'text', value: (string) ($row['name'] ?? ''), placeholder: 'Name', required: true, disabled: $readonly, error: implode(' ', $errors['name'] ?? [])) ?>
        <?php if (!$readonly): ?><button type="submit"><?= $isNew ? 'Create' : 'Save' ?></button><?php endif; ?>
      </form>
      <?php if (!$isNew && !$readonly): ?>
        <form method="post" action="/products/<?= (int) $row['id'] ?>/delete">
          <?= csrf_field() ?>
          <button type="submit">Delete</button>
        </form>
      <?php endif; ?>
      <p><a href="/products">Back to products</a></p>
    </main>
    <?= dv_scripts() ?>
  </body>
</html>
