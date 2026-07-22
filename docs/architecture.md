# Architecture

| Concern | Owner |
|---|---|
| HTTP routing, controllers, rendering, validation, data and security | DizgePHP |
| Presentation and layout | CKCSS |
| Browser interaction and custom elements | DevinimJS |

Routes are explicit and ordered. `routes/web.php` returns server-rendered HTML; `routes/api.php`
returns JSON below `/api/v1`. Both representations can call one action/service without content
negotiation magic.

The container resolves only bindings registered in `routes/services.php`; it does not infer or
autowire constructor dependencies. The core retains PDO/query builder and migrations, rather than
an implicit ORM. Filesystem cache, cron jobs, mail transports and API tokens are official modules.

The included DizgeCMS Studio is a reference application under `/demo`. It is not required by the
HTTP core, but exercises migration, auth, authorization, editor, media, search and workflow paths.

For compact tooling context use `bin/dizge inspect --json`. Security boundaries are documented in
the threat models under `docs/` and are part of the framework contract.
