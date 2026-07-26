# ADR-0010: Studio 0.2 content boundary

- Status: Accepted
- Date: 2026-07-26

`page`, `article` and `service` are CMS-core content types. Page composition is a fixed,
validated block-data schema; themes render it and do not introduce arbitrary editor templates.
Revisions preserve the corresponding block JSON. Content-to-content links are explicit references.

KA Teknoloji is not a core dependency. Any KA-specific content must be installed only through an
explicit, idempotent example/installer seed command and must never be a mandatory migration.

Run `php bin/cms-demo-seed ka-teknoloji` only after creating an administrator. It is intentionally
separate from `migrate` and `install`.

## Consequences

The SQLite migration is forward-only because narrowing a content-type constraint can destroy
published data. Studio routes retain `RequireAuth` plus `cms.manage`; contact PII readers are
additionally administrator-only and audited.
