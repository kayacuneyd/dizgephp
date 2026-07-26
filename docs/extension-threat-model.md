# DizgeCMS extension threat model

## Boundary

Plugins are local PHP code with the same process privileges as the application. A manifest is
metadata and an allowlist, not a sandbox.

## Controls

- Plugins are disabled by default and must be enabled from the CLI.
- Only plugin.json manifests under the repository plugins directory are discovered.
- Entry points must remain inside their plugin directory and return an explicit callable.
- Only GET and POST route registration is currently exposed.
- Remote downloads and WordPress plugin execution are not supported.
- Invalid or missing enabled plugins fail closed during bootstrap.
- Activation state lives in ignored operator configuration, not content data.

## Residual risk

An operator must review local plugin PHP before enabling it. A future capability such as migrations,
admin navigation or commands requires a separate versioned contract and threat review.
