# Deployment

## Shared hosting

- Upload a tagged GitHub Release ZIP or use a tag-pinned Git checkout.
- Set the web root to `public/`; the included `.htaccess` routes non-files to `index.php`.
- Keep `.env` and `storage/` outside public web access. `storage/` must be writable by PHP.
- Use HTTPS at the host/proxy and set `DIZGE_ENV=production`, `DIZGE_DEBUG=0`.

## Nginx

Use `public/` as `root`, send unknown paths to `/index.php?$query_string`, and pass PHP files to
the configured PHP 8.1+ FPM socket. Do not expose the project root, `.env`, migration files or
`storage/` through a fallback location.

## Database, jobs and backups

SQLite defaults to `storage/app.sqlite`; its connection enables foreign keys, WAL and a busy timeout.
Run migrations through `bin/dizge migrate up`. Take a backup before production migrations.

The official file queue is designed for cron, not a persistent daemon:

```cron
* * * * * /path/to/app/bin/dizge job:work 25 >/dev/null 2>&1
```

Run `bin/dizge verify` and `bin/dizge benchmark --check` before every tagged release.
