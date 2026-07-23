# ADR-0006: Global default rate limit

- Status: Accepted
- Date: 2026-07-23

## Context

Rate limiting in DizgePHP is entirely opt-in today: `Dizge\Security\RateLimiter` and
`Dizge\Security\Throttle` exist and work (`routes/demo.php`'s `/demo/api/context` attaches its
own 30-per-60s `Throttle`, and `LoginService` throttles login attempts 5-per-300s per identifier),
but nothing protects a route whose author forgot, or never knew, to attach one.
`docs/auth-threat-model.md` already lists "credential stuffing: not solved in core, add rate
limiting... in later modules" as a residual risk, and `docs/api-threat-model.md` leaves
throttling as an explicit per-route choice for public API endpoints. DizgePHP targets cheap
shared hosting (`AGENTS.md`), where CPU, process count and disk I/O are the scarcest resources; a
single client hammering any unprotected route (a slow search endpoint, a form post, a page with an
expensive query) can exhaust those resources for every other tenant on the box. `AGENTS.md`
requires a threat model, tests and performance measurement before shipping a security-sensitive
module; this ADR and `docs/auth-threat-model.md`'s update are that record, written before the
implementation.

## Decision

- `Dizge\Application`'s constructor builds one `Throttle` instance backed by the existing
  `RateLimiter` (`storage/rate-limits`, the same directory `/demo/api/context` and
  `LoginService` already use) and adds it to `$this->globalMiddleware` alongside the existing
  `VerifyCsrf` — **only when `config/app.php`'s `rate_limit.enabled` is `true`; it is `false` by
  default.** This was a mid-flight revision, not the original plan: the first implementation made
  it unconditional, and benchmarking it (see "Consequences") showed a large, real disk-I/O cost on
  every single request — directly at odds with this project's shared-hosting performance goals.
  Rather than ship that cost onto every DizgePHP app by default, the safety net is a one-line
  config flag away (`'rate_limit' => ['enabled' => true]`) and costs nothing until an operator
  turns it on. When enabled, every route registered through `get()`/`post()` — including the
  internal `POST /_dizge/bridge` route — carries it; a route author does not have to remember to
  attach `Throttle` for the baseline safety net to apply once the operator has opted in.
- The bucket key is `'global:' . ($request->header('remote-addr') ?? 'unknown')` — **client IP
  alone**, not IP+path. One bucket covers a client's traffic across the *entire* application. This
  is a deliberate difference from `/demo/api/context`'s own `'context:' . ip` key: a per-route key
  for a global default would multiply `storage/rate-limits/` file count by the number of distinct
  routes a client touches, for no real benefit — the goal here is "stop one client from exhausting
  the host," not "budget each route separately." Routes that need a *stricter* route-specific
  budget keep using their own explicit `Throttle` (unchanged), which composes with, and is
  independent of, this global one — both fire on their own schedule against their own key.
  `request->header('remote-addr')` already resolves through `TrustedProxy` (this session's
  earlier work), so the key is the real client IP behind an operator-configured trusted reverse
  proxy, not the proxy's own address, and nothing changes for a deployment with no
  `DIZGE_TRUSTED_PROXIES` configured.
- Default limit: **300 requests per 60-second window per IP** (5 req/s sustained), configurable
  via a new `config/app.php` key:
  ```php
  'rate_limit' => ['max' => 300, 'seconds' => 60],
  ```
  read with `app()->config()->get('app.rate_limit.max', 300)` / `...seconds', 60)` — the same
  `Config::get()` dot-path pattern `FrameworkStatus` already uses for `app.name`. Reasoning for
  300/60: static assets under `public/assets/` never reach PHP (`public/.htaccess` serves real
  files directly; only a route miss falls through to `index.php`), so a PHP-routed hit already
  corresponds to a page navigation, form post or API call, not an asset — a real user's browsing
  session stays a small fraction of 300/min even on an interactive page. 300/min is generous
  enough that it should not need per-app tuning for typical traffic, strict enough that a
  single-IP flood is capped at roughly 5 requests worth of PHP+disk work per second instead of
  whatever the host's process pool allows unbounded. It is intentionally *looser* than
  `/demo/api/context`'s 30/60 (a search endpoint doing FTS queries deserves its own tighter
  budget) and looser than login's 5/300 (a credential-guessing surface deserves a much tighter,
  identifier-keyed budget) — this is a coarse, app-wide backstop, not a replacement for either.
- Opt-out/override: `Application::get()` and `Application::post()` gain a fourth, optional,
  named-argument-friendly parameter, `bool $withoutGlobalRateLimit = false`. Passing `true` removes
  *only* the global `Throttle` instance from that one route's middleware chain (by reference
  identity, so it can never accidentally strip a different middleware); `VerifyCsrf` and any
  explicit per-route middleware are unaffected. Mirroring ADR-0003's own consequence ("future
  webhook exemptions must be explicit and security-reviewed"): this parameter defaults to `false`,
  requires a deliberate `true` at the call site (visible in `git diff`/review, not a config toggle
  a route can silently inherit), and is reserved for a route with a genuine reason not to share the
  global bucket — e.g. a route that already enforces its own differently-keyed `Throttle`, or a
  trusted high-volume integration authenticated by another mechanism. No route in this codebase
  uses it yet; `tests/global_rate_limit_test.php` exercises it directly to prove the mechanism
  itself works, independent of any call site.

## Consequences

- **With the default (`rate_limit.enabled = false`), there is no cost at all**: `Application`'s
  constructor never builds the `Throttle`/`RateLimiter`, and `$globalMiddleware` is exactly what
  it was before this ADR (`[VerifyCsrf]`). `sh bin/benchmark --check` confirms every metric,
  including `controller_dispatch` and `router_dispatch_at_scale`, is unaffected at the default.
- **When an operator sets `rate_limit.enabled = true`, every request pays one file lock + disk
  read + disk write** (`RateLimiter::hit()`) on top of routing and CSRF, and that cost is large,
  not marginal: measured with the feature unconditionally on (before this ADR was revised to be
  opt-in), `controller_dispatch` went from a 0.0025 ms/op baseline to a 0.291 ms/op median
  (roughly 90x the 15%-gate limit), and `router_dispatch_at_scale` went from 0.136 to 0.339 ms/op
  (roughly 2.3x the limit). Full before/after numbers for every metric are recorded in
  `docs/swarm/handoffs/TASK-004-implementer.md`. This is the same file-backed trade-off
  `RateLimiter` already makes for login throttling and `/demo/api/context` — this ADR does not
  introduce a new storage mechanism — but a *global, every-request* call site made that cost
  unavoidable when the feature is on. An operator who enables `rate_limit.enabled` is making an
  informed trade: real per-request disk I/O in exchange for a host-wide abuse safety net. A
  lower-cost backend (e.g. an in-process/shared-memory counter with periodic flush) remains a
  legitimate future improvement — it would need its own threat model as a change to a primitive
  `LoginService` and `/demo/api/context` also depend on — but is not required to ship this ADR
  safely now that the cost is opt-in rather than mandatory.
- A shared IP (NAT, corporate proxy, campus network) shares one global bucket across every visitor
  behind it. This is inherent to any IP-keyed limiter, already true of `/demo/api/context`'s own
  Throttle, and is why the limit is generous (300/min) rather than tight.
- `DIZGE_TRUSTED_PROXIES` must be configured accurately in any deployment behind a reverse proxy or
  CDN; if it is not, every request appears to originate from the proxy's IP and shares one bucket
  application-wide, effectively rate-limiting the whole app to 300 req/min regardless of client
  count. This is the same operational responsibility `TrustedProxy` already documented for session
  cookie security; it is not new to this change, but this change raises its stakes from "cookie
  behavior" to "site-wide availability" and should be called out to the maintainer explicitly.
- The `withoutGlobalRateLimit` opt-out is new API surface on `Application::get()`/`post()`; any
  future use of `true` is a security-relevant decision and should get the same review scrutiny
  ADR-0003 asked for on future CSRF exemptions.
- Applications that need a per-IP budget different from 300/60 globally change one config value;
  applications that need it different per-route already have the tools (their own `Throttle`,
  optionally combined with `withoutGlobalRateLimit: true` if they want to fully replace rather than
  compose with the global default).
