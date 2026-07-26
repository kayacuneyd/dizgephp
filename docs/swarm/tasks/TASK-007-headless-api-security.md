# Task: TASK-007 — Headless API security baseline

## Goal

Define and test the boundary between public headless reads, private drafts and future preview
access before cross-origin frontend use is enabled.

## Scope

- Threat model for public API, CORS, media metadata and signed preview.
- Regression tests for draft leakage and unsafe cross-origin assumptions using current routes.
- A preview-token contract proposal: expiry, content binding, single purpose and revocation path.

## Non-goals

- Implementing preview or CORS middleware; those are a later integration task after the contract
and test cases are reviewed.

## Acceptance criteria

- Threat model identifies assets, trust boundaries, abuse cases and mitigations.
- Tests prove draft/review/approved content cannot appear through public content routes.
- No broad `Access-Control-Allow-Origin: *` recommendation for authenticated or preview traffic.

## Exclusive file ownership

| Path | Sole writer |
| --- | --- |
| `docs/headless-api-threat-model.md` | TASK-007 implementer |
| `tests/headless_api_security_test.php` | TASK-007 implementer |
| `docs/swarm/handoffs/TASK-007-implementer.md` | TASK-007 implementer |
