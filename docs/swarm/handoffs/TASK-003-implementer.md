# Handoff: TASK-003 — Implementer

## Status

Complete, with one open follow-up flagged for the orchestrator (see "Open questions and risks").

## Inputs reviewed

- `docs/swarm/tasks/TASK-003-shared-hosting-asset-performance.md` (full task spec, read first)
- `public/.htaccess` (current state before editing: `Options -Indexes`, dotfile-blocking rewrite,
  the three security headers, the clean-URL rewrite — all preserved, nothing removed)
- `docs/deployment.md` (current "DevinimJS and CKCSS assets" section, extended not replaced)
- `src/Dizge/Devinim/Renderer.php` in full
- `bin/publish-assets` in full
- `AGENTS.md` (build-free/zero-dependency contract)
- `dizge.php` in full (`dv_scripts()`, `dv_path()`, `dv_bridge_scripts()`)
- `bench/fullstack.php` and `bin/benchmark` (to confirm the benchmarked hot path)
- `tests/core_test.php`, `tests/trusted_proxy_test.php`, `tests/bridge_test.php` (existing test
  style/idioms, and — critically — an existing assertion my first draft broke, see below)
- Sibling repos: `../devinimjs/README.md`, `../devinimjs/docs/guides/php-integration.md`,
  `../devinimjs/adr/0007-distribution-versioning.md`, `../ckcss/README.md` (for the exact
  pinned-jsDelivr-URL pattern DevinimJS/CKCSS already document and publish to)

## Evidence and findings

### 1. Cache headers (`public/.htaccess`)

Added, inside the file's existing `<IfModule mod_headers.c>` block (reusing the module dependency
this file already has, rather than introducing `mod_expires` as a third module):

```
Header set Cache-Control "public, max-age=31536000, immutable" env=DIZGE_STATIC_ASSET
```

Scoped to `/assets/` **by request path**, not by file extension, via a new rule in the existing
`<IfModule mod_rewrite.c>` block (placed after the dotfile-block rule, before the front-controller
routing rule, and using `-` as the substitution so it never redirects, only tags):

```
RewriteCond %{REQUEST_URI} ^/assets/
RewriteRule ^ - [E=DIZGE_STATIC_ASSET:1]
```

This is the standard Apache technique for path-scoped response headers from a `.htaccess` file:
`<Directory>`/`<DirectoryMatch>` are not permitted inside `.htaccess`, and `<FilesMatch>` matches
only the basename (not the path), so it can't express "under /assets/" directly. Setting an
environment variable with mod_rewrite's `E=` flag and reading it back with mod_headers' `env=`
condition is the well-documented workaround, and it reuses the two modules this file already
depends on (`mod_rewrite` for the front-controller redirect is already a hard requirement of this
framework, so a host that lacks it already can't run clean URLs at all — I noted this dependency
explicitly in the `.htaccess` comment for the next reader).

**Verification method — read this before trusting a "PASS":** PHP's built-in dev server
(`php -S`) does not process `.htaccess` at all, and this environment has no `apache2`/`httpd`
binary installed (checked: `which apache2ctl httpd apachectl apache2` → nothing found), so I
could not run `apachectl -t`, `curl -I` against a real Apache, or any other live test of these
directives. I verified the directive syntax by hand: matching `<IfModule>`/`</IfModule>` pairs,
correct `Header`/`RewriteCond`/`RewriteRule` argument counts and quoting, and cross-checked the
`RewriteRule ... [E=...]` + `Header ... env=...` pattern against how it's documented to work. This
is reasoning-based verification, not an executed test — flagging exactly as the task's gate asks,
rather than claiming a live-server pass that isn't possible here.

### 2. Cache-busting (`src/Dizge/Devinim/Renderer.php`, `Renderer::versionedUrl()`)

Added `Renderer::versionedUrl(string $urlPath): string`, a public static method:

- If `$urlPath` doesn't start with `/`, or starts with `//` (protocol-relative), it's treated as
  an external URL (an operator-pointed CDN via `dv_path()`) and returned unchanged — `filemtime()`
  has no meaning for a non-local URL, and a pinned CDN URL is already immutable by tagged version.
- Otherwise it resolves `$urlPath` against the project's `public/` directory
  (`dirname(__DIR__, 3) . '/public' . $urlPath` — `Renderer.php` lives at
  `src/Dizge/Devinim/Renderer.php`, three levels under the project root, which is stable by
  construction of the `Dizge\` autoload mapping in `dizge.php`) and, if that file exists on disk,
  appends `?v=<filemtime()>`. If the file doesn't exist (e.g. a component in
  `Renderer::COMPONENTS` that hasn't been published yet), it degrades to the unversioned URL
  rather than erroring.

`Renderer::scripts()` now wraps every module URL it emits with `versionedUrl()`.

**Why `filemtime()` and not a manual version counter or content hash:** the task explicitly rules
out a build-time content-hashing pipeline (build-free contract, `AGENTS.md`). A manual counter
would require a human (or `bin/publish-assets`) to remember to bump it, which is exactly the kind
of "someone forgets and ships a stale cache" failure mode this task exists to prevent. `filemtime()`
is automatic and free: I confirmed by hand-testing in this environment that GNU coreutils
`install` (used throughout `bin/publish-assets`, unlike `cp -p`) always sets the destination
file's mtime to "now" on every run, regardless of whether content changed — so every
`bin/publish-assets` run naturally invalidates the cache for every file it touches, with zero
change needed to that script's logic. I documented this explicitly with a comment at the top of
`bin/publish-assets` since it's a non-obvious "why does this work" fact for a future maintainer.

**Test:** `tests/asset_versioning_test.php` (registered in `bin/verify`, immediately after
`tests/bridge_test.php`), exercising the real published `public/assets/devinim/modules/dv-counter.js`
on disk (not a fixture):

1. Asserts `dv_scripts()`'s (via `Renderer::scripts()`) output contains
   `dv-counter.js?v=<mtime>` and that the captured `v` value equals `filemtime()` of the real file.
2. Stability: two captures with no change in between are asserted byte-identical.
3. Change detection: `touch()`s the file to `filemtime() + 5` (explicit offset, not wall-clock
   timing, since `filemtime()` has 1-second resolution and a fast test could otherwise land in the
   same second and false-negative), `clearstatcache(true, $path)`s, re-captures, and asserts the
   URL changed and the new `v` matches the new mtime. Restores the original mtime in a `finally`
   block so the test is non-destructive to the committed asset.
4. Asserts an external CDN module path (`setModulePath('https://cdn.jsdelivr.net/...')`) is
   emitted with **no** `?v=` query string.
5. Direct unit coverage of `Renderer::versionedUrl()` for `/assets/dizge-bridge.js`, a
   non-existent local path (graceful degradation), and an external URL.

I deliberately did **not** have the test shell out to `bin/publish-assets` itself: that requires
sibling checkouts of `../devinimjs`/`../ckcss` with a built `dist/`, which exist in this
environment but aren't guaranteed for every environment `bin/verify` might run in, and no other
test in the suite has that kind of external dependency. Using the already-committed, already-published
`dv-counter.js` as the "publish has happened" fixture and `touch()` to simulate "publish
changed this file" gives the same coverage without that fragility.

### 3. `dv_bridge_scripts()` (`dizge.php`) — decided NOT to version it, and why

My first pass added the identical `?v=<mtime>` treatment to `dv_bridge_scripts()`'s
`/assets/dizge-bridge.js` URL, on the reasoning that it has the exact same staleness exposure as
component modules (same directory, same new long/immutable Cache-Control, same unhashed filename).
Running `sh bin/verify` immediately surfaced that this breaks an **existing** test:

```
PHP Fatal error:  Uncaught RuntimeException: dv_bridge_scripts() must load the published bridge
client module. in tests/bridge_test.php:12
```

`tests/bridge_test.php` line 70 asserts an exact, query-string-free substring:
`str_contains($scripts, '<script type="module" src="/assets/dizge-bridge.js">')`. That test file
is explicitly outside this task's file ownership ("do not touch ... any test file outside your
own new regression test" — this task's scope section, and the parent prompt's ownership list).
I reverted the `dv_bridge_scripts()` change rather than edit that test, since only its owner can
safely change what it asserts.

**This is a genuine, not-yet-closed gap**: `/assets/dizge-bridge.js` sits under the same new
one-year immutable cache and is not versioned, so a future fix to the bridge runtime will be
invisible to already-visited browsers for up to a year. I documented this explicitly in three
places so it isn't silently lost: a comment directly above `dv_bridge_scripts()` in `dizge.php`,
the `.htaccess` Cache-Control comment, and `docs/deployment.md`'s new "Caching self-hosted assets"
section. `Renderer::versionedUrl()` already works correctly for `/assets/dizge-bridge.js` as a
general-purpose utility (covered by test part 5) — wiring it into `dv_bridge_scripts()` is a
one-line change for whoever owns `tests/bridge_test.php`, once that assertion is updated to allow
(or itself assert) a `?v=` suffix.

I did not find any other call site that emits a `Renderer`-adjacent asset URL, so no other
decision was needed here.

### 4. `docs/deployment.md`

Extended the existing "DevinimJS and CKCSS assets" section (kept its tone/format) with two new
subsections: "Caching self-hosted assets" (documents the new header, the versioning mechanism,
and — explicitly — what is *not* covered: `dizge-bridge.js` per the above, plus `ckcss.min.css`,
`devinim-ckcss.css`, `dizgecms.css`, `dizgecms-mark.svg`, which are linked with a hardcoded
`href`/`src` in `src/Dizge/Cms/View.php` and `routes/demo.php` — both outside this task's file
ownership, so I could not add versioning there even if the task had asked for it) and "Recommended
for the cheapest hosting tier: a pinned CDN instead of self-hosting" (documents
`dv_path('https://cdn.jsdelivr.net/gh/kayacuneyd/devinimjs@v0.1.0/dist/modules/')`, the exact
pattern from `../devinimjs/README.md`, `../devinimjs/docs/guides/php-integration.md` and
ADR-0007 — verified against those files directly, not guessed — plus the parallel CKCSS jsDelivr
link from `../ckcss/README.md` for awareness, with a note that switching CKCSS to CDN means
editing the hardcoded `<link>` tags directly since it isn't routed through `dv_path()`).

### 5. `bin/publish-assets`

No functional change. Added a 4-line comment at the top explaining why no explicit versioning
step is needed (see point 2 above — `install`'s always-refresh-mtime behavior already drives the
`?v=` mechanism). I considered adding an explicit `touch` to force-refresh mtimes but it would be
redundant with what `install` already does by default, so I left it out to keep the diff minimal.

### 6. Hot-path / benchmark check

`Renderer::scripts()` (and thus `versionedUrl()`'s `is_file()`/`filemtime()` calls) is **not**
called anywhere in `bench/fullstack.php`. I read that file in full to confirm: the benchmarked
`controller_dispatch` metric dispatches `BenchController::show()`, which returns
`Response::api(['ok' => true])` directly — no `Devinim\Renderer` involvement at all. Same for
`router_dispatch_at_scale`. `dv_bridge_scripts()` (which I ultimately left unchanged) is likewise
not on that path. I did not add any `filemtime()`/`stat()` call anywhere in `Router::dispatch()`
or `Application::dispatch()` — I did not touch either file (out of scope).

`sh bin/verify` (tail of a full run, after the `dv_bridge_scripts()` revert):

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
Asset versioning tests passed.
DizgePHP full-stack tests passed.
```

`sh bin/benchmark --check`:

```
controller_dispatch: 0.0027 ms/op (limit 0.0037)
cache_round_trip: 0.0003 ms/op (limit 0.0004)
file_cache_round_trip: 0.1967 ms/op (limit 0.3680)
router_dispatch_at_scale: 0.1113 ms/op (limit 0.1725)
job_enqueue: 0.1310 ms/op (limit 0.1616)
```

All under their `bench/baseline.json` limits; `controller_dispatch` (0.0027 ms/op) is in the same
range as the unmodified baseline run (0.0028–0.0034 ms/op across repeated runs), consistent with
`Renderer`/`versionedUrl()` genuinely being off that path rather than the check merely happening
to pass this time.

Also ran, as extra corroboration (not required by the task, but cheap and directly relevant):
`php bin/dizge assets:check` → `OK: all 16 components and the DevinimJS runtime are published
under public/assets/devinim/.` — confirms my `Renderer.php` change didn't disturb the
`Renderer::COMPONENTS` ↔ published-files pairing that command guards.

## Changed files

- `public/.htaccess` — added the long/immutable `Cache-Control` for `/assets/**`, guarded by the
  existing `mod_headers`/`mod_rewrite` `<IfModule>`s; nothing removed.
- `src/Dizge/Devinim/Renderer.php` — added `Renderer::versionedUrl()`; `scripts()` now calls it.
- `dizge.php` — added an explanatory comment above `dv_bridge_scripts()` (function body
  unchanged — see point 3).
- `docs/deployment.md` — extended "DevinimJS and CKCSS assets" with two new subsections.
- `bin/publish-assets` — added an explanatory comment only; no behavior change.
- `bin/verify` — registered `tests/asset_versioning_test.php`.
- `tests/asset_versioning_test.php` — new regression test (see point 2).

## Open questions and risks

1. **`dv_bridge_scripts()` is not versioned** (point 3 above) despite sitting under the same new
   long/immutable cache. This is the one real residual risk from this task. Fixing it requires
   either (a) updating `tests/bridge_test.php`'s exact-match assertion to allow/expect a `?v=`
   suffix — needs sign-off from whoever owns that test — or (b) accepting the current
   documented-but-unversioned state. I'd lean toward (a) as a fast follow, since the fix on the
   `dizge.php` side is a one-line call to the already-tested `Renderer::versionedUrl()`.
2. **CKCSS/DizgeCMS CSS and the mark SVG are not versioned either** (`ckcss.min.css`,
   `devinim-ckcss.css`, `dizgecms.css`, `dizgecms-mark.svg`) — their `<link>`/`href` tags are
   hardcoded in `src/Dizge/Cms/View.php` and `routes/demo.php`, both outside this task's file
   ownership. The task's own scope section only asked for versioning on `Renderer::scripts()`
   output (i.e., DevinimJS component JS) and asked me to *decide* on `dv_bridge_scripts()`, not on
   the CSS links, so I read this as an intentional scope boundary rather than an oversight — CSS
   staleness is a visual-only degradation, not the functional-breakage risk that motivated the
   task's JS-focused cache-busting requirement. Documented explicitly in `docs/deployment.md` so
   it's not a silent trap for an operator who edits those files directly.
3. **Live-server verification wasn't possible in this environment** (point 1 above) — no
   `apache2`/`httpd` binary present, and `php -S` doesn't process `.htaccess`. Verified by hand
   instead; recommend a real curl/Apache check as part of this task's Apache-hosted staging/release
   gate if one exists, rather than treating my hand-verification as a substitute for it.
4. The `Header ... env=DIZGE_STATIC_ASSET` mechanism depends on `mod_rewrite` being active to set
   that env var. I judged this an acceptable dependency since `mod_rewrite` is already a hard
   requirement for this framework's clean-URL routing (same `<IfModule mod_rewrite.c>` block,
   pre-existing) — a host without it can't run the app's routing at all, so it isn't a new failure
   mode this change introduces. Worth a second opinion from whoever owns the Apache/shared-hosting
   deployment story end-to-end.

## Next recipient

Orchestrator (integration review)
