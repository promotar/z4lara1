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
  stores its verified output in the Apache/PHP image. The runtime entrypoint can
  restore that output without compiling assets during startup.

Frontend compilation never happens during container startup.

## Coolify Docker Compose Application

1. Create a **Docker Compose** application from this repository and keep the
   Compose location at `/docker-compose.yml`.
2. Assign the application domain to the `app` service on container port `80`.
   Do not add a host port mapping or a source bind mount.
3. For a fresh installation, do not inject `APP_KEY`, database credentials, or
   installed-state flags. Persist `/var/www/html/storage` and complete the web
   installer. For an existing installation, restore its persistent storage or
   provide its original secrets through the deployment platform.
4. Set `APP_ENV=production` and `APP_DEBUG=false`.
5. Deploy with a clean build when replacing a previously broken image.

The image build must complete the Vite stage and verify:

```text
npm ci --include=dev
npm run build
test -s public/build/manifest.json
```

`package.json` pins Node `22.x` for the image build.

For this release, set:

```text
PLATFORM_VERSION=2.5.2
```

Do not add `public/build` to Git and do not add custom Coolify build commands.
The Compose file is the deployment source of truth.

## Docker Compose

```bash
docker compose build app
docker compose up -d
```

Compose contains one `app` service. Apache and PHP run in that container and
serve Laravel directly on internal container port `80`; the platform proxy is
responsible for the public HTTPS endpoint. The database is supplied through the
web installer and must be reachable from the container.

Three named volumes are managed by Compose:

- `art-inpa-storage` preserves installer state, the application key, uploads,
  sessions, logs, and other Laravel runtime files.
- `art-inpa-modules` preserves installed and updated plugin source files.
- `art-inpa-platform-assets` preserves plugin/theme assets published under the
  public web root.

The image seeds a new module/asset volume from the packaged defaults and never
overwrites an existing top-level plugin during container startup.
Installed deployments run non-destructive migrations automatically at startup;
fresh deployments never run migrations before the wizard confirms the database.

Never delete these named volumes during a redeploy. In particular, do not use
`docker compose down -v` for an installed site.

## Verification

```bash
test -s public/build/manifest.json
curl -fsS https://your-domain.example/login >/tmp/login.html
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
