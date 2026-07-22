# ADR-0003: Session-backed authentication, CSRF and deny-by-default authorization

- Status: Accepted
- Date: 2026-07-22

## Context

Cookie-backed server-rendered applications need defenses against session fixation, cross-site request
forgery and accidental authorization gaps. The framework must not assume an application’s user table
or password schema.

## Decision

- `Session` enables strict IDs, HttpOnly cookies and SameSite=Lax; it marks cookies Secure on HTTPS
  or when `DIZGE_SESSION_SECURE=1` is configured.
- `Auth` stores only a scalar identity id plus non-sensitive authorization attributes, regenerating
  the session id on login and logout.
- `Csrf` uses a session-bound 256-bit synchronizer token and `hash_equals` verification.
- `Gate` is explicit and deny-by-default. Applications register policies using `define()`.
- `VerifyCsrf` is global for state-changing browser routes. `RequireAuth` remains explicit per
  protected route so public and protected ownership stays visible.

## Consequences

- Credential lookup, password hashing, MFA and user persistence remain application/domain code until
  a reviewed identity-provider contract exists.
- A route author must consciously attach authentication middleware; CSRF protection is not left to
  route-author memory. Future webhook exemptions must be explicit and security-reviewed.
- API token authentication is a separate future boundary and must not weaken browser-session rules.
