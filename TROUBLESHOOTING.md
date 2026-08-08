# Vite Deployment Troubleshooting

## Vite Manifest Not Found

```text
Illuminate\Foundation\ViteManifestNotFoundException
Vite manifest not found at: .../public/build/manifest.json
```

This means the deployed image skipped or overrode the repository frontend build
phase. It is an image-build failure, not a Blade styling problem.

If the exception path points outside `<project-root>/public`, inspect service
providers for a stale `usePublicPath()` override. Art INPA intentionally uses
Laravel's default project-root public directory.

## Coolify Checks

1. Confirm the build pack is `Nixpacks`.
2. Confirm the base directory is the repository root.
3. Remove custom Install/Build commands that override `nixpacks.toml`.
4. Redeploy with a clean build.
5. Confirm the build log executed:

```text
npm ci --include=dev --no-audit --no-fund
npm run build
test -s public/build/manifest.json
```

## Docker Checks

```bash
docker compose build --no-cache app
docker compose up -d app
docker compose exec app test -s public/build/manifest.json
docker compose logs app
```

The single Apache/PHP image stores its build artifact at
`/opt/art-inpa/public/build/manifest.json`. When a source bind mount hides the
image filesystem, the entrypoint restores that artifact into `public/build`.

## Development Hot File

`public/hot` is development-only. It must not be committed or copied into a
production image. Both `.gitignore` and `.dockerignore` exclude it, and the
production build removes it before running Vite.

Do not fix this exception by adding conditional Blade fallbacks, installing
Node.js at runtime, or committing stale generated bundles.
