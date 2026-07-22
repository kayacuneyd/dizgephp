# ADR-0004: Explicit controllers and official modules

- Status: Accepted
- Date: 2026-07-22

## Decision

Dizge routes remain explicit. `routes/web.php` serves HTML and `routes/api.php` serves `/api/v1`;
both may call the same action/service but never negotiate representation implicitly. Controllers are
resolved only through explicit container bindings, with no reflection autowiring. Cache, jobs, mail
and API tokens are official modules rather than core magic.

## Consequences

- Humans and agents can inspect route ownership, middleware and response type by reading one file.
- Shared-hosting deployments work from Git/ZIP without Composer; optional package distribution can
  be added later.
- File routing, ORM magic, Redis, WebSockets and an embedded agent server stay opt-in future work.
