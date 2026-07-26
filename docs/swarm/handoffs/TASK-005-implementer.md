# Handoff: TASK-005 — Headless domain model

## Status

Complete and integrated into the current working tree.

## Delivered contract

- Migration `20260725_create_headless_site_navigation` creates `site_settings`, `menus` and
  ordered `menu_items`.
- `Dizge\Cms\SiteRepository` is the only new persistence boundary for this model.
- `settings()` always returns an allowlisted complete shape:
  `site_name`, `site_description`, `home_page_id`, `logo_media_id`, `favicon_media_id`,
  `social_links`.
- `saveSettings()` rejects unknown keys and invalid values.
- `menu('primary'|'footer')` returns `{location, items}` in stable order.
- `replaceMenu()` accepts `content`, `url` and `section` items. URL items must be local or HTTPS;
  section items are restricted to fragment identifiers. Content deletion yields a nullable stale
  reference through `ON DELETE SET NULL`, never an implicit navigation deletion.

## Evidence

- `php tests/headless_domain_test.php` passed.
- `sh bin/verify` passed, including the registered headless-domain test.
- `git diff --check` passed.

## Integration notes for TASK-006

- Public API must not return a `content` item whose `content_id` is null or whose content is not
  published.
- No settings value should be treated as a filesystem path, secret or arbitrary config key.
- Site and menu API output may use this repository without new schema work.
