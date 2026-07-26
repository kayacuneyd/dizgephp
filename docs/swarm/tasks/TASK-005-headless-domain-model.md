# Task: TASK-005 — Headless domain model: site settings and navigation

## Goal

Create the single-source-of-truth SQLite model required by both classic themes and headless
frontends: site settings, menus and ordered menu items.

## Scope

- One CMS migration for `site_settings`, `menus` and `menu_items`.
- A dedicated repository for validated reads/writes and deterministic menu ordering.
- Seed-free defaults: an empty installation must return safe, predictable values.
- Isolated repository/migration regression tests.

## Non-goals

- Studio UI, public routes, REST response shaping, CORS, preview tokens and frontend rendering.
- Do not modify existing content, media or authentication tables.

## Contract

- Settings are a small allowlisted key/value set: `site_name`, `site_description`, `home_page_id`,
  `logo_media_id`, `favicon_media_id`, `social_links_json`.
- A menu has a unique `location` (initially `primary` and `footer` are valid locations).
- A menu item is one of `content`, `url` or `section`, with a label, optional content reference or
  HTTPS/local URL, and a zero-based order. Nested menus are deferred from the MVP.
- Deleting a referenced content item must not silently delete navigation; stale items are omitted
  from public output by the API agent and surfaced in Studio later.

## Acceptance criteria

- Fresh SQLite migrations apply and roll back cleanly.
- Repository reads produce deterministic order and do not expose arbitrary setting keys.
- Invalid menu locations, item kinds and unsafe URL schemes are rejected.
- New test passes through `sh bin/verify`.

## Exclusive file ownership

| Path | Sole writer |
| --- | --- |
| `database/migrations/20260725_create_headless_site_navigation.php` | TASK-005 implementer |
| `src/Dizge/Cms/SiteRepository.php` | TASK-005 implementer |
| `tests/headless_domain_test.php` | TASK-005 implementer |
| `bin/verify` (one test registration line only) | TASK-005 implementer |
| `docs/swarm/handoffs/TASK-005-implementer.md` | TASK-005 implementer |

## Dependencies and handoff

TASK-006 consumes only the documented repository methods and migration schema. Write the method
signatures and test evidence into the handoff; do not edit `routes/api.php`.
