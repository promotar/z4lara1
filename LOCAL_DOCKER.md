# Art INPA Local Docker

The Docker layout is split by purpose:

- `docker-compose.yml`: portable fresh install and production-like runtime;
  contains no source bind, `.env` injection, database container, or required
  external network.
- `docker-compose.dev.yml`: local source mount plus isolated `vendor` and Vite
  build volumes.
- `docker-compose.database-net.yml`: optional connection to an already-created
  external `database-net` network.
- `docker-compose.prod.yml`: standalone production binding on all interfaces.

The base runtime exposes Apache/PHP at `http://127.0.0.1:8088`, uses the
verified frontend assets built into the image, and persists `storage` in the
`art-inpa-storage` volume. Queue work uses `QUEUE_CONNECTION=sync`; the project
does not require Vite, Nginx, a queue worker, or a scheduler container.

Common commands:

```bash
docker compose up -d --build
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
