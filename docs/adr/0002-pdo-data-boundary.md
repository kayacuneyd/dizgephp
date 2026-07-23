# ADR-0002: PDO data boundary and guarded query builder

- Status: Accepted
- Date: 2026-07-22

## Context

The framework needs database access without inventing an opaque ORM, accepting raw client SQL or
making accidental broad writes easy. Shared hosting commonly exposes PDO for MySQL and SQLite.

## Decision

`Database` wraps a supplied PDO connection and forces exception mode, associative fetches and native
prepared statements. `Query` accepts only validated identifiers and a small allowlist of operators;
all values are positional parameters. `update()` and `delete()` require an explicit predicate.

Migrations are explicit PHP objects with `up()` and `down()` methods. Their SQL is a reviewed,
trusted-code boundary—not a request-data API.

## Consequences

- Developers retain visibility into tables, predicates and transaction boundaries.
- Complex SQL remains possible in repositories, but needs review and tests.
- Schema authorization, tenant scoping, validation and business authorization remain application
  responsibilities and will be addressed by later modules.
