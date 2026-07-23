# Data module threat model

## Assets

- Database credentials, schema integrity and application records.
- Migration ledger integrity.
- Query availability and predictable resource use.

## Trust boundaries

- HTTP input is untrusted and must be validated before becoming a query value.
- Table/column names cannot be parameterized, so they are syntax-allowlisted by `Query`.
- Migration files are trusted deploy-time source code and must never be selected from a request.
- DSN credentials come from environment/server configuration and never from web input.

## Current mitigations

- PDO exception mode, native prepares and bound values.
- No `raw()` query-builder escape hatch.
- `ASC`/`DESC` and comparison operators are allowlisted.
- Broad update/delete operations fail without a predicate.
- Migration ids are recorded and duplicate runs are skipped.

## Residual risks and required future controls

- Identifier syntax validation cannot enforce per-table authorization; repositories must own
  application allowlists until a policy module exists.
- Query limits prevent accidental unbounded builder reads but do not replace pagination policy.
- Database privileges must be least-privilege at deployment time.
- Migration sources need deployment review and a production backup/rollback policy.
