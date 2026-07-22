# DizgePHP New Project Spec

## Goal

Build a performant, secure-by-default, AI-first PHP 8.1+ application framework that works on
ordinary shared hosting and complements CKCSS and DevinimJS.

## Users And Jobs

- PHP developers building server-rendered applications without mandatory Composer or Node.js.
- AI coding agents that need explicit, compact contracts for routes, views, data and UI components.
- Agencies deploying by FTP, Git or a basic Nginx/Apache virtual host.

## MVP Scope

- Explicit `routes/web.php` router with GET/POST and `{parameter}` routes.
- Immutable request, explicit response, centralized error boundary and PHP view rendering.
- Secure DevinimJS adapter: validated component props, escaped attributes, callback-only child HTML,
  deduplicated external component modules.
- CLI syntax and core-behavior verification.

## Implemented After MVP

- PDO database boundary, guarded query builder, explicit transactions and PHP migrations.
- Data threat model, integration tests and repeatable router/query microbenchmarks.
- Session identity, CSRF tokens, deny-by-default authorization policies and explicit route middleware.

## Next

- Validation and rate limiting; password/user-provider contract after its own threat model.
- External bridge browser module with an allowlisted patch protocol.
- Cache, mail, database/file jobs with cron workers, Studio patterns and optional file routes.

## Out Of Scope For MVP

- ORM magic, automatic CRUD persistence, WebSocket daemon, implicit file routes, authentication
  and database connectivity before their security contracts are designed.

## Pages And Flows

- `/`: framework proof page with a server-rendered DevinimJS counter.
- `/health`: machine-readable health response.

## Data And Integrations

The core uses PDO for MySQL/SQLite when configured with `DIZGE_DB_DSN`. DevinimJS modules are
self-hosted or loaded from a pinned path selected with `dv_path()`.

## Non-Functional Requirements

- PHP 8.1+, no runtime package dependency, Apache/Nginx shared-hosting compatibility.
- Explicit APIs, safe HTML escaping, no inline bridge JavaScript.
- Every later module needs unit/integration/security tests and a benchmark.

## Stack Direction

Pure PHP 8.1+ with PDO planned for data access. No Composer requirement; internals use namespaces
while `dizge.php` offers a small global facade for ergonomic templates.

## Launch Criteria

- `bin/verify` passes.
- `/` returns server-rendered HTML and `/health` returns JSON under `php -S`.
- The adapter rejects unknown components, props and raw child strings.

## Assumptions

- Developers can configure document root as `public/` or use the included Apache rewrite file.
- DevinimJS assets are copied to `/assets/devinim/` or supplied from a pinned release URL.

## Open Questions

- Define the security and response contract for bridge requests before implementation.
- Decide the migration file format after PDO/query-builder design.
