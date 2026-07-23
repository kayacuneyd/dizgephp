<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Data\Database;
use Dizge\Data\Migrator;
use Dizge\Http\Request;

function mr_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$dizge = escapeshellarg($root . '/bin/dizge');

// --- Refusing to overwrite an existing generated resource (ProductController.php etc. already
// ship in the repo as this feature's own worked example) mirrors make:controller/make:migration.
exec('php ' . $dizge . ' make:resource Product 2>&1', $repeatOutput, $repeatStatus);
mr_expect($repeatStatus !== 0, 'Regenerating an existing resource must exit non-zero.');
mr_expect(str_contains(implode("\n", $repeatOutput), 'Refusing to overwrite existing file'), 'The refusal message must name the conflicting file.');
mr_expect(is_file($root . '/app/Http/Controllers/ProductController.php'), 'The shipped Product example controller must still exist.');

// --- A fresh `make:resource Widget` scaffolds a controller, migration and views, then the
// generated vertical slice is exercised through a real Application dispatch cycle before the
// throwaway artifacts are removed so the working tree stays clean between test runs.
$controllerPath = $root . '/app/Http/Controllers/WidgetController.php';
$viewIndexPath = $root . '/views/widgets/index.php';
$viewFormPath = $root . '/views/widgets/form.php';

$cleanup = function () use ($root, $controllerPath, $viewIndexPath, $viewFormPath): void {
    if (is_file($controllerPath)) unlink($controllerPath);
    foreach (glob($root . '/database/migrations/*_create_widgets.php') ?: [] as $migration) unlink($migration);
    if (is_file($viewIndexPath)) unlink($viewIndexPath);
    if (is_file($viewFormPath)) unlink($viewFormPath);
    if (is_dir($root . '/views/widgets')) rmdir($root . '/views/widgets');
};
$cleanup(); // In case a previous interrupted run left artifacts behind.

try {
    exec('php ' . $dizge . ' make:resource Widget 2>&1', $createOutput, $createStatus);
    $created = implode("\n", $createOutput);
    mr_expect($createStatus === 0, 'make:resource Widget must exit 0. Output: ' . $created);
    mr_expect(is_file($controllerPath), 'WidgetController.php must be created.');
    $migrations = glob($root . '/database/migrations/*_create_widgets.php') ?: [];
    mr_expect(count($migrations) === 1, 'Exactly one create_widgets migration must be created.');
    mr_expect(is_file($viewIndexPath), 'views/widgets/index.php must be created.');
    mr_expect(is_file($viewFormPath), 'views/widgets/form.php must be created.');

    // Generated PHP must be syntactically valid, not just plausible-looking.
    foreach ([$controllerPath, $migrations[0], $viewIndexPath, $viewFormPath] as $file) {
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $lintOutput, $lintStatus);
        mr_expect($lintStatus === 0, $file . ' must lint cleanly: ' . implode("\n", $lintOutput));
        $lintOutput = [];
    }

    // It must not write routes anywhere; it prints copy-paste snippets instead (ADR-0001).
    mr_expect(str_contains($created, "app()->get('/widgets', [WidgetController::class, 'index']);"), 'The index route snippet must be printed for copy-paste.');
    mr_expect(str_contains($created, "app()->post('/widgets/{id}/delete', [WidgetController::class, 'destroy']);"), 'The destroy route snippet must be printed for copy-paste.');
    mr_expect(str_contains($created, 'app()->container()->singleton(WidgetController::class'), 'The container binding snippet must be printed for copy-paste.');
    foreach (glob($root . '/routes/*.php') ?: [] as $routeFile) {
        mr_expect(!str_contains((string) file_get_contents($routeFile), 'WidgetController'), $routeFile . ' must not have been modified by make:resource.');
    }

    // Every prop the generated views pass to dv() must be one Renderer::COMPONENTS actually
    // allows; this is asserted for real below by rendering the views end-to-end.
    require_once $controllerPath;
    /** @var class-string $controllerClass */
    $controllerClass = 'App\\Http\\Controllers\\WidgetController';
    mr_expect(is_subclass_of($controllerClass, Dizge\Http\Controller::class), 'Generated controller must extend Dizge\\Http\\Controller.');

    $database = new Database(new PDO('sqlite::memory:'));
    (new Migrator($database))->migrate(Migrator::load($root . '/database/migrations'));
    app()->useDatabase($database);
    app()->container()->singleton($controllerClass, fn (Dizge\Container $container): object => new $controllerClass(db()));
    app()->get('/widgets', [$controllerClass, 'index']);
    app()->get('/widgets/create', [$controllerClass, 'create']);
    app()->post('/widgets', [$controllerClass, 'store']);
    app()->get('/widgets/{id}', [$controllerClass, 'show']);
    app()->get('/widgets/{id}/edit', [$controllerClass, 'edit']);
    app()->post('/widgets/{id}', [$controllerClass, 'update']);
    app()->post('/widgets/{id}/delete', [$controllerClass, 'destroy']);

    $token = app()->csrf()->token();

    mr_expect(app()->dispatch(new Request('GET', '/widgets'))->status() === 200, 'Generated index() must render.');
    mr_expect(app()->dispatch(new Request('GET', '/widgets/create'))->status() === 200, 'Generated create() must render.');

    $invalid = app()->dispatch(new Request('POST', '/widgets', [], ['name' => '', '_token' => $token]));
    mr_expect($invalid->status() === 200 && str_contains($invalid->body(), 'required'), 'store() must re-render the form with a validation message on empty input.');

    $stored = app()->dispatch(new Request('POST', '/widgets', [], ['name' => 'Gadget', '_token' => $token]));
    mr_expect($stored->status() === 302 && $stored->header('Location') === '/widgets/1', 'store() must redirect to the new record on success.');

    $shown = app()->dispatch(new Request('GET', '/widgets/1'));
    mr_expect($shown->status() === 200 && str_contains($shown->body(), 'Gadget'), 'show() must render the stored value.');

    mr_expect(app()->dispatch(new Request('GET', '/widgets/999'))->status() === 404, 'show() must 404 for a missing row.');
    mr_expect(app()->dispatch(new Request('GET', '/widgets/1/edit'))->status() === 200, 'edit() must render.');

    $updated = app()->dispatch(new Request('POST', '/widgets/1', [], ['name' => 'Gadget Pro', '_token' => $token]));
    mr_expect($updated->status() === 302 && $updated->header('Location') === '/widgets/1', 'update() must redirect on success.');
    mr_expect(str_contains(app()->dispatch(new Request('GET', '/widgets'))->body(), 'Gadget Pro'), 'index() must reflect the update.');
    mr_expect(app()->dispatch(new Request('POST', '/widgets/999', [], ['name' => 'Nope', '_token' => $token]))->status() === 404, 'update() must 404 for a missing row.');

    $unauthenticatedWrite = app()->dispatch(new Request('POST', '/widgets', [], ['name' => 'No token']));
    mr_expect($unauthenticatedWrite->status() === 419, 'Generated write routes stay behind the global CSRF middleware.');

    $destroyed = app()->dispatch(new Request('POST', '/widgets/1/delete', [], ['_token' => $token]));
    mr_expect($destroyed->status() === 302 && $destroyed->header('Location') === '/widgets', 'destroy() must redirect to the index.');
    mr_expect(app()->dispatch(new Request('GET', '/widgets/1'))->status() === 404, 'destroy() must actually remove the row.');
    mr_expect(app()->dispatch(new Request('POST', '/widgets/1/delete', [], ['_token' => $token]))->status() === 404, 'destroy() must 404 on an already-deleted row.');
} finally {
    $cleanup();
}

echo "DizgePHP make:resource tests passed.\n";
