# Task: TASK-006 — Headless public REST contract

## Goal

Expose a versioned, public-only REST contract for the shared CMS content, site settings,
navigation and media metadata without changing classic Studio behavior.

## Scope

- Extend `routes/api.php` only after TASK-005's repository contract is available.
- Keep existing `/api/v1/content/{type}` routes as compatibility endpoints.
- Implement resource endpoints: `/api/v1/site`, `/api/v1/menus/{location}`, `/api/v1/pages`,
  `/api/v1/pages/{slug}`, `/api/v1/articles`, `/api/v1/articles/{slug}`, `/api/v1/media/{id}`.
- Return a stable `{data: ...}` envelope, canonical URLs, public media URLs and SEO fields.
- Add focused API contract tests.

## Non-goals

- API writes, Studio UI, cross-origin policy, preview tokens, GraphQL and webhooks.

## Acceptance criteria

- Published content is returned; drafts/review/approved/archived content is never returned.
- The site endpoint has no secret or filesystem data.
- Missing media/content receives the established API problem response.
- Contract tests pass and existing API consumers remain compatible.

## Exclusive file ownership

| Path | Sole writer |
| --- | --- |
| `routes/api.php` | TASK-006 implementer |
| `tests/headless_api_contract_test.php` | TASK-006 implementer |
| `bin/verify` (one test registration line only) | TASK-006 implementer |
| `docs/api-headless-contract.md` | TASK-006 implementer |
| `docs/swarm/handoffs/TASK-006-implementer.md` | TASK-006 implementer |

## Dependency

Do not begin implementation until TASK-005 has documented and tested its repository contract.
