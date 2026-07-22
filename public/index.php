<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';
require dirname(__DIR__) . '/routes/services.php';
require dirname(__DIR__) . '/routes/web.php';
require dirname(__DIR__) . '/routes/api.php';
require dirname(__DIR__) . '/routes/demo.php';

app()->run();
