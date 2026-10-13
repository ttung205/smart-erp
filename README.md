# Smart Distributed ERP

> Hệ thống Quản trị Doanh nghiệp Thông minh theo Kiến trúc Microservices Phân tán (Đồ án liên ngành CNTT & QTKD/SCM v2.0.0).

---

## 1. Yêu Cầu Môi Trường (Prerequisites)

Để chạy dự án trên máy tính của bạn, cần cài đặt:
- **PHP** >= 8.2 (kèm extension `pdo_sqlite`, `pdo_mysql`, `curl`, `mbstring`)
- **Composer** >= 2.5
- **Node.js** >= 18 và **NPM** >= 9
- *(Tùy chọn cho Production)*: **Docker Desktop** (chạy MySQL 8.0, RabbitMQ 3.13, NGINX Gateway)

---

## 2. Hướng Dẫn Dành Cho Developer Mới Clone Dự Án Về

### 🚀 Cách 1: Tự Động 1-Click (Khuyên dùng cho Windows)

Sau khi clone repo về máy, mở thư mục dự án và chạy:

1. **Chạy kịch bản Setup tự động**:
   - Double-click vào file: `setup.bat`
   - Hoặc mở PowerShell gõ: `.\setup.ps1`
   *(Kịch bản này sẽ tự động cài `composer install`, copy `.env`, tạo database SQLite và seed sẵn dữ liệu mẫu cho cả 2 service, đồng thời chạy `npm install` cho Frontend).*

2. **Khởi động toàn bộ hệ thống**:
   - Double-click vào file: `start_dev.bat`
   - Hoặc mở PowerShell gõ: `.\start_dev.ps1`
   *(Hệ thống sẽ tự động bật 3 cửa sổ console cho Identity Service, Catalog Service và Vue 3 Frontend).*

3. **Mở trình duyệt web truy cập**:
   - **Frontend Web UI**: [http://localhost:5174](http://localhost:5174)

---

### 🛠️ Cách 2: Chạy Thủ Công Từng Bước (Manual CLI)

Nếu bạn muốn tự chạy từng dòng lệnh trên Terminal:

#### Bước 1: Thiết lập Identity Service (:8001)
```bash
cd services/identity-service
composer install
cp .env.example .env          # Trên Windows: copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --port=8001
```

#### Bước 2: Thiết lập Catalog Service (:8002) (Mở Terminal thứ 2)
```bash
cd services/catalog-service
composer install
cp .env.example .env          # Trên Windows: copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --port=8002
```

#### Bước 3: Thiết lập Frontend Vue 3 (Mở Terminal thứ 3)
```bash
cd frontend
npm install
npm run dev
```

Truy cập: **[http://localhost:5174](http://localhost:5174)**

---

### 🐳 Cách 3: Chạy Toàn Bộ Bằng Docker Compose (Production-like)

Nếu muốn chạy hạ tầng phân tán đầy đủ với MySQL, RabbitMQ và NGINX Gateway:
```bash
# Khởi động MySQL 8.0, RabbitMQ và NGINX
docker compose up -d

# Truy cập NGINX Gateway tại: http://localhost:8000
# Truy cập RabbitMQ Management tại: http://localhost:15672 (user: erp_admin, pass: erp_secret)
```

---

## 3. Danh Sách Tài Khoản Mẫu Đăng Nhập (Persona Seed Data)

Mật khẩu mặc định cho tất cả các tài khoản là: `password123`

| Họ và Tên | Email | Phòng Ban | Vai Trò | Quyền Hạn Nổi Bật |
|---|---|---|---|---|
| **Nguyễn Văn Giám Đốc** | `director@erp.local` | Ban Giám Đốc (BOD) | `GENERAL_DIRECTOR` | Toàn quyền quản trị hệ thống |
| **Trần Thị Trưởng Phòng Sales** | `sales_mgr@erp.local` | Phòng Sales | `SALES_MANAGER` | Quản lý sales, `catalog.create`, `catalog.edit` |
| **Lê Văn Nhân Viên Sales** | `sales@erp.local` | Phòng Sales | `SALES_REP` | `sales.create`, `catalog.view` *(Không có quyền tạo Catalog)* |
| **Phạm Văn Mua Hàng** | `purchase_mgr@erp.local` | Phòng Mua Hàng | `PURCHASE_MANAGER` | Quản lý mua hàng, duyệt PO |
| **Hoàng Văn Thủ Kho** | `warehouse@erp.local` | Kho Vận | `WAREHOUSE_KEEPER` | Quản lý tồn kho, nhập xuất kho |
| **Quản Trị Viên Hệ Thống** | `admin@erp.local` | Ban CNTT / IT | `ADMIN` | Quản lý phân quyền RBAC và người dùng |

---

## 4. Cấu Trúc Thư Mục Dự Án (Monorepo)

```text
smart-erp/
├── gateway/                 # NGINX API Gateway (:8000)
├── infrastructure/          # Docker compose, MySQL init scripts, RabbitMQ conf
├── packages/
│   └── shared-kernel/       # Thư viện dùng chung (Outbox, Idempotency, Enums)
├── services/                # Các Microservices độc lập
│   ├── identity-service/    # Port 8001 | erp_identity_db (Users, Roles, RBAC, JWT)
│   ├── catalog-service/     # Port 8002 | erp_catalog_db (Master Data, Outbox)
│   ├── purchase-service/    # Port 8003 | erp_purchase_db (Purchase Orders)
│   ├── inventory-service/   # Port 8004 | erp_inventory_db (Stock, Atomic Concurrency)
│   ├── sales-service/       # Port 8005 | erp_sales_db (Sales Orders, Saga)
│   ├── eoffice-service/     # Port 8006 | erp_eoffice_db (Approval Workflow)
│   └── ai-service/          # Port 8007 | Python FastAPI (Permission-aware Copilot)
├── frontend/                # Vue 3 + PrimeVue 4 (Clean Canvas Design System)
├── docs/                    # Tài liệu đặc tả Contracts, CloudEvents, SRS
├── setup.bat / setup.ps1    # Script khởi tạo dự án tự động
└── start_dev.bat / ps1      # Script khởi động toàn bộ service dev
```
