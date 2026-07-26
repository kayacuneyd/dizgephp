# DizgeCMS release package

Create a portable source release with:

```bash
bash bin/package-release 0.1.0
```

The package intentionally excludes `.env`, `storage/`, `dist/`, local extension settings, Git
metadata, and agent/editor work directories (`.agents/`, `.codex/`, `.claude/`, `.cursor/`). Tar
and ZIP member lists plus both SHA-256 files are verified by `tests/release_package_test.php`.
After extraction, publish assets and install:

```bash
bash bin/publish-assets
DIZGE_ADMIN_PASSWORD='choose-a-long-unique-password' php bin/dizge install admin@example.com
```

Verify the adjacent SHA-256 file before extraction. Never package an existing production
`storage/` directory or secrets into a distributable release.

## Upgrade and rollback

Deploy the reviewed package/check-out first. Then let the guarded command take a SQLite snapshot
and apply only pending migrations:

```bash
php bin/cms-upgrade --yes
php bin/dizge verify
```

If verification or the deployment fails, restore both the previous code checkout and the backup
path printed by the command:

```bash
php bin/cms-restore storage/backups/dizgecms-YYYYmmdd-HHMMSS.sqlite --force
```

`cms-upgrade` never downloads code, executes plugin archives or deletes backups. This keeps code
review, package checksum verification and database migration as separate, auditable boundaries.
