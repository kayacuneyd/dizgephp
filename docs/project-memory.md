# DizgeCMS project memory

- 2026-07-23: New product root is `/var/www/dizgecms`; public document root is `/var/www/dizgecms/public`.
- 2026-07-23: Promotional site target is `https://dizgecms.digitaltamam.com`.
- 2026-07-23: Stack is DizgePHP full-stack, PHP 8.1+, SQLite by default, build-free, CKCSS and DevinimJS.
- 2026-07-23: Product boundary: reusable single-site CMS core plus local/allowlist plugins and themes;
  Baltık Postası remains a reference application/theme.
- 2026-07-23: Follow `docs/kayaeos-agent-swarm.md`; every plugin/core concern needs contract, threat
  model where applicable, tests, benchmark and release verification.
- 2026-07-23: `dizgecms.digitaltamam.com` is served by the Nginx vhost at
  `/etc/nginx/sites-available/dizgecms.digitaltamam.com`; HTTP redirects to HTTPS and the
  Let’s Encrypt certificate is installed with automatic renewal.
- 2026-07-23: Production runtime uses `/var/www/dizgecms/.env`, PHP 8.1 FPM and writable
  `storage/` owned by `www-data`; database migrations have been applied.
- 2026-07-23: Marketing SEO baseline includes canonical/OG/JSON-LD metadata plus `/robots.txt`,
  `/sitemap.xml` and `/llms.txt` routes.
