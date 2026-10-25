# Smart ERP deployment runbook

## Architecture baseline

The default gateway is Kong OSS in DB-less mode. Its version is pinned in
`docker-compose.yml`, and all routes and plugins are declared in
`infrastructure/kong/kong.yml`. The old Nginx gateway remains available only as
the `legacy-gateway` profile for rollback and comparison.

Kong provides:

- a stable `/api/v1/*` façade over all services;
- request correlation through `X-Correlation-ID`;
- CORS handling at the edge;
- removal of client-supplied `X-Internal-Service-Key`;
- WebSocket pass-through for notification and chat;
- a gateway `/health` endpoint.

## First deployment

1. Copy `.env.example` to `.env`.
2. Replace every `change-me` value and generate five independent Laravel
   `APP_KEY` values. Never commit `.env`.
3. Run `./scripts/deploy.ps1` from PowerShell.
4. Open the application at `http://localhost:5175` and Kong at
   `http://localhost:8000`.

The deploy script validates Compose, builds images, waits for MySQL and
RabbitMQ, runs Laravel migrations as explicit one-off jobs, starts the complete
stack, then checks all gateway routes. Use `-SkipBuild` or `-SkipMigrations`
only when that stage was completed separately.

## Routine operations

```powershell
# Status and health
docker compose ps
./scripts/smoke-test.ps1

# Follow a service and gateway
docker compose logs -f --tail 200 sales-service gateway

# Rebuild and roll one service
docker compose build sales-service
docker compose up -d --no-deps sales-service

# Validate a Kong change before restart
docker run --rm -e KONG_DATABASE=off `
  -v "${PWD}/infrastructure/kong/kong.yml:/kong/declarative/kong.yml:ro" `
  kong:3.9.1 kong config parse /kong/declarative/kong.yml
docker compose up -d --no-deps --force-recreate gateway
./scripts/smoke-test.ps1
```

## Database changes and rollback

Web containers do not run migrations on startup. Back up MySQL before a schema
release, run forward-compatible migrations, deploy the application, and only
remove old columns in a later release. Laravel migration commands are executed
by `scripts/deploy.ps1`.

The MySQL init directory runs only when the data volume is created. Editing an
init script does not update an existing volume. Apply new permissions or schema
changes through versioned migrations or an explicit DBA operation; do not
delete the volume to force initialization.

To compare the previous gateway without replacing Kong:

```powershell
docker compose --profile legacy-gateway up -d gateway-legacy
# Nginx legacy gateway is exposed on http://localhost:8100
```

To roll back Kong traffic, stop the `gateway` service and temporarily change
the legacy port mapping from `8100:80` to `8000:80`, then run the smoke test.

## Known limitation

The AI service currently starts with warehouse synchronization disabled in
Docker because its ETL still expects service-local SQLite sources, while core
services use MySQL. `/health` remains available and reports both
`analytics_ready` and `warehouse_sync_enabled`. Replace this with an event/API-
based ingestion path before enabling AI analytics in production.
