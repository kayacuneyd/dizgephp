# Active Swarm Work

| Task | Status | Orchestrator | Implementation branch/worktree | Next gate |
| --- | --- | --- | --- | --- |
| [TASK-001](tasks/TASK-001-agent-dx-scaffolding.md) — `make:resource` + inspect component contract | Integrated | claude (this session) | merged into main checkout, worktree removed | Human merge/commit approval |
| [TASK-002](tasks/TASK-002-first-app-tutorial.md) — first-app tutorial | Integrated | claude (this session) | merged into main checkout, worktree removed | Human merge/commit approval |
| [TASK-003](tasks/TASK-003-shared-hosting-asset-performance.md) — asset caching + CDN guidance | Integrated | claude (this session) | merged into main checkout, worktree removed | Human merge/commit approval |
| [TASK-004](tasks/TASK-004-global-rate-limit-default.md) — global rate-limit default | Integrated (revised to opt-in) | claude (this session) | merged into main checkout, worktree removed | Human merge/commit approval |
| [TASK-005](tasks/TASK-005-headless-domain-model.md) — site settings and navigation data model | Integrated | Codex orchestrator | disjoint ownership in current checkout | TASK-006 API integration |
| [TASK-006](tasks/TASK-006-headless-public-api.md) — public headless REST contract | Integrated | Codex orchestrator | disjoint ownership in current checkout | TASK-007 review + starter integration |
| [TASK-007](tasks/TASK-007-headless-api-security.md) — public/draft, preview and cross-origin security | Integrated design/tests | Codex orchestrator | disjoint ownership in current checkout | Signed preview implementation |
| [TASK-008](tasks/TASK-008-devinim-headless-starter.md) — DevinimJS headless starter | Integrated starter | Codex orchestrator | disjoint ownership in current checkout | Browser/API smoke test |
| [TASK-009](tasks/TASK-009-headless-release-verification.md) — integration and shared-hosting verification | Integrated | Codex orchestrator | current checkout | Release package and live-route checks complete |
| TASK-010 — signed preview URLs and explicit CORS allowlist | Integrated | Codex orchestrator | current checkout | Security regression suite complete |
| [TASK-011](tasks/TASK-011-product-marketing-home.md) — product marketing home and shared navigation | Integrated | Codex orchestrator | current checkout | Live responsive route gate |
| [TASK-012](tasks/TASK-012-product-marketing-pages.md) — product marketing feature pages and SEO | Integrated | Codex orchestrator | current checkout | Sitemap and live-route gate |
| [TASK-013](tasks/TASK-013-product-marketing-download-dx.md) — download, release and install explanation | Integrated | Codex orchestrator | current checkout | Package/checksum and live-route gate |
| [TASK-014](tasks/TASK-014-editor-media-navigation.md) — P0 authoring experience | Integrated | Codex orchestrator | current checkout | CMS, headless contract and asset verification complete |
| [TASK-015](tasks/TASK-015-theme-team-workflow.md) — appearance and editorial teams | Integrated | Codex orchestrator | current checkout | Studio appearance, users and revision comparison verified |
| [TASK-016](tasks/TASK-016-distribution-upgrade.md) — distribution, upgrade and recovery | Integrated | Codex orchestrator | current checkout | Staging upgrade gate passed |
| [TASK-017](tasks/TASK-017-production-readiness.md) — production readiness | Integrated | Codex orchestrator | HTTPS staging + operations boundary | Release gate passed |
| [TASK-018](tasks/TASK-018-headless-developer-experience.md) — headless developer experience | Integrated | Codex orchestrator | example/docs boundary | Starter/API verification passed |

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

All listed tasks are integrated into the main working tree (uncommitted — see project memory for
why). `sh bin/verify` and the release/package checks are green as of the latest orchestrator pass.
Nothing has been committed; that is the maintainer's call.
