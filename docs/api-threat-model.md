# API token and JSON boundary threat model

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

## Residual responsibility

Applications choose token subject ownership, expiration, revocation UI, per-route scopes and TLS.
JWT, OAuth and API token UI are not core behavior.
