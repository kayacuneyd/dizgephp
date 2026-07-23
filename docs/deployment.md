# Deployment

## Shared hosting

- Upload a tagged GitHub Release ZIP or use a tag-pinned Git checkout.
- Set the web root to `public/`; the included `.htaccess` routes non-files to `index.php`.
- Keep `.env` and `storage/` outside public web access. `storage/` must be writable by PHP.
- Use HTTPS at the host/proxy and set `DIZGE_ENV=production`, `DIZGE_DEBUG=0`.

## Nginx

Use `public/` as `root`, send unknown paths to `/index.php?$query_string`, and pass PHP files to
the configured PHP 8.1+ FPM socket. Do not expose the project root, `.env`, migration files or
`storage/` through a fallback location.

When Nginx (or any reverse proxy) terminates TLS in front of PHP-FPM on the same host, PHP never
sees `HTTPS=on` directly. Set `DIZGE_TRUSTED_PROXIES` to that proxy's own address (see
`.env.example`) so DizgePHP trusts its `X-Forwarded-For`/`X-Forwarded-Proto` headers for the
session Secure-cookie flag and for rate-limit IP keying — without it, every request behind a
shared proxy collapses onto one rate-limit bucket. Never set it to a public/untrusted range.

## DevinimJS and CKCSS assets

`public/assets/devinim/` and `public/assets/ckcss.min.css` are committed, build-free artifacts
(so a tagged checkout works with zero build step, per the project's shared-hosting contract) —
but they are not auto-synced from the sibling DevinimJS/CKCSS repos. Run `bin/publish-assets`
whenever either sibling repo changes, and always before tagging a release. It reads a built
DevinimJS `dist/` (default `../devinimjs/dist`, override with `DEVINIM_DIST`) and a CKCSS assets
directory (default `../ckcss/site/assets`, override with `CKCSS_ASSETS`) from sibling checkouts.
Run `bin/dizge assets:check` afterward — it fails loudly if `Renderer::COMPONENTS`
(`src/Dizge/Devinim/Renderer.php`) and the files actually published under
`public/assets/devinim/modules/` ever drift apart, instead of a component silently 404ing.

### Caching self-hosted assets

`public/.htaccess` sets a one-year, `immutable` `Cache-Control` on everything under `/assets/`
(guarded by `<IfModule mod_headers.c>`, scoped by request path via a `mod_rewrite` env flag so it
can never apply to `index.php` or any other response) — on a cheap shared-hosting tier with no
CDN in front of it, cutting repeat-visit asset requests to zero is the single biggest performance
lever available. This is only safe for the files that actually carry a cache-busting URL:
DevinimJS component/runtime filenames are not content-hashed (no build step, per the contract
above), so `dv_scripts()` (`Dizge\Devinim\Renderer::scripts()`) appends a `?v=<mtime>` query
string read from the actual published file on disk (`Renderer::versionedUrl()`), and the URL
changes automatically the moment `bin/publish-assets` republishes a changed file — browsers never
keep serving a stale, possibly broken component for the cache's full year. Two captures of
`dv_scripts()` between publishes are byte-for-byte identical (see `tests/asset_versioning_test.php`);
only a real republish changes them.

This versioning covers every DevinimJS component module actually used on the page (everything
printed through `dv_scripts()`). It does **not** currently cover `dizge-bridge.js`
(`dv_bridge_scripts()` in `dizge.php` still prints a plain, unversioned `src="/assets/dizge-bridge.js"`
— an existing regression test asserts that exact string and only that test's owner can safely
change it; see `docs/swarm/handoffs/TASK-003-implementer.md`), nor `ckcss.min.css`,
`devinim-ckcss.css`, `dizgecms.css` or `dizgecms-mark.svg`, which are linked with a hardcoded
`href`/`src` in `src/Dizge/Cms/View.php`/`routes/demo.php` rather than through the `Renderer`. If
you publish a change to any of those four files, or to `dizge-bridge.js`, expect visitors'
browsers to keep the previous cached copy for up to a year; bust it by renaming the file (and its
`href`/`src`) or by purging/versioning it at your host or CDN.

### Recommended for the cheapest hosting tier: a pinned CDN instead of self-hosting

For the most resource-constrained shared hosting — the tier this caching setup is aimed at —
prefer pointing DevinimJS at jsDelivr instead of self-hosting `public/assets/devinim/` at all.
DevinimJS publishes exactly this pattern itself (see `../devinimjs/README.md` and
`../devinimjs/docs/guides/php-integration.md`, ADR-0007): a pinned, tagged release URL, never
`@latest` in production. Point `dv_path()` (`dizge.php`) at it once during bootstrap, e.g. in
`routes/services.php`:

```php
dv_path('https://cdn.jsdelivr.net/gh/kayacuneyd/devinimjs@v0.1.0/dist/modules/');
```

`Renderer::scripts()` detects the `https://` scheme and skips the `?v=` query string entirely for
that URL — a pinned CDN URL is already immutable by tagged version, and jsDelivr sets its own
long-cache headers, so there is nothing left for this project's `.htaccess` or `Renderer` to add.
Bumping the DevinimJS version means bumping the `@vX.Y.Z` pin here; nothing to republish. CKCSS
has the same pinned-CDN option for `ckcss.min.css` (see `../ckcss/README.md`), but that link is
hardcoded in `src/Dizge/Cms/View.php`/`routes/demo.php` rather than routed through a helper, so
switching it means editing those `<link>` tags directly.

Self-hosting via `bin/publish-assets` (the default) remains the right choice for hosts that
cannot reach an external CDN, or that need every asset served from the same origin (e.g. a strict
`script-src 'self'` CSP with no CDN origin allow-listed).

## Database, jobs and backups

SQLite defaults to `storage/app.sqlite`; its connection enables foreign keys, WAL and a busy timeout.
Run migrations through `bin/dizge migrate up`. Take a backup before production migrations.

The official file queue is designed for cron, not a persistent daemon:

```cron
* * * * * /path/to/app/bin/dizge job:work 25 >/dev/null 2>&1
```

`storage/cache/` only evicts an expired entry lazily, on its next read, so entries nobody reads
again would otherwise accumulate forever; run a daily sweep (it also rotates `cms-audit.log` past
5&nbsp;MB, keeping the newest 14 rotated files, the same retention as `bin/cms-backup`):

```cron
17 4 * * * /path/to/app/bin/dizge cache:gc >/dev/null 2>&1
```

Run `bin/dizge verify` and `bin/dizge benchmark --check` before every tagged release.
