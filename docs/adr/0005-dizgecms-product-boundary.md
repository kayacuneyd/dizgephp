# ADR-0005: DizgeCMS product boundary

- Status: Accepted
- Date: 2026-07-23

## Decision

DizgeCMS is a reusable single-site CMS built on DizgePHP. Baltık Postası is a reference application
and theme, not the CMS core. Extensions use explicit plugin manifests and versioned contracts.
The first plugin model is local/allowlist PHP; arbitrary remote code execution, multi-tenant SaaS and
full WordPress plugin compatibility are not initial goals.

## Consequences

Core contracts must not depend on Baltık-specific tables or routes. Plugin capabilities, permissions,
migrations and public data boundaries require review, tests and benchmark evidence before release.
