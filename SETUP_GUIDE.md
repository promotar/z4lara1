# Setup Guide

## Fresh Docker Installation

A fresh checkout must not contain `.env`, an application key, database
credentials, or an installed-state flag. Build and start it directly:

```bash
git clone https://github.com/promotar/z4lara1.git
cd art-inpa
docker compose up -d --build
```

Open `http://127.0.0.1:8088`. The application redirects to the installation
wizard. The wizard creates the permanent `APP_KEY`, tests the supplied MySQL
connection, runs a destructive `migrate:fresh` only after explicit confirmation,
and creates the super administrator.

Do not copy `.env.example` for a production installation. Installer-owned
secrets and state are written to the persistent `art-inpa-storage` Docker
volume, under `storage/app/platform/installation.env`.

The database must already exist and be reachable from the app container. Use a
remote hostname/IP or `host.docker.internal` for a database exposed by the
Docker host.

## Updating An Installed Container

Keep the existing Compose project and its `art-inpa-storage` volume:

```bash
git pull --ff-only
docker compose up -d --build
```

The entrypoint detects the persistent installed state and runs only
non-destructive `php artisan migrate --force`. It preserves the original
`APP_KEY`, database, users, and settings, and does not reopen the installer.

Never use `docker compose down -v` during an update: `-v` deletes the persistent
installer state and uploaded storage.

## Development Environment

Development uses a source-mount override while keeping `vendor` and built Vite
assets in Docker volumes:

```bash
cp .env.example .env
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

Edit `.env` only for local development. It is ignored by Git and rejected by
the repository credential guard if accidentally staged.

The development override defaults to host UID/GID `1000:1000` so generated
storage remains editable. Set `ART_INPA_HOST_UID` and `ART_INPA_HOST_GID` in
your shell when the development user has different IDs.

If the development database is an existing container on the external
`database-net` network, add the optional network override:

```bash
docker compose \
  -f docker-compose.yml \
  -f docker-compose.dev.yml \
  -f docker-compose.database-net.yml \
  up -d --build
```

Run Vite on the host only while actively developing frontend files:

```bash
npm ci --include=dev
npm run dev
```

## Pre-publish Safety Check

Before committing or publishing:

```bash
bash scripts/verify-no-sensitive-files.sh
docker compose config --quiet
```

GitHub Actions runs the same sensitive-file guard on every push and pull
request.
