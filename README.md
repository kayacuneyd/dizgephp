# DizgeCMS

Build-free PHP 8.1+ plugin-ready CMS on the DizgePHP full-stack framework. DizgeCMS owns publishing,
security and data; CKCSS owns presentation; DevinimJS owns browser interaction. It works from a
tagged Git checkout or a GitHub Release ZIP—Composer and Node.js are optional, not required.

**Product specification:** [DizgeCMS product spec](docs/specs/2026-07-23-dizgecms-product.md)

**Documentation:** [getting started](docs/getting-started.md) · [architecture](docs/architecture.md)
· [deployment](docs/deployment.md) · [security](docs/security-baseline.md) ·
[releasing](docs/releasing.md)

## Start

```bash
git clone --branch v0.1.0 --depth 1 https://github.com/kayacuneyd/dizgephp.git my-app
cd my-app && cp .env.example .env
php -S localhost:8080 -t public public/index.php
bin/dizge verify
```

Routes live in `routes/web.php` and return explicit `Response` values:

```php
app()->get('/products/{id}', function (Request $request, array $params): Response {
    return Response::json(['id' => $params['id']]);
});
```

Render a validated DevinimJS component from a PHP view:

```php
<?= dv('counter', start: 10, step: 5) ?>
<?= dv_scripts() ?>
```

When a component needs a server-authoritative correction — not just server-rendered initial
state — bind a handler and load the bridge client:

```php
dv_bridge('welcome-counter', '#welcome-counter', 'change', function (array $detail): ?Dizge\Bridge\Patch {
    return null; // or `dv_patch([...])` to correct the client
});
```
```php
<?= dv_bridge_scripts() ?>
```

See [the bridge ADR](docs/adr/0005-devinim-bridge-protocol.md) and
[the bridge threat model](docs/bridge-threat-model.md) before adding a binding.

See [the project spec](docs/specs/2026-07-22-new-project.md) for MVP and future module scope.
The current escaping and component-rendering boundary is documented in
[the security baseline](docs/security-baseline.md).

## Full-stack application shape

`routes/web.php` owns server-rendered HTML and `routes/api.php` owns explicit `/api/v1` JSON routes.
Both may call one action/service. Controllers are explicit container bindings; there is no reflection
autowiring or file-route magic. The included `/framework` and `/api/v1/framework` routes demonstrate
the same `FrameworkStatus` action with different representations.

```php
app()->get('/projects', [ProjectController::class, 'index']);
app()->get('/api/v1/projects', [ProjectController::class, 'indexApi']);
```

Use the single CLI entrypoint for development and agent context:

```bash
bin/dizge route:list
bin/dizge inspect --json
bin/dizge make:controller Project
bin/dizge make:action CreateProject
bin/dizge migrate up
bin/dizge verify
bin/dizge benchmark --check
```

The official build-free modules are filesystem cache, cron-backed file jobs, mail transports and
scoped API tokens. They are enabled in `config/modules.php`; local overrides belong in the ignored
`config/modules.local.php`. Read the [API threat model](docs/api-threat-model.md) and
[operations threat model](docs/operations-threat-model.md) before extending them.

Run jobs from shared-hosting cron in bounded batches, for example:

```cron
* * * * * /var/www/dizgephp/bin/dizge job:work 25 >/dev/null 2>&1
```

Create a token only through a terminal and store the printed value in a secret manager; the value is
shown once:

```bash
bin/dizge token:issue service-name deployer content.read
```

## DizgeCMS Studio

The reference workflow CMS is served below `/demo`. It uses the app-local SQLite default
(`storage/app.sqlite`) unless `DIZGE_DB_DSN` is configured. Initialize it, then create the only
administrator without placing credentials in source control:

```bash
bin/migrate up
DIZGE_ADMIN_PASSWORD='choose-a-long-unique-password' bin/cms-admin create admin@example.com
bin/publish-assets
```

Studio is at `/demo/studio`; public pages and articles are exposed only after the explicit
`draft → review → approved → published` workflow.

Daily SQLite snapshots run at 03:17 UTC and retain 14 copies under `storage/backups/`. Create a
manual snapshot with `bin/cms-backup`. A restore is deliberately force-gated:

```bash
bin/cms-restore storage/backups/dizgecms-YYYYmmdd-HHMMSS.sqlite --force
```

Restore only during a maintenance window; it replaces the live database.

When a real editorial team is needed, create invite-only accounts from the CLI. Roles are enforced
in the Studio workflow: `author` submits drafts, `reviewer` approves/returns review items and
`publisher` publishes or archives approved content. `admin` retains full control.

```bash
DIZGE_USER_PASSWORD='another-long-unique-password' bin/cms-user create reviewer reviewer@example.com
```

Published content is available at `/demo/articles/{slug}` or `/demo/pages/{slug}`. `approved` is
not public: use the final `published` workflow transition. Public search is `/demo/search`; the
public-only agent context contract is `/demo/api/context?q=your+query` and is intentionally backed
by SQLite FTS5 until a separately authorized vector adapter is needed.

## Data

Set `DIZGE_DB_DSN` (and optional credentials) from `.env.example`, then use the explicit PDO
boundary. Values are parameterized; identifiers and ordering are validated.

```php
$products = db()->table('products')
    ->where('price', '>=', 50)
    ->orderBy('price', 'DESC')
    ->limit(20)
    ->get();
```

See the [data threat model](docs/data-threat-model.md) before extending this layer.

## Browser sessions

Protect state-changing routes explicitly. The application owns credential lookup and password
verification; DizgePHP owns session fixation defense, CSRF validation and authorization policies.

```php
app()->post('/articles/{id}', $handler, [new RequireAuth(auth())]);

gate()->define('article.update', fn (Identity $user, array $article) => $user->id() === $article['owner_id']);
```

CSRF validation is global for state-changing browser routes; use `<?= csrf_field() ?>` in every form. Read the
[authentication threat model](docs/auth-threat-model.md) before adding a login flow.
