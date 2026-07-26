# Public staging activation

The isolated staging application already runs at `127.0.0.1:8088` from
`/var/www/dizgecms-staging` with a separate SQLite database. The Nginx HTTP/ACME vhost is ready
at `/etc/nginx/sites-available/staging.dizgecms.digitaltamam.com`.

## Activated environment

The DNS record and TLS certificate are active:

```text
staging.dizgecms.digitaltamam.com.  A  72.62.52.55
```

Certificate renewal is managed by Certbot. If the vhost needs to be rebuilt, run as root:

```sh
certbot --nginx -d staging.dizgecms.digitaltamam.com
nginx -t && systemctl reload nginx
```

The TLS vhost retains the staging root, separate database FastCGI parameters and
`X-Robots-Tag: noindex, nofollow, noarchive` controls from
`/etc/nginx/sites-available/dizgecms-staging.local`. Verify HTTP→HTTPS, the certificate host,
public product routes, Studio login and the headless API after activation.
