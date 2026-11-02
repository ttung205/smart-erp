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
- a gateway `/health` endpoint;
- rate limiting per client IP (600/min overall, 20/min on `/api/v1/auth`, 10/min on `/api/v1/ai`);
- per-service connect/read timeouts and 2 retries (nginx never retries non-idempotent methods);
- Prometheus metrics on the status listener (`gateway:8100/metrics`) and an access log that includes
  `correlation_id`.
- real client IP: browsers reach Kong through the frontend's nginx, so Kong trusts `X-Forwarded-For` from private
  networks (`KONG_TRUSTED_IPS`, `KONG_REAL_IP_HEADER`) and rate limiting counts per user instead of per nginx container.
  nginx overwrites `X-Forwarded-For` with the connecting address, so a client cannot choose its own IP. If a load
  balancer is ever placed in front of nginx, switch that header back to `$proxy_add_x_forwarded_for` and trust the
  balancer's address range.

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

## Monitoring

The monitoring stack is an opt-in Compose profile so the core system still runs on smaller machines:

```powershell
docker compose --profile monitoring up -d
```

| Component | Role | Access |
|---|---|---|
| Grafana 12 | Dashboards *ERP Overview*, *RabbitMQ*, *Logs*; alert list | `http://127.0.0.1:3000` (user `admin`, password `GRAFANA_ADMIN_PASSWORD`) |
| Prometheus 3 | Scrapes Kong and RabbitMQ (`rabbitmq_prometheus`); evaluates `infrastructure/monitoring/prometheus/alerts.yml` | `http://127.0.0.1:9090` (dev only) |
| Loki 3 | Log store, 7-day retention | internal |
| Grafana Alloy | Tails stdout/stderr of every `smart-erp` container via the Docker socket, labels each stream with `service` | internal |

Alerts: `DeadLetterNotEmpty` (message stuck in `erp.dead_letters`), `ServiceHigh5xx` (more than 5% 5xx through Kong for 5 min),
`ScrapeTargetDown`.

Tracing one request: copy `X-Correlation-ID` from the response headers, open the *Logs* dashboard and paste it into
the search box. Kong's access log always carries the id; Chat and Purchase also include it when they log an error.

Container CPU/RAM is not collected: cAdvisor cannot read containers on Docker Desktop with the containerd image store.

## Resilience

| Mechanism | Where |
|---|---|
| Transactional Outbox + publisher confirms | Catalog, Sales, E-Office (relay containers), Purchase, Inventory (background workers) |
| Retry queue (10 s × 5) then dead letter `erp.dlx` → `erp.dead_letters` | Notification consumers |
| Idempotent consumers (`event_id`) | Notification, Inventory |
| Circuit breaker: opens 30 s after 5 consecutive failures (connection error or 5xx; 4xx does not count), one half-open probe | Sales → Inventory / Finance / Identity / Catalog; E-Office → Identity / Finance (cache-based Guzzle middleware); Chat → Identity, Purchase → Catalog / Identity / Notification (Polly, `Microsoft.Extensions.Http.Resilience`) |
| Retry 2× on connection errors only | Calls that are safe to repeat: reads, and Inventory reserve / release / issue (idempotent per document). Never on POSTs to Finance, sales-return transfers, or email sends |
| Rate limiting, timeouts | Kong |
| Health checks + `restart: unless-stopped` | Every container |

`GET /api/v1/sales/health` reports the state of each Sales circuit breaker (`CLOSED`, `OPEN`, `HALF_OPEN`).

Stock reservations left behind by a failed invoice creation (Sales timed out or crashed after Inventory had
already reserved) are released by `sales:reconcile-reservations`, scheduled every 5 minutes in the
`sales-scheduler` container. Run it by hand with `--dry-run` to only list them:

```powershell
docker compose exec sales-scheduler php artisan sales:reconcile-reservations --dry-run
```

Outbox relays (Sales, E-Office, Catalog in PHP; Purchase, Inventory in .NET) never drop an event while RabbitMQ
is down: they back off up to 60 s and resume in order once the broker is back.

Dead letters: after fixing the cause, list and replay them with

```powershell
./scripts/replay-dead-letters.ps1            # list only
./scripts/replay-dead-letters.ps1 -Replay    # re-publish to the original exchange/routing key
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

The AI service keeps its conversation history and analytics read model in the
MySQL schema `erp_analytics_db`. Warehouse synchronization is off by default in
Docker (`AI_WAREHOUSE_SYNC_ENABLED=false`) because the ETL reads the other
services' MySQL schemas directly, which crosses service boundaries. Set
`AI_WAREHOUSE_SYNC_ENABLED=true` for demos only. `/health` reports both
`analytics_ready` and `warehouse_sync_enabled`. Replace the ETL with an event/API-
based ingestion path before enabling AI analytics in production.
