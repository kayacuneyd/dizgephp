# Task: TASK-002 — End-to-end "first app" tutorial

## Goal

Give a newcomer (human or AI agent) a single guided document that goes from `git clone` to a
working small CRUD feature, using only real, verified-working framework features — closing the
"learning curve" gap: today's docs are all reference material (ADRs, threat models, specs), with
no walkthrough/tutorial progression.

## Scope and non-goals

In scope: one new tutorial document. Every code snippet in it must be copy-pasteable and actually
run — verify each step by running it (`php -S localhost:PORT -t public public/index.php` and
`curl`, or the existing `tests/` pattern) before including it, not written from memory.

Out of scope: do not modify `README.md`, any existing `docs/*.md` file, or any source file. This
task is additive-only — one new doc file, nothing else. If `make:resource` (TASK-001) would be a
natural shortcut to mention, write the tutorial to work correctly WITHOUT it (build the resource
by hand, route by route) since TASK-001 may finish after this task and you cannot depend on it
existing — this keeps the tutorial correct and testable in isolation via disjoint file ownership.

## Acceptance criteria

- New file `docs/tutorial-first-app.md` (exact name; this reserves the file for this task only).
- Walks through, in order, with real runnable code at each step: clone → `cp .env.example .env`
  → run `php -S localhost:8080 -t public public/index.php` → add one GET route in
  `routes/web.php` style (as a snippet to try, not editing the real file) → add a controller
  (`bin/dizge make:controller`) → add a migration (`bin/dizge make:migration`) and run
  `bin/dizge migrate up` against a throwaway SQLite path → query it via `db()->table(...)` →
  render a `dv()` component from a view → run `bin/dizge verify` to show the verification gate.
- Every command shown was actually executed by you as part of writing this task (not simulated)
  — note in your handoff file which commands you ran and confirm their real output matched what
  you wrote. Use a scratch/temp working copy or a disposable SQLite file under `storage/` cleanup
  pattern already used in `tests/*.php` (temp dirs via `sys_get_temp_dir()`) so you don't leave
  stray files in the real repo; if you must touch the live `storage/app.sqlite` to demonstrate
  `migrate up`, revert/clean it up afterward and say so explicitly in the handoff.
- End the tutorial with a pointer to `docs/architecture.md`, `docs/security-baseline.md`, and (if
  it exists by the time you finish — check, don't assume) `bin/dizge make:resource` as the "now
  that you understand the pieces, here's the fast path" callout.
- Read `AGENTS.md` and `docs/getting-started.md` first so the tutorial's tone/terminology matches
  the rest of the project's docs (Turkish UI copy in the CMS demo is fine as reference, but write
  this tutorial in the same language as `README.md`, i.e. English, to match the existing docs/
  audience).

## Inputs

- Read first: `README.md`, `AGENTS.md`, `docs/getting-started.md`, `docs/architecture.md`,
  `routes/web.php`, `views/home.php`, `tests/core_test.php` (for realistic working examples of
  routes/`dv()`/db queries you can adapt).

## Roles and outputs

| Role | Owner | Output file | Depends on |
| --- | --- | --- | --- |
| Orchestrator | claude (this session) | `docs/swarm/tasks/TASK-002-first-app-tutorial.md` | — |
| Implementer | dispatched agent | `docs/swarm/handoffs/TASK-002-implementer.md` | — |

## Exclusive file ownership

| Path or glob | Sole writer | Branch/worktree |
| --- | --- | --- |
| `docs/tutorial-first-app.md` | TASK-002 implementer | none (disjoint-ownership mode, see `docs/swarm/README.md`) |

## Gates

- [ ] Every snippet actually executed and verified during writing (not simulated)
- [ ] No files outside ownership table touched
- [ ] Handoff written with evidence (commands run, outputs) and open questions
- [ ] Orchestrator integration review complete
- [ ] Human merge approval received
