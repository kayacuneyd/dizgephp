# API token and JSON boundary threat model

Content API contract:

- GET /api/v1/content/page and GET /api/v1/content/article expose published content only.
- GET by type/slug exposes one published item.
- POST /api/v1/content and POST /api/v1/content/{id} require content.write.
- POST /api/v1/content/{id}/delete requires content.delete.

## Assets and trust boundaries

- Bearer token secrets, token scopes, JSON request bodies and public API availability.
- `/api/v1` is a separate trust boundary from session-backed browser routes.

## Required controls

| Risk | Control |
|---|---|
| Token database disclosure | Store only SHA-256 token hashes; show a secret only at issuance. |
| Over-privileged token | Require explicit abilities; middleware checks the requested ability. |
| Browser CSRF confusion | API routes bypass session CSRF and must use explicit bearer middleware for protected writes. |
| JSON parser/error drift | Invalid JSON and validation failures use stable `application/problem+json` responses. |
| Enumeration and abuse | Attach API throttle middleware to public endpoints; use token-specific keys when an identity is available. |
| Secret leakage | Never log Authorization headers or return a token after issuance. |
| Content mutation | POST create/update/delete routes require content.write or content.delete; payloads are validated and sanitized server-side. |
| Public content leak | Public list and slug routes query only published rows with a non-null published_at. |

## Residual responsibility

Applications choose token subject ownership, expiration, revocation UI, per-route scopes and TLS.
JWT, OAuth and API token UI are not core behavior.
