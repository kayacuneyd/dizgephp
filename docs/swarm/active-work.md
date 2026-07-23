# Active Swarm Work

| Task | Status | Orchestrator | Implementation branch/worktree | Next gate |
| --- | --- | --- | --- | --- |
| [TASK-001](tasks/TASK-001-agent-dx-scaffolding.md) — `make:resource` + inspect component contract | Integrated | claude (this session) | merged into main checkout, worktree removed | Human merge/commit approval |
| [TASK-002](tasks/TASK-002-first-app-tutorial.md) — first-app tutorial | Integrated | claude (this session) | merged into main checkout, worktree removed | Human merge/commit approval |
| [TASK-003](tasks/TASK-003-shared-hosting-asset-performance.md) — asset caching + CDN guidance | Integrated | claude (this session) | merged into main checkout, worktree removed | Human merge/commit approval |
| [TASK-004](tasks/TASK-004-global-rate-limit-default.md) — global rate-limit default | Integrated (revised to opt-in) | claude (this session) | merged into main checkout, worktree removed | Human merge/commit approval |

## Orchestrator notes

- Worktree isolation for TASK-002/003/004 turned out to work differently than the
  disjoint-ownership fallback documented in `docs/swarm/README.md`: the harness placed each
  implementer in its own worktree regardless (confirmed by TASK-001/003/004's handoffs), so the
  original "no worktree" plan was moot in practice — but the disjoint file-ownership tables still
  did their job of preventing any real ownership conflict once merged.
- **One real conflict found and resolved by the orchestrator**: both TASK-001 and TASK-002's
  agents independently discovered that `tests/cms_test.php` hardcodes an exact 3-element migration
  ID list, which breaks as soon as any application migration exists (exactly the workflow
  `bin/dizge make:migration`/`make:resource` enable). The orchestrator had already fixed this
  directly (outside any task's ownership) with an isolated migration-fixture directory before
  TASK-001 finished; TASK-001's own (different, live-directory-coupled) fix to the same file was
  reviewed and **not** merged in favor of the orchestrator's more isolated version. Verified
  working together with TASK-001's own generated example migration in place.
- TASK-002's tutorial and TASK-001's own testing both independently found and (TASK-001) fixed a
  real pre-existing bug: `bin/dizge make:controller`/`make:migration` generated unparseable PHP
  due to double-backslash heredoc escaping. Fixed as part of TASK-001's `bin/dizge` changes,
  verified independently by the orchestrator after merge.
- **TASK-004 was not merged as originally implemented.** The implementer did everything the task
  asked (design-first ADR/threat-model, `Throttle` reuse, tests, honest un-hidden benchmark
  reporting) and correctly escalated rather than deciding unilaterally: making the global rate
  limit unconditional regressed `controller_dispatch` ~90x and `router_dispatch_at_scale` ~2.3x
  past the 15% gate. The maintainer chose to make it opt-in (`config/app.php`'s
  `rate_limit.enabled`, default `false`) rather than accept the regression or defer to a
  lower-cost backend. The orchestrator implemented that revision directly (`Application.php`,
  `config/app.php`, ADR-0006, `docs/auth-threat-model.md`, `tests/global_rate_limit_test.php`)
  and confirmed the benchmark regression fully disappears at the shipped default while the
  feature remains complete and tested when an operator opts in. See
  `docs/swarm/handoffs/TASK-004-implementer.md`'s "Orchestrator resolution" section for the
  full account.

## Result

All four tasks are integrated into the main working tree (uncommitted — see project memory for
why). `sh bin/verify` (15 test files) and `sh bin/benchmark --check` (all 5 metrics) are both
green as of the last orchestrator pass. Nothing has been committed; that is the maintainer's call.
