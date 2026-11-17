# Art INPA Local Docker

The Docker layout is split by purpose:

- `docker-compose.yml`: portable Coolify/production runtime; contains no source
  bind, host port, `.env` injection, database container, or required external
  network.
- `docker-compose.dev.yml`: local source mount plus isolated `vendor` and Vite
  build volumes.
- `docker-compose.database-net.yml`: optional connection to an already-created
  external `database-net` network.
- `docker-compose.prod.yml`: production-compatible alias for platforms that use
  a separate Compose file path.

The base runtime exposes Apache/PHP only inside the Docker network on port `80`
for the production proxy. It uses verified frontend assets built into the image
and persists storage, modules, and public plugin assets in named volumes. Queue
work uses `QUEUE_CONNECTION=sync`; the project does not require Vite, Nginx, a
queue worker, or a scheduler container.

Common commands:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
docker compose ps
docker compose logs -f app
docker compose exec app php artisan about
docker compose down
```

For source-mounted development:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

Add `-f docker-compose.database-net.yml` only on hosts where that external
network already exists. See `SETUP_GUIDE.md` for fresh-install and update
behavior.
