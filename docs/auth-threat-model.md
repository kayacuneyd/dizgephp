# Authentication and CSRF threat model

## Assets

- Authenticated session identity and session id.
- State-changing form actions and authorization decisions.
- CSRF token secrecy and integrity.

## Threats and mitigations

| Threat | Current mitigation | Residual responsibility |
|---|---|---|
| Session fixation | Regenerate id on login/logout; strict session ids | Configure HTTPS and secure cookie flag in production |
| Session theft | HttpOnly, SameSite=Lax, Secure-on-HTTPS cookie | TLS, XSS prevention, user-session revocation policy |
| Cross-site POST | Global 256-bit synchronizer token middleware, constant-time comparison | Keep future webhook/API exemptions explicit and reviewed |
| Missing authorization | Gate denies unregistered abilities | Register ownership/role policy for every protected action |
| Credential stuffing | Not solved in core | Add rate limiting, audit logging and MFA in later modules |
| Password compromise | Not handled by generic Auth | Use `password_hash()`/`password_verify()` and domain-specific rehash policy |

## Explicit non-goals

The core does not implement password storage, OAuth, JWT, MFA or a user schema. Those require a
separate threat model and must not be implied by `auth()->login()`.
