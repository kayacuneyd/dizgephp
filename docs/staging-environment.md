# Local staging environment

The server has an isolated staging instance at `http://127.0.0.1:8088`.

- Source: `/var/www/dizgecms-staging`
- Database: `/var/www/dizgecms-staging/storage/app.sqlite`
- Nginx site: `/etc/nginx/sites-available/dizgecms-staging.local`
- Access: bound to loopback only; it is not a public hostname and does not use production data.
- Indexing: Nginx sends `X-Robots-Tag: noindex, nofollow, noarchive`.

## Refreshing staging code

Copy the desired reviewed working tree or release package into `/var/www/dizgecms-staging`, while
preserving its `storage/` directory. Then run the migrations against the staging database:

```sh
cd /var/www/dizgecms-staging
DIZGE_ENV=staging \
DIZGE_DB_DSN='sqlite:/var/www/dizgecms-staging/storage/app.sqlite' \
DIZGECMS_SITE_URL='http://127.0.0.1:8088' \
php bin/dizge migrate up
```

Validate Nginx before reloading it:

```sh
nginx -t && systemctl reload nginx
curl -I http://127.0.0.1:8088/
```

## Public staging

A public staging URL requires a dedicated DNS record and a TLS certificate. Do not repoint the
production hostname or reuse the production SQLite database. When a hostname is available, clone
the local server block, enable HTTPS, keep the database path separate and retain noindex controls.
