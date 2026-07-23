<?php

declare(strict_types=1);

/** @var array{name:string,environment:string,modules:array<string,mixed>} $framework */
?>
<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?></title></head>
<body>
<main><p>DizgePHP web ve API aynı action’ı kullanıyor.</p><h1><?= e($framework['name']) ?></h1><p>Environment: <?= e($framework['environment']) ?></p></main>
</body>
</html>
