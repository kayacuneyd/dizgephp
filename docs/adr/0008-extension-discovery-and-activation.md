# ADR-0008: Local extension discovery and activation

- Status: Accepted
- Date: 2026-07-24

## Decision

DizgeCMS discovers plugins from local plugin.json manifests and themes from local theme.json
manifests. Plugins are disabled by default and are enabled explicitly through the CLI. An enabled
plugin entrypoint is loaded on the next request and must return a callable that registers only
explicit framework routes.

Extension state is stored in the ignored config/cms.local.php file. Remote downloads, implicit
filesystem magic and WordPress plugin execution are not supported.

## Consequences

An operator can audit installed and enabled extensions before activation. A malformed manifest or
missing enabled plugin fails closed instead of executing unknown code. Future capabilities such as
migrations and admin navigation must add a versioned registry contract and security tests rather
than expanding the entrypoint surface implicitly.
