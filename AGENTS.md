# DizgePHP agent contract

## Product

DizgePHP is a PHP 8.1+ application framework for shared hosting. CKCSS owns presentation,
DevinimJS owns browser interaction, and DizgePHP owns HTTP, server rendering, security and data.
DizgeCMS is the reusable CMS product built on this framework; Baltık Postası is a reference
application/theme and must not become a core dependency.

## Non-negotiable rules

- Return `Response` values from routes; do not echo from handlers.
- Route declarations live explicitly in `routes/web.php`.
- Escape text with `e()`; nested component HTML uses a deliberate `children: fn () => ...` boundary.
- Never trust request, bridge or database input. Add validation, authorization and tests before use.
- Use `db()->table()` for simple parameterized queries; keep complex SQL in a reviewed repository.
- `update()` and `delete()` need a predicate. Do not add a raw-SQL escape hatch to the query builder.
- CSRF is global for browser state-changing routes; attach `RequireAuth` to every protected handler.
- Register authorization rules with `gate()->define()`; missing abilities must remain denied.
- Do not introduce Composer/runtime dependencies into the core without an ADR and benchmark.
- Every security-sensitive module needs a threat model, tests and performance measurement.
- Use `bin/dizge inspect --json` as the compact agent context entrypoint; do not infer route or
  authorization ownership from naming conventions.
- Agent work follows `docs/kayaeos-agent-swarm.md`: isolate concerns, preserve ADR ownership and
  never deploy directly without the verification/release gate.

## Verification

Run `bin/dizge verify` and `bin/dizge benchmark --check` before handing off framework changes.
