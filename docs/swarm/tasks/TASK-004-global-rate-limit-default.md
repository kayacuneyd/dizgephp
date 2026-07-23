# Task: TASK-004 — Global rate-limit default (security-sensitive)

## Goal

Give every route a sane default rate-limit safety net so a single client can't exhaust a shared
host's limited resources, without requiring every developer to remember to attach `Throttle`
manually (today it's fully opt-in — `docs/auth-threat-model.md` already flags "credential
stuffing not solved in core" and `docs/api-threat-model.md` leaves throttling as an explicit
per-route choice). This is a security-posture change: per `AGENTS.md` ("every security-sensitive
module needs a threat model, tests and performance measurement") and the KayaEOS constitution
§3.4 ("security review for sensitive changes"), do the design record and threat model FIRST, then
implement.

## Scope and non-goals

In scope:
1. A new ADR (`docs/adr/0006-<slug>.md` — next number after the existing 0001-0005; follow their
   exact format, see `docs/adr/0003-session-csrf-authorization.md` for the closest precedent).
2. A threat model update — extend the most relevant existing doc (read
   `docs/auth-threat-model.md`, `docs/api-threat-model.md` and `docs/operations-threat-model.md`
   first and decide which one this belongs in, or whether it needs its own; justify the choice in
   your handoff) rather than inventing a new doc if an existing one already owns this concern.
3. Implementation: reuse `Dizge\Security\RateLimiter` and `Dizge\Security\Throttle`
   (`src/Dizge/Security/RateLimiter.php`, `src/Dizge/Security/Throttle.php`) — do not write a new
   limiter. Register a `Throttle` instance in `Dizge\Application`'s `$this->globalMiddleware`
   array (`src/Dizge/Application.php`, constructor, right next to the existing
   `new VerifyCsrf($this->csrf)`), keyed on the client IP alone (`$request->header('remote-addr')`
   — already trusted-proxy-aware from this session's `TrustedProxy` work, so this is safe behind
   a configured reverse proxy) so one bucket covers a whole client across the whole app, not one
   bucket per route (a per-route key would multiply `storage/rate-limits/` file count on shared
   hosting for no real benefit — the goal is a global safety net, not a per-route budget; routes
   that need a stricter per-route budget already use their own explicit `Throttle`, e.g.
   `routes/demo.php`'s `/demo/api/context`, and must keep working unmodified alongside your
   global default).
4. Default limit and window: make them reasonable (state your reasoning — something in the range
   of a few hundred requests per minute per IP is a starting point, not a mandate) and
   configurable via `config/app.php` (it currently only has `name`/`environment`/`api_prefix` —
   add a new top-level key there, read via `app()->config()->get(...)`, same pattern
   `FrameworkStatus` already uses).
5. An explicit, documented opt-out or override path for a route that legitimately needs different
   behavior (mirror the precedent in ADR-0003's consequences: "future webhook exemptions must be
   explicit and security-reviewed" — don't build a silent bypass).

Out of scope: do not touch `bin/dizge`, `docs/deployment.md`'s asset section, `public/.htaccess`,
`src/Dizge/Devinim/Renderer.php`, or any generator/scaffolding file.

## Acceptance criteria

- ADR written and added to a natural index if one exists (check whether `docs/adr/` has an
  index file; if not, that's consistent with the existing 0001-0005, which have none — don't
  invent one).
- Threat model coverage added/extended with a clear risk/control table matching the existing
  format (see `docs/api-threat-model.md` for the table style).
- Every existing route in `routes/web.php`, `routes/api.php`, `routes/demo.php` still works and
  still passes its existing behavior under the new global default — in particular:
  `/demo/api/context`'s existing stricter Throttle must still independently enforce its own
  30-per-60s limit, unaffected by (but composable with) the new global default.
- New regression test(s) in `tests/` (register in `bin/verify`): confirm the global default
  actually triggers a 429 once its limit is hit for an otherwise-unprotected route, confirm a
  request from a different key/IP is unaffected, confirm `/demo/api/context`'s own stricter limit
  still fires independently.
- **Benchmark discipline is critical here**: this adds a mandatory file-lock + disk write
  (`RateLimiter::hit()`) to every single request's critical path. Run `sh bin/benchmark --check`
  before and after your change and report both sets of numbers in your handoff, specifically the
  `controller_dispatch` metric. If it regresses past the 15% gate, do not just accept it — either
  find a cheaper keying/storage strategy, or (if you conclude the cost is inherent and acceptable)
  say so explicitly with reasoning and let the orchestrator/human decide on updating the baseline;
  do not silently edit `bench/baseline.json` yourself to make a real regression disappear.
- `sh bin/verify` passes.

## Inputs

- Read first: `AGENTS.md`, `docs/adr/0003-session-csrf-authorization.md` (closest ADR precedent),
  `docs/auth-threat-model.md`, `docs/api-threat-model.md`, `docs/operations-threat-model.md`,
  `src/Dizge/Application.php` (full file), `src/Dizge/Security/RateLimiter.php`,
  `src/Dizge/Security/Throttle.php`, `src/Dizge/Http/TrustedProxy.php` and how
  `src/Dizge/Http/Request.php` already resolves a trusted-proxy-aware `remote-addr` (added this
  session — reuse it, do not reinvent IP resolution), `routes/demo.php`'s `/demo/api/context`
  route for the existing per-route `Throttle` pattern to keep compatible, `bench/fullstack.php`
  and `bench/baseline.json` for the benchmark gate you must respect.

## Roles and outputs

| Role | Owner | Output file | Depends on |
| --- | --- | --- | --- |
| Orchestrator | claude (this session) | `docs/swarm/tasks/TASK-004-global-rate-limit-default.md` | — |
| Implementer | dispatched agent | `docs/swarm/handoffs/TASK-004-implementer.md` | — |

## Exclusive file ownership

| Path or glob | Sole writer | Branch/worktree |
| --- | --- | --- |
| `docs/adr/0006-*.md` (new) | TASK-004 implementer | none (disjoint-ownership mode, see `docs/swarm/README.md`) |
| One of `docs/auth-threat-model.md` / `docs/api-threat-model.md` / `docs/operations-threat-model.md` (pick one, justify) | TASK-004 implementer | same |
| `src/Dizge/Application.php` | TASK-004 implementer | same |
| `config/app.php` | TASK-004 implementer | same |
| new `tests/global_rate_limit_test.php` (or similar name) | TASK-004 implementer | same |
| `bench/baseline.json` (read/report only — do not edit to hide a regression, see acceptance criteria) | — | — |

## Gates

- [ ] ADR + threat model written before implementation (design-first, KayaEOS §1)
- [ ] Implementation verified (`sh bin/verify` green, new tests included)
- [ ] Benchmark measured before/after and reported; gate respected or regression explicitly flagged, not hidden
- [ ] Existing per-route `Throttle` usage (`/demo/api/context`) confirmed still independently correct
- [ ] Handoff written with evidence and open questions
- [ ] Orchestrator integration review complete
- [ ] Human merge approval received
