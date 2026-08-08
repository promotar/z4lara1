# Deployment Guide

## Build Contract

`public/build` is generated output and is not committed to Git. Every production
image must contain a non-empty `public/build/manifest.json` before Laravel
starts.

The application public root is always the `public` directory inside the
repository. Laravel resolves it through the framework default
`base_path('public')`; deployment code must not call `usePublicPath()` or point
to a sibling web directory.

The repository enforces this contract in both supported deployment paths:

- Coolify with Nixpacks reads `nixpacks.toml`, installs development build
  dependencies with `npm ci`, runs `npm run build`, and fails when the manifest
  is absent.
- Docker Compose builds the `vite-assets` stage in `docker/php/Dockerfile` and
  stores its verified output in the Apache/PHP image. The runtime entrypoint copies
  that output into an empty source bind mount.

Frontend compilation never happens during container startup.

## Coolify

1. Select the **Nixpacks** build pack.
2. Leave custom Install and Build commands empty so repository
   `nixpacks.toml` remains authoritative.
3. For a fresh installation, do not inject `APP_KEY`, database credentials, or
   installed-state flags. Persist `/var/www/html/storage` and complete the web
   installer. For an existing installation, restore its persistent storage or
   provide its original secrets through the deployment platform.
4. Set `APP_ENV=production` and `APP_DEBUG=false`.
5. Deploy with a clean build when replacing a previously broken image.

The build log must show:

```text
npm ci --include=dev
npm run build
test -s public/build/manifest.json
```

`package.json` pins Node `22.x`; do not override it with an older Coolify
Nixpacks Node version.

For this release, set:

```text
PLATFORM_VERSION=2.5.2
```

Do not add `public/build` to Git and do not set a Coolify build command that
overrides the repository build phase.

## Docker Compose

```bash
docker compose -f docker-compose.prod.yml build app
docker compose -f docker-compose.prod.yml up -d
```

Compose contains one `app` service. Apache and PHP run in that container and
serve Laravel directly on container port `80`. The database is supplied through
the web installer and must be reachable from the container. The persistent
`art-inpa-storage` volume carries installation state across image updates.
Installed deployments run non-destructive migrations automatically at startup;
fresh deployments never run migrations before the wizard confirms the database.

## Verification

```bash
test -s public/build/manifest.json
curl -fsS http://127.0.0.1/login >/tmp/login.html
grep -Eo 'build/assets/[^"]+\.(css|js)' /tmp/login.html
```

Request every reported CSS/JS URL and confirm HTTP `200`.

Inside a running Laravel container, this must resolve within the source root:

```bash
php artisan tinker --execute="echo public_path('build/manifest.json');"
```

## Rollback

Redeploy the previous image or Git revision. Database rollback is unnecessary
for this frontend build contract unless the release also contains migrations.
