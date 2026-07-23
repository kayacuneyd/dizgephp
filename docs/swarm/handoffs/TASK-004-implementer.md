# Handoff: TASK-004 — Implementer

## Status

Complete, with one item flagged for explicit human decision (see "Open questions and risks" —
the performance regression is real, large, and I did not resolve it myself since resolving it
would mean rewriting the shared `RateLimiter` primitive, which is out of this task's scope and
used elsewhere).

## Inputs reviewed

`docs/swarm/tasks/TASK-004-global-rate-limit-default.md` (full task spec), `AGENTS.md`,
`docs/adr/0003-session-csrf-authorization.md`, `docs/adr/0004-full-stack-explicit-modules.md`,
`docs/adr/0005-devinim-bridge-protocol.md` (format precedent), `docs/auth-threat-model.md`,
`docs/api-threat-model.md`, `docs/operations-threat-model.md` (read all three to pick an owner),
`src/Dizge/Application.php` (full), `src/Dizge/Security/RateLimiter.php`,
`src/Dizge/Security/Throttle.php`, `src/Dizge/Security/LoginService.php` (existing throttle
numbers for style precedent), `src/Dizge/Http/TrustedProxy.php`, `src/Dizge/Http/Request.php`,
`src/Dizge/Http/Router.php` (confirmed route matching runs before middleware, relevant to the
benchmark analysis below), `src/Dizge/Config.php` (dot-path config lookup), `routes/demo.php`
(`/demo/api/context`'s existing Throttle pattern), `routes/web.php`, `routes/api.php`,
`app/Actions/FrameworkStatus.php` (config-read pattern), `config/app.php`, `dizge.php`,
`public/index.php`, `public/.htaccess` (confirmed static assets never reach PHP routing),
`bin/verify`, `bench/fullstack.php`, `bench/check.php`, `bench/baseline.json`, and every existing
test file in `tests/` (to count `Application::dispatch()` calls and confirm no test would
spuriously trip a shared-storage rate-limit bucket).

**Important environment note**: this task runs in a per-agent worktree
(`/var/www/dizgephp/.claude/worktrees/agent-a71c06dbbccd5bd8a`), one of four sibling worktrees for
the four concurrent swarm tasks (confirmed via `ls .claude/worktrees/`), despite
`docs/swarm/README.md` describing a "disjoint-ownership, no worktree" plan — the actual harness
isolates each dispatched implementer into its own worktree copy regardless. All file paths below
are relative to that worktree. Early in this session I verified the worktree's copies of every
file I needed for context were byte-identical to `/var/www/dizgephp`'s copies before proceeding
(`diff` on `Application.php`, `config/app.php`, `routes/demo.php`, `bin/verify`,
`bench/baseline.json`), then did all reading/editing exclusively from the worktree path.

## Evidence and findings

### Design-first: ADR and threat model (written before implementation)

- **`docs/adr/0006-global-default-rate-limit.md`** (new, follows ADR-0003's
  Status/Date/Context/Decision/Consequences format). Key decisions recorded there:
  - One `Throttle` instance added to `Application::$globalMiddleware` alongside the existing
    `VerifyCsrf`, applied to every route via `get()`/`post()` (and the internal
    `POST /_dizge/bridge` route, which already shares `$globalMiddleware`).
  - Key is **`'global:' . ($request->header('remote-addr') ?? 'unknown')`** — IP alone, one
    bucket per client for the *whole app*, deliberately not per-route (a per-route key would
    multiply `storage/rate-limits/` file count for no benefit — that's what a route's own
    `Throttle` is already for).
  - Default **300 requests / 60 seconds per IP** (5 req/s sustained), reasoned from: static
    assets under `public/assets/` never reach PHP (`public/.htaccess`'s `!-f` rewrite condition
    serves real files directly), so a PHP-routed hit already corresponds to a page
    navigation/form post/API call, not an asset — 300/min is generous for a real browsing
    session and looser than both `/demo/api/context`'s 30/60 and `LoginService`'s 5/300
    (each deliberately tighter for its own higher-value surface). Configurable via new
    `config/app.php` key `rate_limit.max` / `rate_limit.seconds`.
  - Opt-out: `Application::get()`/`post()` gained a fourth, named-argument-friendly parameter
    `bool $withoutGlobalRateLimit = false`. `true` strips *only* the global `Throttle` instance
    (matched by reference identity, not by type, so it can never accidentally strip something
    else) from that one route's middleware chain; `VerifyCsrf` and any explicit `$middleware` are
    unaffected. Mirrors ADR-0003's own stated precedent ("future webhook exemptions must be
    explicit and security-reviewed") — defaults to `false`, requires a deliberate `true` at the
    call site, visible in review. **No route in this codebase uses it yet** — it exists as a
    documented, tested mechanism for a future route that needs it, per the acceptance criteria.
  - Consequences section explicitly documents the measured performance cost (see below) and
    leaves the baseline-update decision to the maintainer rather than deciding unilaterally.

- **`docs/auth-threat-model.md`** extended (chosen over `api-threat-model.md` and
  `operations-threat-model.md`). Reasoning: this doc already carried the exact residual-risk line
  the task's own scope quotes as motivation — "Credential stuffing | Not solved in core | Add
  rate limiting... in later modules" — and it's the doc that documents the *sibling* global
  middleware (`VerifyCsrf`) this new global `Throttle` now sits directly beside in
  `Application`'s constructor. `api-threat-model.md` already treats throttling as an explicit
  per-route API concern ("Attach API throttle middleware to public endpoints") which remains true
  and unchanged by this global, all-routes default; `operations-threat-model.md` is scoped to
  cache/job/mail infrastructure, not HTTP-layer concerns, so it was not a fit. Changes: added a
  new asset ("host-wide availability... on shared hosting"), updated the credential-stuffing row
  to reflect the new layered mitigation, added a new "Single-client resource exhaustion" row with
  a full risk/control/residual-responsibility entry (matching the existing table format), and
  added a sentence to "Explicit non-goals" clarifying the global default is a coarse backstop, not
  a bot/distributed-attack defense.

### Implementation

- **`config/app.php`**: added `'rate_limit' => ['max' => 300, 'seconds' => 60]`, read via
  `app()->config()->get('app.rate_limit.max', 300)` / `...seconds', 60)` — same dot-path
  `Config::get()` pattern `FrameworkStatus` already uses for `app.name`.
- **`src/Dizge/Application.php`**: added `use` imports for `RateLimiter`/`Throttle`; new private
  `Throttle $globalThrottle` property, constructed in the constructor (after `$this->config` is
  available) with `new RateLimiter($basePath . '/storage/rate-limits')` — the same directory
  `/demo/api/context` and `LoginService` already write to, differentiated only by key prefix
  (`global:` vs `context:`/`login:`), so no new storage location was introduced.
  `$this->globalMiddleware` is now `[new VerifyCsrf($this->csrf), $this->globalThrottle]`.
  `get()`/`post()` gained the `bool $withoutGlobalRateLimit = false` parameter and now build their
  middleware list through a new private `globalMiddlewareFor(bool): array` helper that filters
  `$this->globalThrottle` out by reference identity when the caller opts out.
- **No changes to `routes/web.php`, `routes/api.php`, or `routes/demo.php`** — confirmed by
  `git status`/`git diff` showing neither file touched. `/demo/api/context`'s own `Throttle`
  (30/60, `context:`-keyed) is unmodified and now simply composes with the new global default that
  every route gets automatically.

### Tests — `tests/global_rate_limit_test.php` (new, registered in `bin/verify`)

Every `Application` instance in this test uses its own temp basePath (own `config/app.php`, own
`storage/rate-limits` directory) so the test can never contend with, or pollute, the real repo's
shared `storage/rate-limits` directory that other test files' `new Application(dirname(__DIR__))`
calls and the process-wide `app()` singleton also write to (see "cross-test contamination"
analysis below). Four scenarios, each mapped to an acceptance criterion:

1. **Global default protects an unprotected route**: a route with no `Throttle` of its own,
   global limit configured to 3/60s — first 3 requests from one IP return 200, the 4th returns
   429 with a `Retry-After` header.
2. **A different IP is a separate bucket**: immediately after client A's bucket is exhausted,
   client B's first request to the same route still returns 200.
3. **Composability with a route's own stricter Throttle**: constructs a `Throttle` using the
   *exact* construction `/demo/api/context` uses (`RateLimiter` over `storage/rate-limits`, key
   `'context:' . ip`, 2/60s for a fast test) attached to a route, alongside a generous (300/60)
   global default. Proves the route's own limit trips at request 3 (well before the global
   default would), and that the *same client* hitting a *different* route right after is
   unaffected by the context-style route's exhaustion (different key) while still being governed
   by the shared, not-yet-exhausted global bucket (proving "one bucket per client for the whole
   app," not per-route).
4. **The `withoutGlobalRateLimit` opt-out**: with global limit 2/60s, a default route throttles at
   request 3; a sibling route registered with `withoutGlobalRateLimit: true` keeps returning 200
   for 5 more requests from the same already-throttled client. Also asserts, via
   `Application::routes()`, that the default route carries 2 global middlewares
   (`VerifyCsrf` + global `Throttle`) and the opted-out route carries exactly 1
   (`VerifyCsrf` only) — a structural check that the opt-out removes exactly the intended
   middleware, nothing more, nothing less.

**Why this test builds a synthetic route mirroring `/demo/api/context`'s pattern rather than
requiring `routes/demo.php` directly**: `routes/demo.php` registers routes on the process-wide
`app()` singleton, whose basePath is hardcoded to the real repo root at `Application::boot()`
time (in `dizge.php`) — there is no way to redirect it to an isolated temp directory without
touching `routes/demo.php` or `dizge.php` (both out of this task's file ownership). Driving that
singleton's real, shared `storage/rate-limits` bucket into 429 territory to prove the limit works
would have risked tripping the *same* global bucket for every other test file that also dispatches
through `new Application(dirname(__DIR__))` without a `remote-addr` header (they'd all share the
`global:unknown` fallback key) if run within the same 60-second window during `bin/verify` — every
other existing test file in this repo already uses this same "build my own isolated `Application`
and register synthetic routes" idiom for exactly this reason (`core_test.php`, `auth_test.php`,
`bridge_test.php` never require `routes/*.php` either). I verified empirically that this concern
was real and not just theoretical: I counted `Application::dispatch()` calls across every existing
test file before writing mine (~19 total, none setting `remote-addr`) to confirm my chosen test
limits wouldn't collide with them, then ran the full `sh bin/verify` suite after my change and
confirmed zero cross-test interference (see next section).

### `sh bin/verify` — full tail (after implementation)

```
DizgePHP core tests passed.
DizgePHP config tests passed.
DizgePHP trusted-proxy tests passed.
DizgePHP security tests passed.
DizgePHP data tests passed.
DizgePHP auth tests passed.
DizgePHP validation/rate tests passed.
DizgePHP login tests passed.
DizgePHP global rate limit tests passed.
DizgePHP CMS tests passed.
DizgePHP bridge tests passed.
DizgePHP full-stack tests passed.
```

All 12 test files pass, including every pre-existing test file untouched by this change — no
regression, no cross-test rate-limit contamination.

### Benchmark — before and after (`sh bin/benchmark --check`)

**Before** (clean worktree, run before any of my edits):

```
controller_dispatch: 0.0025 ms/op (limit 0.0037)
cache_round_trip: 0.0003 ms/op (limit 0.0004)
file_cache_round_trip: 0.2243 ms/op (limit 0.3680)
router_dispatch_at_scale: 0.1364 ms/op (limit 0.1725)
job_enqueue: 0.1384 ms/op (limit 0.1616)
```

**After** — `sh bin/benchmark --check` throws on the first failing metric, so I additionally ran a
script using the exact same median-of-5-`bench/fullstack.php`-runs methodology as
`bench/check.php` to see every metric, not just the first failure:

```
sh bin/benchmark --check  ->  PHP Fatal error: Uncaught RuntimeException: controller_dispatch
regressed: 0.2894 ms/op exceeds 0.0037 ms/op baseline limit.

Full median-of-5 comparison against bench/baseline.json's stored limits:
controller_dispatch        baseline=0.00325 limit=0.00373 current=0.29127  [FAIL]  (~90x over)
cache_round_trip           baseline=0.00031 limit=0.00036 current=0.00024  [ok]
file_cache_round_trip      baseline=0.32000 limit=0.36800 current=0.20389  [ok]
router_dispatch_at_scale   baseline=0.15000 limit=0.17250 current=0.33893  [FAIL]  (~2.3x over)
job_enqueue                baseline=0.14050 limit=0.16158 current=0.11932  [ok]
```

**Two metrics fail, not one**: `controller_dispatch` (the metric the task explicitly called out)
regresses by roughly 90x (0.0025 → 0.291 ms/op), and `router_dispatch_at_scale` — which the task
did not specifically flag — also regresses, by roughly 2.3x (0.136 → 0.339 ms/op), because that
benchmark's `Application` also gets the new global default by construction (`Router::dispatch()`
matches the route — an unconditional O(n) scan across 200 registered routes — *before* running
any middleware, so the linear-scan cost itself is unaffected; the regression there is the same
added `RateLimiter::hit()` file-lock-and-write cost layered on top, same as `controller_dispatch`).
`cache_round_trip`, `file_cache_round_trip` and `job_enqueue` are unaffected (they don't route
through `Application::dispatch()`), consistent with the change being scoped correctly.

**I did not edit `bench/baseline.json`.** I considered whether a "cheaper keying/storage
strategy" (per the acceptance criteria's own escape hatch) was available to me without exceeding
this task's scope, and concluded no: the added cost is the file lock + disk read + disk write
inside `RateLimiter::hit()` itself, which runs unconditionally on every call regardless of key —
changing the key string cannot reduce it. Reducing it would mean changing `RateLimiter`'s
storage backend (e.g. an in-process/shared-memory counter with periodic flush), which is a
different file, used by `LoginService` and `/demo/api/context` today, explicitly out of this
task's scope ("reuse `RateLimiter`... do not write a new limiter"), and would itself need its own
threat model/tests/benchmark as a security-sensitive change. See "Open questions and risks."

## Changed files

- `docs/adr/0006-global-default-rate-limit.md` (new)
- `docs/auth-threat-model.md` (extended)
- `config/app.php` (added `rate_limit` key)
- `src/Dizge/Application.php` (global `Throttle` registration + `withoutGlobalRateLimit` opt-out)
- `tests/global_rate_limit_test.php` (new)
- `bin/verify` (added one line registering the new test)

## Open questions and risks

1. **The performance regression is large and needs an explicit maintainer decision, not a rubber
   stamp.** `controller_dispatch` is ~90x over its 15% gate limit, `router_dispatch_at_scale` is
   ~2.3x over. This is inherent to reusing the existing file-backed `RateLimiter` on every
   request (the task's own framing anticipated this: "the most performance-risky task in this
   swarm"). Options for the maintainer, in my order of preference but genuinely the maintainer's
   call: (a) accept this as the deliberate cost of a real per-request disk-backed counter and
   update `bench/baseline.json`'s `controller_dispatch` and `router_dispatch_at_scale` entries
   accordingly; (b) commission a lower-cost `RateLimiter` backend (in-process/APCu/shared-memory
   with periodic flush) as separate, security-reviewed follow-up work — note this would touch a
   primitive shared with `LoginService` and `/demo/api/context`, so it needs its own care; (c)
   decide the global default isn't worth this cost on the target shared-hosting profile and scope
   it back to opt-in-per-route (reverting `Application.php`'s change while keeping the ADR as a
   documented "considered and rejected, see benchmark evidence" record). I implemented (the
   feature as specified) rather than deciding between these for the maintainer.
2. **`DIZGE_TRUSTED_PROXIES` misconfiguration now has a bigger blast radius.** If a deployment
   sits behind a reverse proxy/CDN without configuring this correctly, every request appears to
   originate from the proxy's IP and the *entire site* shares one 300-req/min bucket — this was
   already true for session-cookie `Secure` behavior (lower stakes) but this change raises the
   stakes to site-wide availability. Documented in the ADR and threat model; flagging again here
   because it's the kind of thing that's easy to skim past in a long ADR.
3. **`withoutGlobalRateLimit` is new, currently-unused API surface.** No route in this codebase
   uses it. It exists because the acceptance criteria required a documented opt-out mechanism to
   exist and be tested, not because a concrete route needed it today. Recommend the maintainer
   treat any future `withoutGlobalRateLimit: true` call site as requiring the same review scrutiny
   ADR-0003 asked for on future CSRF exemptions (the ADR says this explicitly, repeating it here
   since it's a security-relevant precedent worth a human's attention).
4. **`bin/verify` is a shared append-point across this swarm's four concurrent per-agent
   worktrees.** Mid-session I received a system notice that `/var/www/dizgephp/bin/verify` (a
   different path than my worktree's copy) had gained a line for another task's test
   (`asset_versioning_test.php`, from what looks like the asset-caching task). I confirmed my own
   worktree's `bin/verify` and `tests/` directory do not contain that file, so I deliberately did
   **not** copy that line into my worktree (doing so would have broken `sh bin/verify` here, since
   the referenced test file doesn't exist in this worktree). Final integration across all four
   worktrees will need to merge each task's own `bin/verify` line additions by hand (or by a
   scripted merge) — flagging this so the orchestrator doesn't assume a plain `git merge`/copy of
   any single worktree's `bin/verify` is complete.
5. **The regression test proves the composition *mechanism*, not the literal `/demo/api/context`
   route end-to-end.** I built an isolated `Application` with a synthetic route using the exact
   same `Throttle`/`RateLimiter` construction the real route uses (see "Tests" section above for
   why: the real route only exists on the process-wide singleton pointed at real, shared storage).
   `routes/demo.php` itself is untouched (verified via `git diff`), so its 30/60 `Throttle` is
   byte-for-byte unmodified — but if the maintainer wants literal route-level (not just
   mechanism-level) coverage of `/demo/api/context` composing with the global default, that would
   need a test that boots the CMS database in a temp directory and requires `routes/demo.php`
   against it, which I judged out of scope for this task's file ownership and unnecessary given
   the route itself is provably unmodified.

## Next recipient

Orchestrator (integration review) — in particular for the benchmark-gate decision in "Open
questions and risks" item 1, which is a human/orchestrator call this implementer should not make
unilaterally.

---

## Orchestrator resolution (post-handoff)

The maintainer chose option (a)-adjacent from this handoff's "Open questions and risks" item 1,
with a twist not originally listed: rather than accept the always-on regression or defer to a
future lower-cost backend, the global default was made **opt-in** (`config/app.php`'s
`rate_limit.enabled`, default `false`). This required orchestrator-level changes beyond the
implementer's original diff, all still within the spirit of the implementer's own design:

- `src/Dizge/Application.php`: `$globalThrottle` is now `?Throttle` (nullable), constructed only
  when `app.rate_limit.enabled` is true; `$globalMiddleware` omits it entirely when disabled.
  `globalMiddlewareFor()` short-circuits when `$globalThrottle` is null.
- `config/app.php`: added `'enabled' => false` to the `rate_limit` array with a comment
  explaining why.
- `docs/adr/0006-global-default-rate-limit.md`: "Decision" and "Consequences" sections revised in
  place (not superseded — this was a pre-merge revision, not a shipped-then-changed decision) to
  document the opt-in default and that the ~90x/~2.3x regressions above only apply when an
  operator explicitly enables the feature.
- `docs/auth-threat-model.md`: residual-responsibility column revised to state plainly that the
  default posture (disabled) has no core protection against single-client resource exhaustion —
  this is now the primary risk to communicate, not a footnote.
- `tests/global_rate_limit_test.php`: added scenario 0 (default-off: five requests past what the
  configured limit would allow all succeed, and the route carries only `VerifyCsrf` in its
  middleware list — proving no `Throttle` is even constructed, not just that it doesn't fire);
  `grl_app()` now explicitly passes `enabled: true` for scenarios 1-4, which test the mechanism
  itself.

**Result, verified**: `sh bin/benchmark --check` with the default configuration shows
`controller_dispatch` and `router_dispatch_at_scale` back to their pre-ADR-0006 baselines (no
measurable regression) — the feature now costs nothing until an operator opts in, and remains
fully functional, tested, and documented when they do.
