# DizgeCMS plugins

Plugins extend the CMS through explicit manifests and versioned contracts. A plugin may register
routes, migrations, admin navigation, CLI commands, content types and SEO providers only when its
manifest declares the capability and permission.

The first runtime milestone is local/allowlist activation. Remote arbitrary PHP downloads and
WordPress plugin API compatibility are intentionally out of scope.
