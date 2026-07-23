<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Data\Database;
use Dizge\Data\Migration;
use Dizge\Data\Migrator;

function data_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database = new Database(new PDO('sqlite::memory:'));
$database->pdo()->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, price INTEGER NOT NULL, deleted_at TEXT NULL)');

$database->transaction(function (Database $db): void {
    $db->table('products')->insert(['name' => 'Keyboard', 'price' => 99]);
    $db->table('products')->insert(['name' => 'Mouse', 'price' => 49]);
});

$products = $database->table('products')->select(['id', 'name', 'price'])->where('price', '>=', 49)->orderBy('price', 'DESC')->get();
data_expect(array_column($products, 'name') === ['Keyboard', 'Mouse'], 'Parameterized select and deterministic ordering must work.');
data_expect($database->table('products')->where('name', '=', 'Mouse')->update(['price' => 59]) === 1, 'Guarded update must change matching rows.');
data_expect($database->table('products')->where('name', '=', 'Mouse')->first()['price'] === 59, 'first() must return the updated row.');

foreach ([
    fn () => $database->table('products')->orderBy('price', 'DROP TABLE'),
    fn () => $database->table('products; DROP TABLE products')->get(),
    fn () => $database->table('products')->update(['price' => 1]),
    fn () => $database->table('products')->delete(),
] as $unsafeOperation) {
    try {
        $unsafeOperation();
        throw new RuntimeException('Unsafe data operation must be rejected.');
    } catch (InvalidArgumentException|LogicException) {
    }
}

$migration = new class implements Migration {
    public function id(): string { return '20260722_create_notes'; }
    public function up(Database $database): void { $database->pdo()->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT NOT NULL)'); }
    public function down(Database $database): void { $database->pdo()->exec('DROP TABLE notes'); }
};
$migrator = new Migrator($database);
data_expect($migrator->migrate([$migration]) === ['20260722_create_notes'], 'Migration must be recorded once.');
data_expect($migrator->migrate([$migration]) === [], 'Applied migrations must not run twice.');
data_expect($migrator->rollback([$migration]) === ['20260722_create_notes'], 'Latest migration batch must roll back in reverse order.');

echo "DizgePHP data tests passed.\n";
