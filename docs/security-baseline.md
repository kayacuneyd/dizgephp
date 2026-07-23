# Security baseline

The current security boundary includes server-rendered HTML, guarded data access and browser-session
authentication. The DevinimJS bridge (`POST /_dizge/bridge`) is implemented, threat-modeled and
tested; see `docs/adr/0005-devinim-bridge-protocol.md` and `docs/bridge-threat-model.md`.

- `e()` uses UTF-8, `ENT_QUOTES` and `ENT_SUBSTITUTE`.
- `dv()` only accepts known framework components and documented props.
- Component prop values and approved HTML attributes are escaped.
- `children` rejects strings; a callback is the intentional trusted-markup boundary. User data
  inside that callback must still use `e()`.
- Event attributes such as `onclick` are rejected from `attrs`.
- Route handlers return `Response`; accidental direct output is not part of the route contract.
- Sessions use strict ids, HttpOnly cookies and SameSite=Lax. Login/logout regenerates the session id.
- CSRF uses a session-bound 256-bit synchronizer token; authorization policies deny by default.

Before adding bridge, file upload, mail or job modules, create a threat model and extend the tests
with a regression test for every resolved risk.
