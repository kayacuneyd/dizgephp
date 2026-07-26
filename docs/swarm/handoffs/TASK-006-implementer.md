# Handoff: TASK-006 — Headless public REST contract

## Status

Complete and integrated into the current working tree and live database schema.

## Delivered endpoints

- `GET /api/v1/site`
- `GET /api/v1/menus/primary` and `GET /api/v1/menus/footer`
- `GET /api/v1/media/{id}`
- `GET /api/v1/search?q=...`
- Existing resource aliases remain: pages and articles collection/detail routes.

All endpoints keep the `{data: ...}` envelope. Content responses provide relative and canonical
URLs. Media metadata deliberately excludes private `stored_name` values. Menu output omits a
stale or unpublished content target; public site output omits unpublished configured home pages.

## Evidence

- `php tests/headless_api_contract_test.php` passed.
- `sh bin/verify` passed.
- `sh bin/benchmark --check` passed:
  `controller_dispatch 0.0027`, `cache_round_trip 0.0002`,
  `file_cache_round_trip 0.1859`, `router_dispatch_at_scale 0.1080`,
  `job_enqueue 0.1594` ms/op.
- Live smoke on `https://dizgecms.digitaltamam.com` passed for site, primary menu, pages,
  articles and missing-media problem responses.

## Controlled production schema change

- Backup before migration: `storage/backups/dizgecms-20260725-headless-pre-migration.sqlite`.
- Applied migration: `20260725_create_headless_site_navigation`.
- The migration adds empty settings/navigation tables only; no existing content, users or media
  records were modified.

## Follow-up boundaries

- TASK-007 owns CORS and signed preview security design/tests.
- TASK-008 owns only `examples/devinim-headless/` and must consume the endpoints above without
  browser-held privileged credentials.
