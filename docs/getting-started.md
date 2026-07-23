# Getting started

## Requirements

- PHP 8.1+ with PDO SQLite for the default database.
- Apache or Nginx configured with `public/` as the document root.
- No Composer, Node.js or frontend build step is required.

## Install from a release

1. Download the `dizgephp-vX.Y.Z.zip` asset from GitHub Releases and extract it outside the web
   document root when your host permits it.
2. Point the virtual host or hosting panel to the extracted `public/` directory.
3. Copy `.env.example` to `.env`; set production values there or in the hosting environment.
4. Run `bin/dizge verify`. Run `bin/dizge migrate up` only when the included reference CMS or an
   application migration needs database storage.

For Git-based installation, pin a release tag rather than cloning an unversioned branch:

```bash
git clone --branch v0.1.0 --depth 1 https://github.com/kayacuneyd/dizgephp.git my-app
cd my-app
cp .env.example .env
bin/dizge verify
```

## First routes

HTML routes stay in `routes/web.php`; JSON endpoints stay in `routes/api.php`. A controller action
always returns `Dizge\Http\Response`.

```php
app()->get('/projects', [App\Http\Controllers\ProjectController::class, 'index']);
app()->get('/api/v1/projects', [App\Http\Controllers\ProjectController::class, 'indexApi']);
```

Generate a controller or action instead of copying boilerplate:

```bash
bin/dizge make:controller Project
bin/dizge make:action CreateProject
bin/dizge route:list
```

## DevinimJS and CKCSS

DizgePHP does not force a CDN. Self-hosted assets are the default for production. If a public,
version-pinned CDN is preferred, configure DevinimJS once during bootstrap:

```php
dv_path('https://cdn.jsdelivr.net/gh/kayacuneyd/devinimjs@v0.5.1/dist/modules');
```

Use a matching pinned CKCSS release URL in the page layout. Never point PHP `require` calls to a
CDN: DizgePHP is server code and must be installed on the host through a release ZIP or Git tag.
