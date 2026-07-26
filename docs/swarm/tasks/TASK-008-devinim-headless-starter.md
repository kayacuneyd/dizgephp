# Task: TASK-008 — DevinimJS headless starter

## Goal

Ship a minimal, shared-hosting-safe reference frontend that consumes the agreed DizgeCMS public
API and renders a site, menu, page and article without admin credentials in the browser.

## Scope

- Create `examples/devinim-headless/` only.
- Include a small API client, fixtures, route mapping, SEO metadata mapping and static deployment
notes.
- Use the TASK-006 contract; before it is available, fixtures must match the ADR contract.

## Non-goals

- Changes to DizgeCMS routes, migrations, security middleware or production assets.
- Node.js SSR, token storage, GraphQL and a visual editor.

## Acceptance criteria

- No privileged token or secret appears in source or browser configuration.
- The example can render fixture data without Node.js runtime requirements.
- After TASK-006, it consumes live `/api/v1/site`, menu and content endpoints in a smoke test.

## Exclusive file ownership

| Path | Sole writer |
| --- | --- |
| `examples/devinim-headless/**` | TASK-008 implementer |
| `docs/swarm/handoffs/TASK-008-implementer.md` | TASK-008 implementer |
