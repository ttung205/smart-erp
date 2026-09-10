# Smart Distributed ERP

Hệ thống Quản trị Doanh nghiệp Thông minh theo Kiến trúc Microservices (Smart Distributed ERP v2.0.0).

## 1. Cấu Trúc Dự Án (Monorepo)

```text
smart-erp/
├── gateway/                 # NGINX API Gateway (:8000)
├── infrastructure/          # Docker compose, MySQL scripts, RabbitMQ setup
├── packages/
│   └── shared-kernel/       # Thư viện PHP dùng chung (Outbox, Idempotency, Events, Enums)
├── services/                # Các Microservices độc lập
│   ├── identity-service/    # Port 8001 | erp_identity_db
│   ├── catalog-service/     # Port 8002 | erp_catalog_db
│   ├── purchase-service/    # Port 8003 | erp_purchase_db
│   ├── inventory-service/   # Port 8004 | erp_inventory_db
│   ├── sales-service/       # Port 8005 | erp_sales_db
│   ├── eoffice-service/     # Port 8006 | erp_eoffice_db
│   └── ai-service/          # Port 8007 | erp_analytics_db
├── frontend/                # Vue 3 + PrimeVue + Tailwind CSS
└── docs/                    # Tài liệu đặc tả Contracts, Diagrams, SRS
```
