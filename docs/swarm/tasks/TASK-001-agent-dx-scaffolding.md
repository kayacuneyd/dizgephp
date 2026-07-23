# Task: TASK-001 — AI-agent developer experience: `make:resource` scaffolding + component contract in `inspect`

## Goal

Cut the tokens/time an AI agent (or a human) needs to produce a working CRUD-shaped page from
scratch, and give an agent one authoritative, single-command source for what DevinimJS component
props/events actually exist — so it stops needing to read multiple files across two repos to
generate a correct `dv()` call.

## Scope and non-goals

In scope:
1. `bin/dizge make:resource <Name>` — one command that scaffolds a full vertical slice:
   controller + migration + a list/form view wired to real DevinimJS components. It must NOT
   auto-register routes (ADR-0001: routes stay explicit in `routes/web.php`/`routes/api.php`) —
   instead it prints the exact `app()->get(...)`/`app()->post(...)` lines for the developer to
   paste in, the same way `make:controller`/`make:action` leave route wiring to the developer.
2. `bin/dizge inspect --json` gains a component prop/event contract, not just
   `{name, tag, published}`. Merge `Renderer::COMPONENTS` (this repo, prop names) with
   `../devinimjs/docs/component-manifest.json` (sibling repo, richer metadata) when that sibling
   checkout is resolvable; degrade gracefully (omit the richer fields, keep working) when it
   isn't — `inspect` must never hard-fail just because devinimjs isn't checked out next to this
   repo, since it is the primary standalone context tool for this repo.

Out of scope (do not touch): the bridge module, caching, rate limiting, DevinimJS source itself.
Do not modify `src/Dizge/Devinim/Renderer.php`, `dizge.php`, `routes/*.php`, `public/.htaccess`,
`src/Dizge/Application.php`, or any file outside this task's ownership table below.

## Acceptance criteria

- `php bin/dizge make:resource Product` (no prior `products` anything) creates, without
  overwriting any existing file (reuse the existing `writeGenerated()` refuse-to-overwrite guard
  in `bin/dizge`):
  - `app/Http/Controllers/ProductController.php` — extends `Dizge\Http\Controller`
    (`src/Dizge/Http/Controller.php`), methods for index/show/create/store/edit/update/destroy,
    each returning `Dizge\Http\Response`, following the existing `FrameworkController`
    (`app/Http/Controllers/FrameworkController.php`) style: constructor-injected dependency,
    `/** @param array<string,string> $params */` on every action.
  - `database/migrations/{timestamp}_create_products.php` — reuse the exact template shape
    `make:migration` already generates (see the `make:migration` case in `bin/dizge`), with a
    plausible `id`/`name`/timestamps schema as a starting point.
  - `views/products/index.php` and `views/products/form.php` (or a single file if that's
    simpler and consistent with existing view conventions in `views/`) that render a
    `dv('data-table', ...)` for the list and `dv('field', ...)` for form inputs — reference
    `src/Dizge/Devinim/Renderer.php`'s `COMPONENTS` const for the exact accepted props of
    `data-table` and `field` so the generated view is actually valid, not just plausible-looking.
  - Prints the route registration snippet to stdout (do not write it into any routes file).
- Running `make:resource Product` a second time refuses to overwrite (matches `writeGenerated()`
  behavior) and exits non-zero with a clear message, exactly like `make:controller` today.
- `php bin/dizge inspect --json` output gains a field (name it clearly, e.g.
  `component_contract`) with per-component prop/event detail sourced from
  `component-manifest.json` when `../devinimjs/docs/component-manifest.json` (or a
  `DEVINIM_MANIFEST`-env-var-overridden path, mirroring the existing `DEVINIM_DIST` convention in
  `bin/publish-assets`) exists and parses as JSON; when it doesn't exist, `inspect --json` still
  runs successfully and simply omits the richer fields (verify by temporarily renaming the
  sibling checkout path or using an env var override to a nonexistent path in a test).
- `sh bin/verify` passes, including whatever new regression test(s) you add for `make:resource`
  and for `inspect`'s graceful-degradation behavior (add to `tests/`, register in `bin/verify` in
  the same style as the existing `php tests/*.php` lines).
- `sh bin/benchmark --check` still passes (this task should not touch any benchmarked path, but
  confirm before finishing).

## Inputs

- Read first: `AGENTS.md`, `docs/architecture.md`, `bin/dizge` (full file — study
  `writeGenerated()`, `pascal()`, the existing `make:controller`/`make:action`/`make:migration`
  cases, and the `assetParity()`/`manifest()` functions added earlier this session), the
  `component-manifest.json` schema in the sibling `../devinimjs/docs/` if present.
- Relevant ADRs: ADR-0001 (explicit routing — do not auto-register routes), ADR-0004 (explicit
  container bindings — no reflection).
- Relevant existing patterns: `app/Http/Controllers/FrameworkController.php`,
  `src/Dizge/Devinim/Renderer.php`'s `COMPONENTS` const, `views/home.php` for `dv()` call style.

## Roles and outputs

| Role | Owner | Output file | Depends on |
| --- | --- | --- | --- |
| Orchestrator | claude (this session) | `docs/swarm/tasks/TASK-001-agent-dx-scaffolding.md` | — |
| Implementer | dispatched agent | `docs/swarm/handoffs/TASK-001-implementer.md` | — |

## Exclusive file ownership

| Path or glob | Sole writer | Branch/worktree |
| --- | --- | --- |
| `bin/dizge` | TASK-001 implementer | none (disjoint-ownership mode, see `docs/swarm/README.md`) |
| `app/Http/Controllers/ProductController.php` (generator output, example only) | TASK-001 implementer | same |
| `views/products/**` (generator output, example only) | TASK-001 implementer | same |
| `tests/make_resource_test.php` (or similarly named new test file) | TASK-001 implementer | same |
| `tests/inspect_component_contract_test.php` (or folded into the above) | TASK-001 implementer | same |

## Gates

- [ ] Implementation verified (`sh bin/verify` green, new tests included)
- [ ] Benchmark unaffected (`sh bin/benchmark --check` green)
- [ ] Handoff written with evidence and open questions
- [ ] Orchestrator integration review complete
- [ ] Human merge approval received (maintainer decides when to commit)
