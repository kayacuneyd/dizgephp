# Your first DizgePHP app

This is a guided, step-by-step walkthrough — from `git clone` to a small working feature — for
someone who has never touched DizgePHP before. Unlike the rest of `docs/`, which is reference
material (architecture, ADRs, threat models), this document is a tutorial: every command and code
snippet below was actually executed while writing it, against a disposable local copy of this
repository and a throwaway SQLite database, so what you read here is proven to work rather than
recalled from memory. See `docs/getting-started.md` for the condensed reference version of the same
material, and `docs/architecture.md` for how the pieces fit together.

## 0. What you need

- PHP 8.1+ with the PDO SQLite extension (the default database driver).
- No Composer, no Node.js, no build step. DizgePHP is deliberately zero-build.

## 1. Clone and configure

```bash
git clone --branch v0.1.0 --depth 1 https://github.com/kayacuneyd/dizgephp.git my-app
cd my-app
cp .env.example .env
```

`.env` is git-ignored; production values (or a `DIZGE_DB_DSN` pointing at MySQL/Postgres instead of
the SQLite default) belong there or in your hosting environment, never in source control.

## 2. Run the dev server and check the gate

PHP ships a built-in development server — no Apache/Nginx needed while you're learning:

```bash
php -S localhost:8391 -t public public/index.php
```

(Port `8391` here is arbitrary — pick anything free on your machine; `8080`, used in the README, is
just as fine on a machine that isn't already using it.) In another terminal:

```bash
curl -s http://localhost:8391/
```

This returns the shipped homepage — server-rendered HTML with a `dv-counter` DevinimJS component
already on it. Before changing anything, run the same verification gate the project itself uses
before every release:

```bash
bin/dizge verify
```

On a freshly cloned checkout this lints every `.php` file and runs the full `tests/*.php` suite —
you should see eleven `... tests passed.` lines and exit code 0. Keep this command in your back
pocket: run it again after any change, including the ones below.

## 3. Add a route

Routes are explicit and live in `routes/web.php` — there is no file-based or annotation routing.
Try adding this to the bottom of that file (a closure route, the simplest form):

```php
app()->get('/hello/{name}', function (Request $request, array $params): Dizge\Http\Response {
    return Dizge\Http\Response::html('<p>Hello, ' . e($params['name']) . '!</p>');
});
```

Restart nothing — `php -S` re-reads the file on every request. Then:

```bash
curl -s http://localhost:8391/hello/Ada
# <p>Hello, Ada!</p>

curl -s http://localhost:8391/hello/%3Cscript%3E
# <p>Hello, &lt;script&gt;!</p>
```

The second call shows why `e()` is mandatory around any value that reaches HTML: route parameters
are attacker-controlled input, and `e()` (UTF-8, `ENT_QUOTES`, `ENT_SUBSTITUTE`) neutralizes it
before it reaches the browser. Route handlers always return a `Dizge\Http\Response` — nothing in a
route handler echoes directly.

## 4. Scaffold a controller

For anything bigger than a one-line closure, generate a controller instead of hand-writing
boilerplate:

```bash
bin/dizge make:controller Task
# Created app/Http/Controllers/TaskController.php
```

> **Known rough edge (as of this writing):** the freshly generated stub's `index()` method
> signature currently ships with a template-escaping bug in `bin/dizge` itself (a doubled
> backslash in the heredoc turns `$request`/`$params` into undefined-variable warnings and the
> file fails `php -l`). You're about to replace the whole method body with real logic anyway, so
> it doesn't block you — just don't `git diff` the raw stub and expect it to run as-is. Worth
> reporting upstream if it's still there when you read this.

Open `app/Http/Controllers/TaskController.php` and replace its contents with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Dizge\Http\Controller;
use Dizge\Http\Request;
use Dizge\Http\Response;

final class TaskController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $tasks = db()->table('tasks')->orderBy('id', 'DESC')->get();

        $rows = array_map(
            static fn (array $task): array => [(string) $task['id'], (string) $task['title'], $task['done'] ? 'Yes' : 'No'],
            $tasks,
        );

        return view('tasks/index.php', ['rows' => $rows]);
    }
}
```

Controllers are **explicit container bindings** — DizgePHP does not reflect or autowire constructor
arguments (see `docs/architecture.md`). Register the controller in `routes/services.php`, next to
the existing `FrameworkController` binding:

```php
use App\Http\Controllers\TaskController;
// …
app()->container()->singleton(TaskController::class, fn (Dizge\Container $container): TaskController => new TaskController());
```

Skip this and DizgePHP fails fast and loudly: `LogicException: No explicit binding exists for
"App\Http\Controllers\TaskController"` — that's the framework refusing to guess, not a bug. (If
you only see a bare `<h1>500</h1>` in the browser, that's `DIZGE_ENV=production` hiding the detail;
check the terminal running `php -S` — exceptions are logged there via `error_log()` — or set
`DIZGE_DEBUG=1` in `.env` for local work to see the message inline.)

Then wire the route in `routes/web.php`:

```php
use App\Http\Controllers\TaskController;
// …
app()->get('/tasks', [TaskController::class, 'index']);
```

## 5. Scaffold and run a migration

```bash
bin/dizge make:migration create_tasks_table
# Created database/migrations/202607231236_create_tasks_table.php
```

(Your filename's timestamp prefix will differ — it's `gmdate('YmdHi')` at the moment you run the
command.) The same heredoc-escaping rough edge from step 4 applies to the generated `up()`/`down()`
signatures — open the file and replace the whole body with:

```php
<?php

declare(strict_types=1);

use Dizge\Data\Database;
use Dizge\Data\Migration;

return new class implements Migration {
    public function id(): string { return '202607231236_create_tasks_table'; } // keep the generated id() line as-is

    public function up(Database $database): void
    {
        $database->pdo()->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, done INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)');
    }

    public function down(Database $database): void
    {
        $database->pdo()->exec('DROP TABLE IF EXISTS tasks');
    }
};
```

Now run it:

```bash
bin/dizge migrate up
```

With no `DIZGE_DB_DSN` set, DizgePHP defaults to a local `storage/app.sqlite` — created on first
use, and already excluded by `.gitignore`, so it's disposable dev data by default. This applies
every pending migration in the directory (the shipped CMS-demo migrations plus your new one) and
prints each migration's id, including `..._create_tasks_table`. Confirm the route is registered:

```bash
bin/dizge route:list
```

`GET /tasks` should be in the list.

## 6. Query it with `db()->table()`

Seed a couple of rows from the command line — `dizge.php` boots the application as soon as it's
required, so a one-off script needs nothing else:

```bash
php -r "
require 'dizge.php';
db()->table('tasks')->insert(['title' => 'Write the tutorial', 'done' => 0, 'created_at' => date('c')]);
db()->table('tasks')->insert(['title' => 'Ship it & relax', 'done' => 1, 'created_at' => date('c')]);
echo count(db()->table('tasks')->get()) . \" task(s) in the tasks table.\n\";
"
```

`db()->table('tasks')` returns a small query builder — `where()`, `orderBy()`, `limit()`,
`insert()`, `update()`, `delete()` — that parameterizes every value and allowlists identifiers; see
`docs/data-threat-model.md`. `TaskController::index()` above already used it:

```php
$tasks = db()->table('tasks')->orderBy('id', 'DESC')->get();
```

## 7. Render a `dv()` component from a view

Create `views/tasks/index.php`:

```php
<!doctype html>
<html lang="en">
  <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tasks</title></head>
  <body>
    <main>
      <h1>Tasks</h1>
      <?= dv('data-table', columns: ['ID', 'Title', 'Done'], rows: $rows, label: 'Tasks') ?>
    </main>
    <?= dv_scripts() ?>
  </body>
</html>
```

Now load it:

```bash
curl -s http://localhost:8391/tasks
```

```html
<dv-data-table data-columns="[&quot;ID&quot;,&quot;Title&quot;,&quot;Done&quot;]" data-rows="[[&quot;2&quot;,&quot;Ship it &amp; relax&quot;,&quot;Yes&quot;],[&quot;1&quot;,&quot;Write the tutorial&quot;,&quot;No&quot;]]" data-label="Tasks"></dv-data-table>
<script type="module" src="/assets/devinim/modules/dv-data-table.js"></script>
```

Note the `&amp;` — the ampersand in "Ship it & relax" was escaped automatically. `dv()` validates
the component name and its props against an allowlist and escapes every value itself (see
`docs/security-baseline.md`); **don't** call `e()` yourself on values you pass into `dv()` props —
doing so would double-escape them. `e()` is for markup you write directly in a view, like the
`<h1>Tasks</h1>` above. `dv_scripts()` emits one `<script type="module">` tag per distinct component
used on the page, deduplicated.

## 8. Confirm the gate again

```bash
bin/dizge verify
```

Straight after cloning (step 2) this passed cleanly. Having added
`database/migrations/202607231236_create_tasks_table.php`, running it again right now will
actually fail:

```
PHP Fatal error:  Uncaught RuntimeException: CMS and official-module migrations must apply
to an empty SQLite database in order. in tests/cms_test.php:15
```

That's not something you did wrong: `tests/cms_test.php` currently asserts the *exact* list of
migration ids it expects to find under `database/migrations/` (the three shipped with the
DizgeCMS Studio demo and API-token module), so any application migration added alongside them —
exactly what step 5 just did, and exactly what the README's own `bin/dizge make:migration` example
invites you to do — trips that assertion. It's a real, reproducible interaction worth flagging to
the maintainers rather than a mistake in these steps; moving your migration file out of
`database/migrations/` before running `bin/dizge verify` (or trying this tutorial against a
disposable copy of the repo, as was done while writing it) sidesteps it in the meantime.

## Where to go next

- `docs/architecture.md` — how routing, the container, controllers and rendering fit together, and
  who owns what across DizgePHP, CKCSS and DevinimJS.
- `docs/security-baseline.md` — the escaping and component-rendering boundary you just relied on in
  steps 3 and 7, plus session, CSRF and authorization defaults for anything that changes state.
- `docs/data-threat-model.md` — the guarantees (and limits) behind `db()->table()`.
- `AGENTS.md` — the project's non-negotiable rules in one page, useful as a checklist before your
  first real change.

This tutorial deliberately built a small feature by hand, route by route, so every piece — routing,
container binding, migrations, querying, rendering — is visible once. Once these pieces are
familiar, skim `bin/dizge` with no arguments for the full command list; it may have grown a faster
scaffold for this exact CRUD-ish shape since this was written.
