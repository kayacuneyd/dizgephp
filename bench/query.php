<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Data\Database;

$database = new Database(new PDO('sqlite::memory:'));
$database->pdo()->exec('CREATE TABLE measurements (id INTEGER PRIMARY KEY AUTOINCREMENT, value INTEGER NOT NULL)');
$database->transaction(function (Database $db): void {
    for ($i = 0; $i < 100; $i++) $db->table('measurements')->insert(['value' => $i]);
});

$iterations = 1000;
$started = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $database->table('measurements')->where('value', '>=', 50)->orderBy('value', 'DESC')->limit(10)->get();
}
$elapsedMilliseconds = (hrtime(true) - $started) / 1_000_000;
printf("query: %d selects in %.2f ms (%.4f ms/query)\n", $iterations, $elapsedMilliseconds, $elapsedMilliseconds / $iterations);
