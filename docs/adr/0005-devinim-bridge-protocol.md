# ADR-0005: DevinimJS server round-trip bridge

- Status: Accepted
- Date: 2026-07-23

## Context

DevinimJS components are client-authoritative by design (ADR-0001 in the DevinimJS repository:
no SSR, no hydration). Some interactions still need a server verdict — a business rule the
client should not enforce alone (a quota, a price, a permission) — without adopting a full SPA
data-fetching layer or hand-rolling a fetch/CSRF/escaping path per component. `Bridge\Registry`,
`Bridge\Patch`, the `POST /_dizge/bridge` route and `assets/dizge-bridge.js` already existed as a
complete, working implementation, but shipped with no ADR, no threat model, no tests and no real
call site — `docs/security-baseline.md` still described it as "intentionally unimplemented". This
ADR is the missing design record for what was already built, and closes the gap between what the
project's own process requires before shipping a module (`AGENTS.md`: "every security-sensitive
module needs a threat model, tests and performance measurement") and what had actually shipped.

## Decision

- A binding is `(id, selector, event) -> handler`, registered at boot with `dv_bridge()` /
  `app()->bridge()->on()`. `id` is a server-chosen routing key, not client input; `selector` must
  start with `#` (a single element, matching the DevinimJS light-DOM/no-slot-collision model) and
  `event` must be a lowercase custom-event name — `Registry::on()` throws on any other shape.
- The client (`assets/dizge-bridge.js`) listens for `dv:{event}` on the bound selector, and on
  fire POSTs `{id, detail: event.detail}` as JSON to `/_dizge/bridge` with the session's CSRF
  token in `X-CSRF-Token`. This is the same `VerifyCsrf` global middleware every other
  state-changing browser route uses (`Application.php` registers the route with
  `$this->globalMiddleware`) — the bridge does not invent a parallel auth mechanism.
- The handler receives the client-supplied `detail` payload verbatim and returns `?Patch`. A
  handler is exactly as trusted, and exactly as responsible for validating/authorizing its input,
  as any other route closure reading `$request->input()` — `Registry::dispatch()` performs no
  authorization of its own beyond "does this id exist."
- A `Patch` is a plain `{props: array}` DTO; the client applies each prop back onto the element as
  a `data-*` attribute using the same coercion rules DevinimJS already uses for props, so a patch
  can only ever change what a server-rendered `dv()` call could already have set — it cannot
  inject arbitrary HTML or attributes.
- Returning `null` is the expected "no correction needed" response and is not an error.

## Consequences

- The bridge is only as safe as its handlers: every `dv_bridge()` registration must validate
  `detail` and check authorization exactly as a route would, per `docs/bridge-threat-model.md`.
- `/_dizge/bridge` is now a documented, tested, intentionally-shipped route rather than dead
  reachable surface; `docs/security-baseline.md` is corrected to match.
- The pattern generalizes: any component event can gain a server-authoritative correction without
  a bespoke endpoint, provided the handler is written with the same care as a route.
