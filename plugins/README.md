# DizgeCMS plugins

Plugins extend the CMS through explicit manifests and versioned contracts. A plugin may register
routes, migrations, admin navigation, CLI commands, content types and SEO providers only when its
manifest declares the capability and permission.

The manifest is a local plugin.json file. It includes id, name, version, entrypoint and an explicit
capabilities list. The loader validates the id, entrypoint containment and capability allowlist
before any plugin bootstrap is considered.

Remote downloads and WordPress plugin compatibility are not part of this contract.

List and activate extensions from the CLI:

    bin/dizge plugin:list
    bin/dizge plugin:enable example-health
    bin/dizge plugin:disable example-health

Enabled plugins boot on the next request. The generated local state is stored in
config/cms.local.php, which is ignored by Git and must be reviewed like any other production
configuration change.

The first runtime milestone is local/allowlist activation. Remote arbitrary PHP downloads and
WordPress plugin API compatibility are intentionally out of scope.
