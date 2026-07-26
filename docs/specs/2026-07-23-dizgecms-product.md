# DizgeCMS Product Spec

## Goal

Build a reusable, build-free PHP CMS for single-site shared hosting deployments. DizgeCMS combines
an editor-friendly admin, developer-facing CLI/contracts, installable themes and allowlisted plugins.
The first public product page is `https://dizgecms.digitaltamam.com`.

## Users And Jobs

- Site owners: install a site, select a theme, edit content and manage SEO without coding.
- Developers/agencies: extend a site with explicit plugins, themes, migrations, routes and CLI commands.
- Publishers: draft, review, approve, schedule and publish sourced content.
- AI/search systems: consume structured, public-only, citation-ready content context.

## MVP Scope

- DizgePHP full-stack foundation with explicit routes, PDO/SQLite, CSRF and deny-by-default authorization.
- CMS core extraction from the reference Studio: content, workflow, revisions, media, FTS5 and SEO contracts.
- Local/allowlist plugin discovery and lifecycle with manifest validation.
- Starter theme contract and the DizgeCMS promotional site.
- WordPress REST/WXR import with published URL and metadata preservation.
- Technical SEO, structured data, public context and regional entity foundations.
- Hybrid headless MVP: versioned public REST contract plus a DevinimJS starter frontend;
  classic Studio/theme publishing remains supported.

## Next

The productization sequence is documented in
`docs/dizgecms-wordpress-alternative-roadmap.md`: installer/default site and headless API
contract first, then site settings/menus, media library/editor, DevinimJS starter, theme
management, users/roles, and release/update packaging.
Plugin/theme management UI and backup UI are included in that sequence; analytics is deferred.
Multi-site SaaS, WordPress plugin/theme compatibility and PHP sandboxing remain out of scope.

## Stack Direction

- PHP 8.1+, DizgePHP, SQLite by default, no required Composer or Node.js runtime.
- CKCSS for presentation and DevinimJS for progressive enhancement.
- Public document root: `public/`; private state: `storage/`.

## Launch Criteria

- `bin/dizge verify` and `bin/dizge benchmark --check` pass.
- Promotional routes have correct title, description, canonical, robots and sitemap output.
- A sample plugin can register a migration, route, admin item and CLI command without editing core files.
- Draft/review/approved content never appears in public HTML, sitemap, FTS or context API.
