# Smart ERP Sales Service

Laravel service for quotations, sales orders, delivery requests, sales documents, warehouse posting, VAT invoices, attachments, and sales/revenue reports.

## Requirements

- PHP 8.3 or newer
- Composer
- MySQL for local integration, or SQLite for tests
- Identity Service for JWT issuance
- Inventory Service for stock reservation and goods issue
- Catalog and Identity databases configured as the `catalog` and `identity` connections

## Local setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8005
```

Set `JWT_SECRET` to the same HS256 secret used by Identity Service. Configure `INVENTORY_SERVICE_URL` and the database connections before starting the service.

## Authentication and permissions

Except for `GET /api/health`, every endpoint requires:

```http
Authorization: Bearer <identity-service-jwt>
Accept: application/json
```

The token must be signed with HS256, have issuer `identity-service`, contain a valid `sub` and `exp`, and include `roles` and/or `permissions`. `SUPER_ADMIN` bypasses individual permission checks. Other callers need the permission declared on each route in `routes/api.php`.

## Main API groups

All paths below are relative to `/api` inside this service. Through the gateway they are normally exposed under `/api/v1/sales`.

| Area | Endpoints |
|---|---|
| Health | `GET /health` |
| Quotations | `GET/POST /quotations`, `GET/PUT/PATCH/DELETE /quotations/{id}`, approval and conversion actions |
| Sales orders | `GET/POST /orders`, `GET/PUT/PATCH/DELETE /orders/{id}`, approval actions |
| Deliveries | `GET/POST /deliveries`, `PUT /deliveries/{id}/status` |
| Sales documents | `GET/POST /invoices`, `GET /invoices/{id}`, reserve, logistics, warehouse and accounting actions |
| VAT invoices | `GET/POST /vat-invoices`, next number and unbilled item endpoints |
| Reports | `GET /reports/sale-items`, `GET /reports/revenue-items` and detail endpoints |
| Attachments | filtered list, private upload, authenticated download and delete |

List endpoints accept `page` and `per_page`. `per_page` defaults to 50 and is capped at 200. Responses return rows in `data` and pagination information in `meta`.

## Workflows

- Quotation: `DRAFT/REJECTED -> PENDING_APPROVAL -> APPROVED -> CONVERTED`
- Sales order: `DRAFT/REJECTED -> PENDING_APPROVAL -> APPROVED`
- Delivery transitions are validated by `App\Support\SalesWorkflow`.
- Documents in an approval workflow cannot be edited or deleted after they are locked.
- Totals, VAT, document numbers and actor fields are calculated by the server.
- Warehouse posting uses a stable reference per invoice item. Retrying the same posting is idempotent in both Sales and Inventory.
- VAT creation locks the selected revenue rows and rejects quantities above the unbilled balance.

## Verification

```bash
php artisan test
vendor/bin/pint --dirty
php artisan route:list --path=api
```

The test suite covers JWT and permission checks, approval transitions, idempotent warehouse posting, and VAT over-invoicing protection.
