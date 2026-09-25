# Self-hosting Straden

Straden runs as a small set of containers from a single image:

| Service | What it does |
|---|---|
| `app` | Web UI + MCP server (FrankenPHP/Caddy). Proxies websockets to Reverb and handles HTTPS. Runs database migrations on start. |
| `reverb` | Websocket server for live run progress, chat streaming and notifications. |
| `runner` | Queue worker (Horizon) that executes k6 load tests. Ships with **k6 v2.0.0**. |
| `scheduler` | Checks running tests and finalises them. |
| `postgres` | Application database. |
| `redis` | Cache, sessions and queues. |
| `influxdb` | **InfluxDB 1.8** — k6 time-series metrics. |

## Quick start (local)

```bash
mkdir straden && cd straden
curl -fsSLO https://raw.githubusercontent.com/kwasii1/straden/main/deploy/compose.yaml
curl -fsSL https://raw.githubusercontent.com/kwasii1/straden/main/deploy/.env.example -o .env
docker compose up -d
```

Open <http://localhost:8000>. On first visit Straden asks you to create the admin account. There is no public registration; the admin adds everyone else under **Settings → Users**.

### Creating the admin without the browser

Set these in `.env` before the first start. They're only used while the instance has no users:

```dotenv
STRADEN_ADMIN_EMAIL=you@example.com
STRADEN_ADMIN_PASSWORD=a-long-password
```

Locked out? Create or promote an admin and reset their password from the CLI:

```bash
docker compose exec app php artisan straden:admin you@example.com
```

## Running on a VPS (with HTTPS)

1. Point a DNS record (e.g. `straden.example.com`) at the server.
2. In `.env`:

   ```dotenv
   STRADEN_DOMAIN=straden.example.com
   APP_URL=https://straden.example.com
   HTTP_PORT=80
   HTTPS_PORT=443
   DB_PASSWORD=change-me-before-first-start
   ```

3. `docker compose up -d`

Caddy obtains and renews the Let's Encrypt certificate automatically. Websockets use the same origin (`wss://straden.example.com/app/...`), so there is no extra port to open.

### Behind your own reverse proxy

If nginx, Traefik or a load balancer already terminates TLS, keep `STRADEN_DOMAIN=:80`, set `APP_URL` to the public HTTPS URL, and proxy everything (including websocket upgrades on `/app/*`) to the `app` container's port 80.

## Secrets

`APP_KEY` and the Reverb credentials are generated on first start and stored in the `storage` volume (`storage/app/.straden-secrets`). AI provider keys are encrypted with `APP_KEY`, so back this volume up. To manage the secrets yourself, set `APP_KEY`, `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` in `.env`.

## Upgrading

```bash
docker compose pull
docker compose up -d
```

Migrations run automatically. Pin `STRADEN_VERSION` (e.g. `1.2.0`) in production and bump it deliberately.

## Backups

```bash
# Database
docker compose exec postgres pg_dump -U straden straden > straden.sql
# Scripts, run logs, cloned repositories and secrets
docker run --rm -v straden_storage:/data -v "$PWD":/backup alpine tar czf /backup/storage.tgz -C /data .
# Metrics
docker run --rm -v straden_influxdb:/data -v "$PWD":/backup alpine tar czf /backup/influxdb.tgz -C /data .
```

## Load generator capacity

k6 runs inside the `runner` container, so the host's CPU and network bandwidth limit how much load you can generate. Uncomment the `deploy.resources` block in `compose.yaml` to cap the runner on a shared host.

## Building the image yourself

From a checkout of the repository:

```bash
cd deploy
docker compose -f compose.yaml -f compose.build.yaml up -d --build
```
