<!doctype html>
<html lang="tr">
  <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?></title></head>
  <body>
    <main>
      <h1><?= e($title) ?></h1>
      <p>AI-first, build-free ve shared-hosting uyumlu PHP uygulama çekirdeği.</p>
      <?= dv('counter', start: 3, step: 1, attrs: ['id' => 'welcome-counter']) ?>
    </main>
    <?= dv_scripts() ?>
  </body>
</html>
