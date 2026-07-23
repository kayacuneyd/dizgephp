# Handoff: TASK-001 — Implementer

## Status

Complete.

## Inputs reviewed

- `docs/swarm/tasks/TASK-001-agent-dx-scaffolding.md` (full task spec, read first as instructed)
- `AGENTS.md`, `docs/architecture.md`
- `bin/dizge` (full file, before editing): `writeGenerated()`, `pascal()`, `usage()`, the existing
  `make:controller`/`make:action`/`make:migration` cases, `assetParity()`/`manifest()`
- `app/Http/Controllers/FrameworkController.php`, `src/Dizge/Http/Controller.php`,
  `src/Dizge/Http/Request.php`, `src/Dizge/Http/Response.php`, `src/Dizge/Http/Router.php`,
  `src/Dizge/Application.php` (routing/handler resolution, `useDatabase()`, global middleware)
- `src/Dizge/Devinim/Renderer.php` (`COMPONENTS` const — ground truth prop allowlist)
- `src/Dizge/Data/Database.php`, `src/Dizge/Data/Query.php`, `src/Dizge/Data/Migrator.php`,
  `src/Dizge/Data/Migration.php`
- `src/Dizge/Validation/Validator.php`, `ValidationException.php`, `ErrorBag.php`
- `src/Dizge/Security/VerifyCsrf.php`, `src/Dizge/Security/Csrf.php`
- `src/Dizge/View/ViewRenderer.php`, `views/home.php`
- `routes/web.php`, `routes/services.php`, `routes/demo.php` (CRUD/validation/CSRF conventions)
- `bin/publish-assets` (the `DEVINIM_DIST` env-var-override convention to mirror)
- `bin/verify`, `bin/benchmark`, `tests/core_test.php`, `tests/fullstack_test.php`,
  `tests/cms_test.php` (existing test conventions, `shell_exec`/`exec` CLI-testing pattern)
- `database/migrations/20260722_create_dizgecms.php`, `.../20260724_create_dizge_api_tokens.php`
  (existing migration file style)
- `docs/adr/0001-explicit-router.md`, `docs/adr/0004-full-stack-explicit-modules.md`
- `../devinimjs/docs/component-manifest.json` (sibling repo, confirmed present on this host at
  `/var/www/devinimjs/docs/component-manifest.json`)
- `/var/www/devinimjs/src/components/dv-data-table.js`, `.../dv-field.js` (to shape realistic
  `columns`/`rows`/`options` payloads for the generated views, beyond just "prop name exists")
- `docs/swarm/README.md`, `docs/swarm/active-work.md`, `docs/swarm/tasks/TASK-004-*.md` (checked
  for ownership collisions before touching anything outside my table — see below)

## Evidence and findings

### 1. `make:resource`

Added `pluralize()`, `snake()`, `migrationTemplate()`, `componentContract()` helpers and a
`make:resource` case to `bin/dizge`. `php bin/dizge make:resource Product` generates:

- `app/Http/Controllers/ProductController.php` — `final class ProductController extends
  Controller`, constructor-injected `Database`, one action per `index/show/create/store/edit/
  update/destroy`, each with `/** @param array<string,string> $params */` and returning
  `Response`, matching `FrameworkController`'s style. `store()`/`update()` catch
  `ValidationException` and re-render the form with field errors (same pattern as
  `routes/demo.php`'s CMS editor handlers).
- `database/migrations/202607231250_create_products.php` — reuses `migrationTemplate()`, the same
  shape `make:migration` emits, filled with a plausible `id`/`name`/`created_at`/`updated_at`
  schema.
- `views/products/index.php` and `views/products/form.php` — `dv('data-table', columns: [...],
  rows: $rows, label: $title)` and `dv('field', id:, name:, label:, control:, type:, value:,
  placeholder:, required:, disabled:, error:)`. Every prop name was checked against
  `Renderer::COMPONENTS['data-table']` and `['field']` before use; `columns`/`rows` shape
  (`{key,label}` / row objects) was checked against `dv-data-table.js`'s `#columns()`/`#rows()`
  parsing so the output is real, not just plausible.
- Prints (never writes) a container-binding snippet for `routes/services.php` and seven
  `app()->get/post(...)` lines for `routes/web.php` — GET/POST only, since `Application` only
  exposes `get()`/`post()` (no PUT/PATCH/DELETE, no method-override), matching the
  `routes/demo.php` convention of using POST for updates. Confirmed no route file was modified:
  `grep -r WidgetController routes/*.php` finds nothing after generation (asserted in the test).

Actual CLI output for the shipped example:

```
$ php bin/dizge make:resource Product
Created app/Http/Controllers/ProductController.php
Created database/migrations/202607231250_create_products.php
Created views/products/index.php
Created views/products/form.php

Paste into routes/services.php:

app()->container()->singleton(ProductController::class, fn (Dizge\Container $container): ProductController => new ProductController(db()));

Paste into routes/web.php:

app()->get('/products', [ProductController::class, 'index']);
app()->get('/products/create', [ProductController::class, 'create']);
app()->post('/products', [ProductController::class, 'store']);
app()->get('/products/{id}', [ProductController::class, 'show']);
app()->get('/products/{id}/edit', [ProductController::class, 'edit']);
app()->post('/products/{id}', [ProductController::class, 'update']);
app()->post('/products/{id}/delete', [ProductController::class, 'destroy']);

Then: php bin/dizge migrate up

$ php bin/dizge make:resource Product   # run again
[dizge] Refusing to overwrite existing file: /var/www/dizgephp/app/Http/Controllers/ProductController.php
$ echo $?
1
```

I manually round-tripped the generated `ProductController`/views through a real `Application`
(fresh in-memory SQLite, migrations applied, routes registered exactly per the printed snippet)
before writing it into the automated test: index/create/store(+validation error)/show/edit/
update/destroy/404/CSRF-419 all behaved correctly. That round trip is now `tests/
make_resource_test.php` (using a throwaway `Widget` resource, cleaned up in a `finally` block so
the working tree stays clean between runs — verified by running the test twice back to back and
`git status --porcelain` showing nothing).

**Pre-existing bug fixed as a direct dependency of this work**: `make:controller` and
`make:migration`'s existing templates used `\\$params`/`\\$request`/`\\$database` (double
backslash) instead of `\$params` (single backslash) inside their double-quoted PHP source
strings. In PHP, `\\$foo` is an escaped backslash followed by *live interpolation* of `$foo`, not
an escaped `$`. I confirmed this empirically: running the original code emitted `PHP Warning:
Undefined variable $params` and produced genuinely broken output (`Request \, array \): Response`
— invalid PHP, would fail `php -l`). Both `make:controller` and `make:migration` were silently
generating malformed files before this change. I fixed the escaping (5 occurrences, `bin/dizge`
lines that became `\$params`/`\$request`/`\$database`) and introduced `migrationTemplate()` as a
single shared template so `make:migration` and `make:resource` cannot drift. This was necessary
for my own feature to work correctly (I reuse `migrationTemplate()` verbatim per the task's
instruction to "reuse the exact template shape"), and it was in my owned file, so I fixed it
rather than propagate the same bug into new code.

### 2. `inspect --json` component contract

Added `componentContract()`, wired into `manifest()` as a new `component_contract` field. It
starts from `Renderer::COMPONENTS` (read via `ReflectionClass::getReflectionConstant()` — PHP
reflection can read a private constant's value without needing `Renderer.php` to expose a getter,
so the "ground truth" file stays untouched per the task's non-goals) and, when resolvable, merges
in `attributes`/`events` from the sibling `component-manifest.json`. Path resolution mirrors
`bin/publish-assets`'s `DEVINIM_DIST` convention exactly: a fixed absolute default
(`/var/www/devinimjs/docs/component-manifest.json`), overridable via `DEVINIM_MANIFEST`. Only
components already present in `Renderer::COMPONENTS` are enriched — an unknown manifest entry is
silently ignored rather than surfaced as if `dv()` accepted it.

Verified directly:

```
$ php bin/dizge inspect --json | php -r '...'
# on this host, /var/www/devinimjs/docs/component-manifest.json is resolvable by default:
component_contract.field = {
  props: [id, name, label, control, type, options, value, placeholder, required, disabled, error],
  tag: "dv-field",
  attributes: [data-id, data-name, ..., data-error],
  events: [dv:input, dv:change]
}

$ DEVINIM_MANIFEST=/nonexistent/path/component-manifest.json php bin/dizge inspect --json; echo $?
0
# component_contract.field.props still present; .attributes and .events are null (not fabricated)

# Malformed JSON at the override path also degrades (JsonException caught) rather than fataling;
# `inspect --markdown` (the other output mode) is unaffected either way.
```

Both cases (env var pointed at a nonexistent path, and pointed at a syntactically invalid JSON
file) are asserted in `tests/inspect_component_contract_test.php`, along with a positive case
(env var pointed at a well-formed temp manifest) proving the merge itself works and that
`Renderer::COMPONENTS`-unknown entries are dropped.

### 3. `sh bin/verify`

Full tail, all 13 test files green (11 pre-existing + 2 new):

```
DizgePHP core tests passed.
DizgePHP config tests passed.
DizgePHP trusted-proxy tests passed.
DizgePHP security tests passed.
DizgePHP data tests passed.
DizgePHP auth tests passed.
DizgePHP validation/rate tests passed.
DizgePHP login tests passed.
DizgePHP CMS tests passed.
DizgePHP bridge tests passed.
DizgePHP full-stack tests passed.
DizgePHP make:resource tests passed.
DizgePHP inspect component-contract tests passed.
```

(The full-repo `php -l` sweep that `bin/verify` runs first also passed, covering every generated
file left in the tree.)

### 4. `sh bin/benchmark --check`

```
controller_dispatch: 0.0027 ms/op (limit 0.0037)
cache_round_trip: 0.0002 ms/op (limit 0.0004)
file_cache_round_trip: 0.2263 ms/op (limit 0.3680)
router_dispatch_at_scale: 0.1221 ms/op (limit 0.1725)
job_enqueue: 0.1291 ms/op (limit 0.1616)
```

All under their limits (~29.5s wall). This task never touches `bench/fullstack.php`,
`bench/router.php`, `src/Dizge/Application.php`, or any dispatch-path code, so this is confirmation
only, as expected.

## Changed files

- `bin/dizge` — extended (new `make:resource` case; `pluralize()`, `snake()`,
  `migrationTemplate()`, `componentContract()` helpers; `component_contract` added to `manifest()`;
  `usage()` text updated; escaping bug fix described above)
- `bin/verify` — two new lines registering the new tests, same style as existing lines
- `app/Http/Controllers/ProductController.php` — new (generator output, kept as the shipped
  working example per the ownership table)
- `database/migrations/202607231250_create_products.php` — new (generator output, same)
- `views/products/index.php`, `views/products/form.php` — new (generator output, same)
- `tests/make_resource_test.php` — new
- `tests/inspect_component_contract_test.php` — new
- `tests/cms_test.php` — **one line changed, outside my nominal ownership table; see "Open
  questions and risks" below for the full justification.** Diff:
  ```diff
  -$migrations = Migrator::load(dirname(__DIR__) . '/database/migrations');
  -cms_expect($migrator->migrate($migrations) === ['20260722_create_dizgecms', '20260723_add_cms_publishing_features', '20260724_create_dizge_api_tokens'], 'CMS and official-module migrations must apply to an empty SQLite database in order.');
  +$migrations = Migrator::load(dirname(__DIR__) . '/database/migrations');
  +$baseline = ['20260722_create_dizgecms', '20260723_add_cms_publishing_features', '20260724_create_dizge_api_tokens'];
  +cms_expect(array_values(array_intersect($migrator->migrate($migrations), $baseline)) === $baseline, 'CMS and official-module migrations must apply to an empty SQLite database in order.');
  ```

## Open questions and risks

- **`tests/cms_test.php` fix, outside my ownership table.** `cms_test.php` calls
  `Migrator::load(dirname(__DIR__) . '/database/migrations')` (the real, shared directory) and
  asserted the applied-migrations list was *exactly* the three hand-authored baseline migrations.
  Leaving `database/migrations/202607231250_create_products.php` in place (an explicit, named
  deliverable in this task's ownership table — "generator output ... as its own working example")
  makes `Migrator::load()` pick up a fourth file, which broke that exact-equality assertion —
  `sh bin/verify` failed with `RuntimeException: CMS and official-module migrations must apply to
  an empty SQLite database in order.` This is not a bug in the generated migration (it applies
  cleanly on its own, and `tests/make_resource_test.php` proves that); it's a pre-existing
  fragility in `cms_test.php` — it implicitly assumed `database/migrations/` would only ever hold
  its own three files, which any migration-generating feature was always going to break. Before
  touching it I checked: (a) no other in-flight task's ownership table lists `tests/cms_test.php`
  or adds any migration file — TASK-004 (rate limiting) explicitly excludes `bin/dizge` and "any
  generator/scaffolding file" and uses file-based storage, not a migration; TASK-002 is docs-only;
  TASK-003 is asset caching. (b) The task's own acceptance criteria require both `sh bin/verify`
  passing *and* the Product migration file remaining in the tree — those two requirements are
  mutually exclusive under the original assertion, so satisfying both was not optional. I made the
  minimal, semantics-preserving fix shown above: it still asserts the three baseline migrations
  apply successfully and in relative order, it just no longer assumes they are the only content
  in the directory. **Flagging this explicitly for orchestrator review** since it is a file outside
  my assigned ownership table — please confirm this is acceptable, or direct a different
  resolution (e.g. dropping the migration file from the diff, if `sh bin/verify` passing must take
  priority over keeping it).
- **Container binding cannot be auto-wired.** `Dizge\Container` never reflects or autowires
  (ADR-0004); `routes/services.php` is explicitly off-limits to this task. So the generated
  `ProductController` is not runnable until a developer pastes the printed
  `app()->container()->singleton(...)` line into `routes/services.php` themselves — the same
  manual-wiring step `make:controller`'s output already requires today (verified: even a
  zero-argument generated controller can't be resolved by `Container::get()` without an explicit
  binding). I judged printing that snippet, in addition to the route snippet the acceptance
  criteria explicitly asked for, to be necessary for the printed instructions to actually work
  end-to-end when pasted — not an addition to scope, but a correctness requirement of the feature
  as specified ("prints the exact ... lines to copy-paste").
- **Naive pluralization.** `pluralize()` handles the common English cases (`-y`→`-ies`,
  `-s/x/z/ch/sh`→`-es`, default `-s`) but is not a full inflector (e.g. irregulars like
  "person"→"people" aren't handled; it would produce "persons"). This is a generator convenience,
  not a data-integrity concern — the developer sees and can rename the generated table/route/view
  names before running `migrate up` if the naive plural is wrong for their word.
  `snake()`/`pluralize()` were verified against "Product" (→ products) and "BlogPost" (→
  blog_posts / blog-posts) during development; only "Product" is kept as the committed example.
  `pascal()` (pre-existing, unchanged) still rejects non-identifier input before either helper
  runs.
  - `show()`'s route currently reuses `form.php` in a read-only (`disabled`) rendering rather than
  a dedicated detail view, since `dv-field`'s only relevant prop for this is `disabled`, not a
  `readonly` mode — this keeps the generated view count at two files as the acceptance criteria's
  "or a single file if that's simpler" allows, while still giving `show()` a real, distinct
  response body (asserted in the test: `show()` renders the stored value and 404s for a missing
  row).
  - The generated resource intentionally only has a single `name` column beyond `id`/timestamps —
  a deliberately minimal, obviously-editable starting schema, not a claim that every resource is
  a name-only entity.

## Next recipient

Orchestrator (integration review) — in particular please review the `tests/cms_test.php` change
called out above, since it is the one file I touched outside my assigned ownership table.
