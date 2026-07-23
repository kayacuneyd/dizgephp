# DevinimJS bridge threat model

See `docs/adr/0005-devinim-bridge-protocol.md` for the design record. This covers
`Bridge\Registry`, `Bridge\Patch`, the `POST /_dizge/bridge` route and `assets/dizge-bridge.js`.

## Assets and trust boundaries

- Bridge bindings (`id -> handler`) registered by trusted application code at boot.
- The `detail` payload of every request is untrusted client input, exactly like any request body.
- `/_dizge/bridge` is a single, shared, session-authenticated browser route — not a per-component
  endpoint and not a separate trust boundary from the rest of the browser-session surface.

## Required controls

| Risk | Control |
|---|---|
| Forged bridge request (CSRF) | The route is registered with the same global `VerifyCsrf` middleware as every other state-changing browser route; a missing or invalid `X-CSRF-Token` is rejected with 419 before the handler ever runs. |
| Binding-id enumeration / unknown target | `Registry::dispatch()` throws `InvalidArgumentException` for an unregistered id; the route maps that to 404. IDs are chosen by application code at boot, not derived from client-controlled strings. |
| Malformed request shape | The route handler rejects a non-string `id` or non-array `detail` with 422 before calling `Registry::dispatch()`. |
| Handler receiving unvalidated input | Handlers are handed the raw `detail` array, identically to `$request->input()` in any other route — a handler MUST validate and authorize exactly as a route would (check `auth()`/`gate()` if the action is not public, validate types/ranges before trusting a value). This is an application responsibility the framework cannot enforce generically, the same way it cannot validate an arbitrary route's `$request->input()` for it. |
| Patch used to inject arbitrary markup/attributes | `Patch` only carries a `props` array; the client (`assets/dizge-bridge.js`) writes each entry back as a `data-*` attribute using the same key/value coercion DevinimJS already applies to component props — there is no code path from a patch to `innerHTML` or to a non-`data-*` attribute. A patch can therefore only ever set values the component's own prop contract already accepts. |
| Selector/event injection at registration time | `Registry::on()` enforces `id` matches `^[A-Za-z][A-Za-z0-9_.-]*$`, `selector` starts with `#`, and `event` matches `^[a-z][\w:-]*$`, rejecting anything else — this runs once at boot against trusted code, not per request, but keeps bindings greppable and prevents a copy-paste typo from silently matching the wrong element. |
| Information disclosure via patch | `Patch` never carries internal component or server state implicitly — a handler must explicitly choose what to put in `props`, the same discipline as any other JSON response. |

## Residual responsibility

- Rate limiting a specific bridge binding (e.g. against automated abuse of a public-facing
  handler) is the same explicit `Throttle` middleware pattern used elsewhere
  (`docs/api-threat-model.md`) — the bridge route itself has no built-in per-binding limiter.
- The bridge is session/cookie-authenticated only; it is not a bearer-token API surface and must
  not be exposed to non-browser clients as if it were one (`docs/api-threat-model.md` is the
  contract for that case).
- Handlers that touch authenticated/authorized state must call `auth()`/`gate()` themselves —
  `Registry` has no concept of the current identity.
