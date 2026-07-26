# TASK-016 — Distribution, upgrade and recovery

## Goal

Make a release installable and safely upgradable on shared hosting.

## Ownership

- release manifest, package/update/backup CLI commands and release documentation
- install/upgrade/rollback tests and package fixtures

## Acceptance

- Signed/checksummed release metadata identifies the package version and required PHP version.
- Upgrade makes a backup before migrations and has a documented, tested rollback path.
- Theme/plugin package validation and version compatibility are explicit.
- Clean-install and upgrade fixtures run in verification.
