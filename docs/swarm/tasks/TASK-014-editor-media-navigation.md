# TASK-014 — P0 authoring experience

## Goal

Let a non-technical editor create, revise and publish a page with structured blocks, managed
media and an ordered/nested menu without writing HTML.

## Ownership

- `src/Dizge/Cms/Block*`, `src/Dizge/Cms/ContentRepository.php`, related migrations
- `routes/demo.php`, Studio content/media/menu views and their assets
- focussed CMS regression tests

## Acceptance

- Blocks support paragraph, heading, list, link, image, quote and divider; stored content remains
  sanitized HTML at the public rendering boundary.
- Media is searchable, filterable, selectable from the editor and safely deletable only when not
  referenced by content.
- Primary and footer menus support ordering, rename, deletion, custom URL/content entries and one
  parent-child nesting level.
- Existing rich HTML content and public routes remain compatible.

## Security gate

Validate upload ownership/authorization, MIME and size limits, HTML sanitization, CSRF, and
referential checks before a deletion.
