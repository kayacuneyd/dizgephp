# Authentication and CSRF threat model

## Assets

- Authenticated session identity and session id.
- State-changing form actions and authorization decisions.
- CSRF token secrecy and integrity.
- Host-wide availability of PHP/disk resources on shared hosting (one client must not be able to
  exhaust them for every other tenant).

## Threats and mitigations

| Threat | Current mitigation | Residual responsibility |
|---|---|---|
| Session fixation | Regenerate id on login/logout; strict session ids | Configure HTTPS and secure cookie flag in production |
| Session theft | HttpOnly, SameSite=Lax, Secure-on-HTTPS cookie | TLS, XSS prevention, user-session revocation policy |
| Cross-site POST | Global 256-bit synchronizer token middleware, constant-time comparison | Keep future webhook/API exemptions explicit and reviewed |
| Missing authorization | Gate denies unregistered abilities | Register ownership/role policy for every protected action |
| Credential stuffing | `LoginService` throttles login attempts 5-per-300s per identifier; the optional global default `Throttle` (see below), when enabled, adds a coarse 300-per-60s-per-IP backstop on top | Applications with a high-value login surface should still add audit alerting and MFA; the core stops neither slow, distributed credential stuffing nor multi-account attacks from one IP, and the global backstop only applies at all once an operator enables it |
| Password compromise | Not handled by generic Auth | Use `password_hash()`/`password_verify()` and domain-specific rehash policy |
| Single-client resource exhaustion (any unprotected route, not just auth) | `Application` can register a global default `Throttle` (`Dizge\Security\RateLimiter` + `Throttle`, ADR-0006) in `$this->globalMiddleware`, keyed on `request->header('remote-addr')` alone (one bucket per client, whole app) — **opt-in via `config/app.php`'s `rate_limit.enabled = true`, off by default** because the file-backed limiter adds a measured, large per-request disk-I/O cost (ADR-0006 "Consequences") that this project does not want to impose on every DizgePHP app unconditionally. Default 300 requests/60s per IP once enabled, configurable via `rate_limit.max`/`rate_limit.seconds`. Composes with (does not replace) any route's own stricter `Throttle`, e.g. `/demo/api/context`'s 30-per-60s | **An application that never sets `rate_limit.enabled = true` has no global backstop at all** — this is a deliberate default, not an oversight, but it means the residual risk below is the *default* posture, not just an edge case; operators on shared/contended hosting should evaluate enabling it. When enabled, `request->header('remote-addr')` is only the real client IP if `DIZGE_TRUSTED_PROXIES` is configured correctly for the deployment's reverse proxy/CDN (`TrustedProxy`) — a misconfigured or missing value collapses every client behind that proxy into one shared bucket. A route with a genuine reason not to share the global bucket must opt out explicitly via `withoutGlobalRateLimit: true` on `get()`/`post()` (ADR-0006); this parameter is a security-reviewed exemption, not a default any route should reach for |

## Explicit non-goals

The core does not implement password storage, OAuth, JWT, MFA or a user schema. Those require a
separate threat model and must not be implied by `auth()->login()`. The global default `Throttle`
is opt-in (`config/app.php`'s `rate_limit.enabled`) and, even when enabled, is a coarse, app-wide
backstop, not a distributed-attack or bot-detection system: it does nothing against a large
botnet spreading load across many IPs, and (like any IP-keyed limiter) shares one bucket across
every client behind a NAT or corporate proxy. **An application that leaves it disabled (the
default) has no core protection at all against single-client resource exhaustion on an otherwise
unprotected route** — this is the explicit, documented trade-off of keeping the framework's
default request cost at zero.
