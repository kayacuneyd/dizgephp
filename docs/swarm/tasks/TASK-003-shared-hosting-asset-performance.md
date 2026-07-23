# Task: TASK-003 — Shared-hosting asset performance (cache headers + cache-busting + CDN guidance)

## Goal

Let static DevinimJS/CKCSS assets be cached by the browser for a long time (fewer requests on
repeat visits — the single biggest lever on cheap shared hosting with no CDN in front of it),
without risking a stale-cache bug the next time `bin/publish-assets` ships a new component
version. Also make the existing CDN option (`dv_path()` can already point at a pinned external
URL) a clearly documented, recommended choice for the most resource-constrained hosting tier.

## Scope and non-goals

In scope:
1. Long-lived `Cache-Control`/`mod_expires` headers for `public/assets/**` in `public/.htaccess`
   (the file already has `Options -Indexes`, dotfile blocking and security headers from earlier
   this session — add to it, don't replace those).
2. A cache-busting mechanism so a long `max-age` is actually safe: component/runtime script URLs
   emitted by `Dizge\Devinim\Renderer` (`src/Dizge/Devinim/Renderer.php`, see `scripts()`) must
   change when the underlying published file changes, with zero build step (no content hashing
   pipeline — this project is build-free by contract, see `AGENTS.md`). The simplest correct
   approach is almost certainly `filemtime()` of the actual file on disk appended as `?v=`,
   computed lazily inside `scripts()` — but you decide the exact mechanism; state your reasoning
   in the handoff. `dv_bridge_scripts()` in `dizge.php` also emits an asset URL
   (`/assets/dizge-bridge.js`) — decide whether it needs the same treatment and say why either way.
3. Update `docs/deployment.md`'s existing "DevinimJS and CKCSS assets" section (added this
   session) to document the caching behavior and to explicitly recommend pointing `dv_path()` at
   a pinned CDN URL (devinimjs publishes to jsDelivr per its own docs — check
   `../devinimjs/docs/guides/php-integration.md` or its README for the exact pinned-URL pattern
   it documents) as the default recommendation for the cheapest/most resource-constrained shared
   hosting tier, with self-hosting via `bin/publish-assets` as the alternative for hosts that
   can't reach an external CDN.

Out of scope: do not touch `bin/dizge`, `routes/*.php`, `src/Dizge/Application.php`,
`src/Dizge/Security/*`, or any test file outside your own new regression test for the versioning
behavior.

## Acceptance criteria

- `public/.htaccess` sets a long (e.g. one year) `Cache-Control: public, max-age=..., immutable`
  (or equivalent `mod_expires` directives) scoped to `/assets/`, guarded by the appropriate
  `<IfModule>` the same way the existing rules already are.
- `Renderer::scripts()` output changes when the underlying published `.js` file's content/mtime
  changes, and stays stable when it doesn't (verify with a test: publish, capture the URL, touch
  the file, capture again, assert they differ; capture twice without touching, assert they're
  equal). Add this test to `tests/` and register it in `bin/verify`, following this session's
  established pattern (a standalone `tests/*.php` script using the `expect`-style helper already
  used in `tests/core_test.php`).
- `sh bin/verify` and `sh bin/benchmark --check` both still pass — `Renderer::scripts()` is not on
  the currently-benchmarked hot path, but confirm you haven't added an `filemtime()`/`stat()` call
  inside anything that IS benchmarked (`Router::dispatch`, `Application::dispatch`) before
  finishing; if you're unsure, add a note in the handoff rather than guessing.
- `docs/deployment.md` clearly states both the caching behavior and the CDN-vs-self-hosted
  recommendation, matching this session's existing doc style (see the file's current "DevinimJS
  and CKCSS assets" section for tone/format to extend, not replace).

## Inputs

- Read first: `public/.htaccess` (current state — do not remove the existing `Options -Indexes`,
  dotfile-blocking, or security-header rules added earlier this session), `docs/deployment.md`
  (current "DevinimJS and CKCSS assets" section), `src/Dizge/Devinim/Renderer.php` in full,
  `bin/publish-assets` in full, `AGENTS.md` (zero-build/zero-dependency constraint).
- Relevant ADRs: none directly, but respect the build-free contract stated in `AGENTS.md` and
  `README.md`'s opening paragraph — no new Composer/npm/runtime dependency for this.

## Roles and outputs

| Role | Owner | Output file | Depends on |
| --- | --- | --- | --- |
| Orchestrator | claude (this session) | `docs/swarm/tasks/TASK-003-shared-hosting-asset-performance.md` | — |
| Implementer | dispatched agent | `docs/swarm/handoffs/TASK-003-implementer.md` | — |

## Exclusive file ownership

| Path or glob | Sole writer | Branch/worktree |
| --- | --- | --- |
| `public/.htaccess` | TASK-003 implementer | none (disjoint-ownership mode, see `docs/swarm/README.md`) |
| `src/Dizge/Devinim/Renderer.php` | TASK-003 implementer | same |
| `bin/publish-assets` | TASK-003 implementer | same |
| `dizge.php` (only if a new facade helper is genuinely needed) | TASK-003 implementer | same |
| `docs/deployment.md` | TASK-003 implementer | same |
| new `tests/asset_versioning_test.php` (or similar name) | TASK-003 implementer | same |

## Gates

- [ ] Cache headers verified (curl `-I` against `php -S` shows the header — note: PHP's built-in
      dev server does not process `.htaccess`, so verify the file's syntax carefully by hand/by
      reasoning, and say so explicitly in the handoff rather than claiming a live-server test that
      isn't actually possible in this environment)
- [ ] Cache-busting behavior covered by a passing regression test
- [ ] `sh bin/verify` and `sh bin/benchmark --check` green
- [ ] Handoff written with evidence and open questions
- [ ] Orchestrator integration review complete
- [ ] Human merge approval received
