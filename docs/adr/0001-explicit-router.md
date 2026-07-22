# ADR-0001: Explicit route registration

- Status: Accepted
- Date: 2026-07-22

## Context

DizgePHP needs a routing model that is secure, benchmarkable and easy for both humans and AI
agents to inspect. File-based routing offers convenience but makes behavior depend on filesystem
conventions and deployment paths.

## Decision

The core uses imperative route declarations in `routes/web.php` through `app()->get()` and
`app()->post()`. Handlers return explicit `Response` objects. File-based routing may be added later
as an opt-in adapter that compiles to these same registrations.

## Consequences

- Route ownership, middleware and authorization remain greppable and testable.
- Shared-hosting rewrite rules remain a deployment concern, not an implicit application behavior.
- The core avoids an early convention-heavy abstraction while preserving an optional convenience
  path for later Studio tooling.
