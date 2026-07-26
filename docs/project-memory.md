# DizgeCMS project memory

- 2026-07-24: Live vhost /etc/nginx/sites-available/dizgecms.digitaltamam.com serves
  /var/www/dizgecms/public through PHP 8.1 FPM; HTTP redirects to HTTPS and the certificate
  covers dizgecms.digitaltamam.com.
- 2026-07-24: Production backup created at
  storage/backups/dizgecms-20260724-234841.sqlite before launch verification.
- 2026-07-24: Launch verification passed for root, Studio product paths, public REST 404 behavior,
  robots, sitemap, TLS, mobile and desktop screenshots; sh bin/verify and
  sh bin/benchmark --check are green.

- 2026-07-24: Product-facing CMS paths are studio, admin, pages and articles; demo remains a
  compatibility path and canonical URLs use DIZGECMS_SITE_URL.
- 2026-07-24: WordPress compatibility is WXR import plus permalink/metadata preservation and
  scoped content REST, not arbitrary WordPress plugin/theme execution. See ADR-0007.
- 2026-07-24: WXR import now preserves publish dates and description metadata, creates collision
  redirects, and supports opt-in HTTPS media download with a 5 MB image limit.
- 2026-07-24: Content REST supports public published lists/read and scoped bearer-token
  create/update/delete operations; starter theme assets load from the active theme setting.
- 2026-07-24: Local plugin/theme discovery and activation use plugin.json/theme.json manifests,
  CLI commands, and ignored config/cms.local.php state; plugins are disabled by default. See
  ADR-0008.

- 2026-07-23: New product root is `/var/www/dizgecms`; public document root is `/var/www/dizgecms/public`.
- 2026-07-23: Promotional site target is `https://dizgecms.digitaltamam.com`.
- 2026-07-23: Stack is DizgePHP full-stack, PHP 8.1+, SQLite by default, build-free, CKCSS and DevinimJS.
- 2026-07-23: Product boundary: reusable single-site CMS core plus local/allowlist plugins and themes;
  Baltık Postası remains a reference application/theme.
- 2026-07-23: Follow `docs/kayaeos-agent-swarm.md`; every plugin/core concern needs contract, threat
  model where applicable, tests, benchmark and release verification.
- 2026-07-23: `dizgecms.digitaltamam.com` is served by the Nginx vhost at
  `/etc/nginx/sites-available/dizgecms.digitaltamam.com`; HTTP redirects to HTTPS and the
  Let’s Encrypt certificate is installed with automatic renewal.
- 2026-07-23: Production runtime uses `/var/www/dizgecms/.env`, PHP 8.1 FPM and writable
  `storage/` owned by `www-data`; database migrations have been applied.
- 2026-07-23: Marketing SEO baseline includes canonical/OG/JSON-LD metadata plus `/robots.txt`,
  `/sitemap.xml` and `/llms.txt` routes.
- 2026-07-25: WordPress-alternative product roadmap is recorded in
  `docs/dizgecms-wordpress-alternative-roadmap.md`. The next implementation session starts
  with Faz 1: idempotent installer, first-run wizard, default pages, initial menu and home-page
  settings; later phases cover editor/media, menus/settings, roles/workflow, theme management,
  distribution/update packaging and staging release verification.
- 2026-07-25: Headless direction is accepted as a hybrid CMS, not a full product pivot. ADR
  `docs/adr/0009-headless-hybrid-api-contract.md` defines the first DevinimJS REST contract,
  public-only published data, scoped write tokens, signed preview, and shared-hosting-friendly
  static/build output. The first headless vertical slice is site + pages/articles + menus + media
  + SEO consumed by a DevinimJS starter frontend.
- 2026-07-25: Headless API implementation started without removing generic content routes;
  resource-oriented public aliases `/api/v1/pages`, `/api/v1/pages/{slug}`, `/api/v1/articles`
  and `/api/v1/articles/{slug}` now expose canonical and frontend-relative URLs. `sh bin/verify`
  and live JSON smoke checks pass.
- 2026-07-25: Headless swarm TASK-005 integrated migration
  `20260725_create_headless_site_navigation` and `src/Dizge/Cms/SiteRepository.php`. This is the
  allowlisted source for public site settings and ordered `primary`/`footer` navigation; its
  isolated regression test is registered in `bin/verify`. TASK-006 owns the API integration.
- 2026-07-25: Headless swarm TASK-006 integrated `/api/v1/site`, `/api/v1/menus/{location}`,
  `/api/v1/media/{id}` and `/api/v1/search` alongside existing pages/articles aliases. Migration
  `20260725_create_headless_site_navigation` was applied after backup
  `storage/backups/dizgecms-20260725-headless-pre-migration.sqlite`; no existing records changed.
  Full verify, benchmark and live public API smoke checks passed.
- 2026-07-25: TASK-007 added `docs/headless-api-threat-model.md` and draft-leak regression coverage; TASK-008 added the build-free `examples/devinim-headless/` starter. TASK-010 subsequently added signed preview URLs and an exact HTTPS-origin public-read allowlist via `DIZGECMS_PUBLIC_ORIGINS`; preview and authenticated write routes are never CORS-enabled.
- 2026-07-25: The public product marketing routes are `/features`, `/headless`, `/install-guide`, `/download` and `/migrate-wordpress`. They are included in the sitemap and served from the live production checkout; `/install` remains the deliberately disabled-by-default web installer, not the public documentation route.
- 2026-07-25: Release package `dist/dizgecms-0.1.0-rc2.tar.gz` is served at `/releases/dizgecms-0.1.0-rc2.tar.gz` with its `.sha256` companion. A fresh remote download passed `sha256sum -c`; `sh bin/verify` and the live route, TLS and redirect checks passed.
- 2026-07-25: Isolated staging runs only on `http://127.0.0.1:8088`, with source in `/var/www/dizgecms-staging`, an independent SQLite database and Nginx site `/etc/nginx/sites-available/dizgecms-staging.local`. It emits `X-Robots-Tag: noindex, nofollow, noarchive`; no production database or public DNS is used. See `docs/staging-environment.md`.
- 2026-07-25: Production scheduled operations are defined in `/etc/cron.d/dizgecms` and run as `www-data`: due publishing every minute, daily SQLite backup, then cache/audit cleanup. The local staging database passed a backup → forced restore → API smoke test; cron service is active. See `docs/operations-cron.md`.
- 2026-07-25: Staging was refreshed from the current checkout and passed `php bin/cms-upgrade --yes`, `sh bin/verify`, `sh bin/benchmark --check` and the API smoke check. The upgrade created its own staging backup before migration. Production root returned HTTP 200 after the release gate.
- 2026-07-25: Studio theme preview is available at `/studio/themes/{id}/preview` without activating the theme. Public HTTPS staging is live at `https://staging.dizgecms.digitaltamam.com`, served from `/var/www/dizgecms-staging` with separate SQLite storage. HTTP redirects to HTTPS; the Let’s Encrypt certificate expires 2026-10-23 and Certbot renewal is configured. The staging upgrade, route, verify and benchmark gates passed.
