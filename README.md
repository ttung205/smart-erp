# Smart Distributed ERP

> **Xây dựng hệ thống ERP phân tán theo kiến trúc Microservices hướng sự kiện, ứng dụng RabbitMQ và Transactional Outbox**
> *Building an Event-Driven Microservices ERP System with RabbitMQ and Transactional Outbox*

Hệ thống quản trị doanh nghiệp (bán hàng, mua hàng, kho, tài chính, văn phòng điện tử) được tách thành **8 microservice độc lập**, viết bằng 3 nền tảng (Laravel, .NET, Node.js). Mỗi service có database riêng; các service giao tiếp qua REST đồng bộ và qua **RabbitMQ** bất đồng bộ.

---

## Mục lục

1. [Kiến trúc tổng quan](#1-kiến-trúc-tổng-quan)
2. [Danh sách service](#2-danh-sách-service)
3. [Các cơ chế hệ phân tán](#3-các-cơ-chế-hệ-phân-tán)
4. [Luồng tiêu biểu: phê duyệt và thông báo realtime](#4-luồng-tiêu-biểu-phê-duyệt-và-thông-báo-realtime)
5. [Cài đặt và chạy](#5-cài-đặt-và-chạy)
6. [Tài khoản mẫu](#6-tài-khoản-mẫu)
7. [Quan sát hệ thống](#7-quan-sát-hệ-thống)
8. [Kiểm thử](#8-kiểm-thử)
9. [Cấu trúc thư mục](#9-cấu-trúc-thư-mục)
10. [Tài liệu](#10-tài-liệu)

---

## 1. Kiến trúc tổng quan

```
                         ┌──────────────────────────────────────────┐
                         │   Frontend: Vue 3 + PrimeVue (:5175)     │
                         │   REST qua Vite proxy · Socket.IO        │
                         └───────────────┬──────────────────────────┘
                                         │ JWT (HS256) trên mọi request
                                         ▼
               ┌───────────── API Gateway: Kong (:8000) ────────────┐
               │   /api/v1/<service>/* · X-Correlation-ID · CORS     │
               └──┬──────┬──────┬──────┬──────┬──────┬──────┬───────┘
                  ▼      ▼      ▼      ▼      ▼      ▼      ▼
            ┌─────────┐┌───────┐┌────────┐┌─────────┐┌───────┐┌────────┐┌───────┐┌──────────────┐
            │identity ││catalog││purchase││inventory││ sales ││ eoffice││finance││ notification │
            │ :8001   ││ :8002 ││ :8003  ││ :8004   ││ :8005 ││ :8007  ││ :8008 ││ :8006        │
            │ Laravel ││Laravel││ .NET   ││ .NET    ││Laravel││ Laravel││Laravel││ Node.js      │
            └────┬────┘└───┬───┘└───┬────┘└────┬────┘└───┬───┘└───┬────┘└───┬───┘└──────┬───────┘
                 ▼         ▼        ▼          ▼         ▼        ▼         ▼           ▼
               [DB]      [DB]     [DB]       [DB]      [DB]     [DB]      [DB]        [DB]
                                    │          ▲│        │        │                     ▲
                                    │ publish  ││publish │publish │publish     consume  │
                                    ▼  consume │▼        ▼        ▼                     │
                ┌─────────────────────────────────────────────────────────────────────────┐
                │ RabbitMQ: topic exchange erp.events · dead letter erp.dlx (:5672/:15672)  │
                └─────────────────────────────────────────────────────────────────────────┘
```

Publish qua Transactional Outbox: purchase, inventory, sales, eoffice. Consume: inventory (giữ chỗ / nhập kho), notification (thông báo).

- **Database per service**: mỗi service sở hữu dữ liệu của mình (SQLite khi dev, MySQL 8 khi triển khai). Không service nào đọc trực tiếp DB của service khác.
- **Đồng bộ (REST)**: dùng khi cần kết quả ngay, ví dụ sales gọi inventory để kiểm tra kho xuất, eoffice gọi identity để tìm người duyệt.
- **Bất đồng bộ (RabbitMQ)**: dùng cho domain event như đơn hàng, nhập kho, phê duyệt. Bên phát không cần biết bên nhận là ai.

## 2. Danh sách service

| Service | Port | Công nghệ | Trách nhiệm chính |
| :--- | :--- | :--- | :--- |
| `identity-service` | 8001 | Laravel 13 / PHP | Người dùng, phòng ban, vai trò, phân quyền RBAC, cấp JWT |
| `catalog-service` | 8002 | Laravel 13 / PHP | Danh mục sản phẩm, đối tác (master data) |
| `purchase-service` | 8003 | ASP.NET Core (.NET 10) | Đề nghị mua (PR), đơn mua (PO), nhập hàng |
| `inventory-service` | 8004 | ASP.NET Core (.NET 10) | Kho, tồn kho, giữ chỗ tồn kho (atomic reservation) |
| `sales-service` | 8005 | Laravel 13 / PHP | Báo giá, đơn bán, yêu cầu giao hàng, chứng từ xuất kho, hóa đơn GTGT |
| `notification-service` | 8006 | Node.js 24 / Express | Hộp thư thông báo, đẩy realtime (Socket.IO), gửi email |
| `eoffice-service` | 8007 | Laravel 13 / PHP | Đơn nghỉ phép, luồng phê duyệt nội bộ |
| `finance-service` | 8008 | Laravel 13 / PHP | Thu chi, công nợ, chứng từ kế toán |

Hạ tầng (Docker Compose): **RabbitMQ 3.13** (message broker), **Kong OSS** DB-less (API gateway), **MySQL 8** (database nghiệp vụ, kho phân tích và hội thoại AI), **MongoDB 7** (chat-service và notification-service), **Mailpit** (SMTP giả lập để test email).

## 3. Các cơ chế hệ phân tán

| Vấn đề của hệ phân tán | Giải pháp trong dự án | Vị trí |
| :--- | :--- | :--- |
| Ghi DB xong nhưng gửi message lỗi (dual-write) | **Transactional Outbox**: event được ghi vào bảng `outbox_events` trong cùng transaction với nghiệp vụ, relay chạy nền đẩy lên broker | `sales`, `eoffice` (`php artisan outbox:relay`), `purchase`, `inventory` (BackgroundService) |
| Message bị gửi lặp (at-least-once) | **Idempotent consumer**: dùng `event_id` làm khóa chống trùng (`processed_events`, `UNIQUE(event_id, recipient_id)`) | `inventory`, `notification` |
| Consumer lỗi tạm thời | **Retry queue** có TTL, quá số lần thì chuyển sang **Dead Letter Exchange** `erp.dlx` | `notification-service`, `definitions.json` |
| Định tuyến message | **Topic exchange** `erp.events`, routing key dạng `<service>.<aggregate>.<action>` | `infrastructure/rabbitmq/definitions.json` |
| Hợp đồng dữ liệu giữa các service | **Envelope chuẩn** (tương thích CloudEvents): `event_id`, `event_type`, `correlation_id`, `producer`, `payload` | `docs/contracts/events-spec.md` |
| Xác thực không tập trung | **JWT HS256** do identity cấp (`JwtIssuer`); mọi service kiểm tra cùng một bộ luật (alg, chữ ký, `iss`, `sub`, `exp`) bằng secret dùng chung, không cần gọi lại identity | `app/Auth/JwtVerifier` (PHP), `IAccessTokenValidator` → `HmacJwtTokenValidator` (.NET), `auth/jwt.js` (Node) |
| Service gọi nhau không có token người dùng | **Internal service key**: header `X-Internal-Service-Key`, chỉ được đọc; gateway xóa header này từ client | identity (`user_or_service`), catalog (`internal`), eoffice, notification, purchase |
| Truy vết request xuyên service | **Correlation ID** sinh ở gateway, đi theo request và được ghi vào event | `infrastructure/kong/kong.yml`, envelope |
| Đồng thời khi giữ chỗ tồn kho | **Atomic reservation**, kiểm tra và trừ tồn trong một thao tác | `inventory-service` |
| Thông báo thời gian thực | **Socket.IO** chia room theo `user:{id}`; DB là nguồn sự thật, socket chỉ để báo có thay đổi | `notification-service`, `frontend` |
| Hai relay cùng publish một event | **Single-instance lock** (`flock`) | `RelayOutboxEvents` |

## 4. Luồng tiêu biểu: phê duyệt và thông báo realtime

Ví dụ: nhân viên kinh doanh gửi duyệt một báo giá.

```
 Sale bấm "Gửi duyệt"
   │
   ▼
 sales-service ── DB::transaction { quotation.status = PENDING_APPROVAL ; INSERT outbox_events }
   │
   ▼  outbox:relay (publisher confirm)
 RabbitMQ erp.events ── routing key: sales.quotation.submitted ──► queue erp.notification.events
   │
   ▼
 notification-service
   1. Bỏ qua nếu event_id đã xử lý
   2. Rule: QuotationSubmitted → người có quyền quotation.approve (hỏi identity-service, cache 5 phút)
   3. Ghi thông báo cho từng người nhận (trừ chính người gửi) → ACK
   4. Socket.IO emit tới room user:{id}
   │
   ▼
 Trình duyệt người duyệt: chuông +1, hiện toast "Báo giá QT-... cần phê duyệt"
```

Khi một người duyệt xong, event `sales.quotation.approved` sẽ:
- đóng việc cần làm của những người duyệt còn lại (chuyển sang "Đã xử lý");
- gửi thông báo kết quả cho người tạo báo giá.

Các luồng thông báo đang có:

| Sự kiện | Người nhận |
| :--- | :--- |
| Báo giá / đơn hàng cần duyệt | Người có quyền `quotation.approve` / `order.approve` |
| Yêu cầu giao hàng | Người có quyền duyệt giao hàng `delivery.manage` |
| Yêu cầu xuất kho | Người có quyền kho `inventory.manage` / `inventory.issue` |
| Đơn nghỉ phép | Người duyệt (line manager); kết quả duyệt gửi lại người làm đơn |

Chi tiết: [docs/notification-architecture.md](docs/notification-architecture.md).

## 5. Cài đặt và chạy

### 5.1. Yêu cầu môi trường

| Công cụ | Phiên bản | Ghi chú |
| :--- | :--- | :--- |
| PHP | ≥ 8.3 | extension `pdo_sqlite`, `sqlite3`, `mbstring`, `curl`, `sockets` (cho `php-amqplib`) |
| Composer | ≥ 2.5 | |
| .NET SDK | 10 | purchase-service, inventory-service |
| Node.js | ≥ 24 | notification-service và frontend (Vite) |
| Docker Desktop | bản mới | RabbitMQ, Mailpit (bắt buộc để chạy luồng message) |
| Windows Terminal | | `start_dev.ps1` mở mỗi service một tab |

### 5.2. Cài đặt lần đầu

```powershell
git clone <repo-url> smart-erp
cd smart-erp
.\setup.ps1            # hoặc double-click setup.bat
```

`setup.ps1` thực hiện cho từng service:
- copy `.env.example` thành `.env`;
- chạy `composer install` / `dotnet build` / `npm install`;
- tạo database SQLite và seed dữ liệu mẫu.

> Mọi service phải dùng **cùng một `JWT_SECRET`** và **cùng một `INTERNAL_SERVICE_KEY`** (.NET: `Jwt:Secret`, `Services:InternalKey`). Các file `.env.example` / `appsettings.json` đã có sẵn giá trị dùng cho môi trường dev; khi triển khai phải thay bằng giá trị riêng. Code không còn giá trị mặc định: thiếu cấu hình thì service báo lỗi thay vì chạy với secret công khai.

### 5.3. Khởi động

```powershell
docker compose up -d rabbitmq mailpit     # message broker + SMTP test
.\start_dev.ps1                           # hoặc double-click start_dev.bat
```

`start_dev.ps1` mở một cửa sổ Windows Terminal, mỗi tiến trình một tab:
- 8 service;
- frontend;
- 2 worker **Outbox Relay** (sales, eoffice).

Tiến trình nào đang chạy rồi sẽ được bỏ qua. Dùng `.\start_dev.ps1 -Preview` để xem trước danh sách tab sẽ mở.

| Địa chỉ | Mô tả |
| :--- | :--- |
| http://localhost:5175 | **Giao diện web** |
| http://localhost:15672 | RabbitMQ Management (`erp_rabbit` / `rabbit_secret`) |
| http://localhost:8025 | Mailpit, xem email đã gửi |
| http://127.0.0.1:8003/swagger · http://127.0.0.1:8004/swagger | Swagger của purchase / inventory |

> Khi dev, frontend gọi thẳng các service qua Vite proxy (`frontend/vite.config.js`). Kong gateway (`:8000`) được cấu hình cho môi trường container, nơi mỗi service chạy với hostname riêng.

### 5.4. Chạy thủ công một service

```powershell
cd services\sales-service
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8005     # tab 1: API
php artisan outbox:relay          # tab 2: đẩy domain event lên RabbitMQ
```

## 6. Tài khoản mẫu

Mật khẩu mặc định của mọi tài khoản: **`password123`**

| Email | Vai trò | Mô tả |
| :--- | :--- | :--- |
| `admin@erp.local` | `SUPER_ADMIN` | Toàn quyền hệ thống |
| `sale@erp.local` | `SALE` | Chuyên viên kinh doanh |
| `sale.manager@erp.local` | `SALE_MANAGER` | Trưởng phòng kinh doanh, duyệt báo giá / đơn hàng |
| `purchase@erp.local` | `PURCHASE` | Chuyên viên mua hàng |
| `purchase.manager@erp.local` | `PURCHASE_MANAGER` | Trưởng phòng mua hàng |
| `technical@erp.local` | `TECHNICAL` | Kỹ thuật viên |
| `technical.manager@erp.local` | `TECHNICAL_MANAGER` | Trưởng phòng kỹ thuật |
| `finance@erp.local` | `FINANCE_ACCOUNTING` | Chuyên viên kế toán |
| `finance.manager@erp.local` | `FINANCE_ACCOUNTING_MANAGER` | Kế toán trưởng |
| `logistic@erp.local` | `LOGISTIC` | Chuyên viên kho vận |
| `logistic.manager@erp.local` | `LOGISTIC_MANAGER` | Trưởng phòng kho vận |

**Demo thông báo realtime:**
1. Mở 2 trình duyệt (hoặc 1 cửa sổ thường + 1 cửa sổ ẩn danh).
2. Đăng nhập `sale@erp.local` ở cửa sổ thứ nhất, `sale.manager@erp.local` ở cửa sổ thứ hai.
3. Ở cửa sổ sale: tạo báo giá rồi bấm **Gửi duyệt**.
4. Chuông ở cửa sổ manager sáng lên ngay. Manager duyệt xong thì sale nhận thông báo kết quả.

## 7. Quan sát hệ thống

| Muốn xem | Ở đâu |
| :--- | :--- |
| Event đã được ghi chưa | Bảng `outbox_events` của sales/eoffice: `PENDING` → `PUBLISHED` (lỗi: `FAILED` + `last_error`) |
| Relay có đẩy được không | Tab **Sales/EOffice Outbox Relay**: `Đã publish N event.` |
| Message đi qua broker | RabbitMQ UI → **Exchanges › erp.events**, **Queues › erp.notification.events** |
| Nội dung message (JSON envelope) | Queue `erp.analytics.events` (bind `*.*.*`, chưa có consumer nên message nằm lại) → **Get messages** |
| Message lỗi | Queue `erp.notification.retry` (chờ thử lại), `erp.dead_letters` (đã bỏ) |
| Kết quả xử lý | Tab **Notification Service**: `[AMQP] QuotationSubmitted (...) → { created: 1, resolved: 0 }` |

## 8. Kiểm thử

```powershell
cd services\identity-service;     php artisan test
cd services\sales-service;        php artisan test
cd services\eoffice-service;      php artisan test
cd services\finance-service;      php artisan test
cd services\notification-service; npm test
cd frontend;                      npm run lint; npm run build
```

## 9. Cấu trúc thư mục

```text
smart-erp/
├── docker-compose.yml          # RabbitMQ, Kong, MySQL, Mailpit
├── infrastructure/kong/       # Declarative routes và gateway plugins
├── gateway/nginx.conf          # Gateway cũ, dùng cho profile rollback
├── infrastructure/
│   ├── mysql/init/             # Khởi tạo schema MySQL cho từng service
│   └── rabbitmq/               # definitions.json: exchange, queue, binding, DLX
├── packages/shared-kernel/     # Hợp đồng dùng chung (envelope, outbox model, enum)
├── services/
│   ├── identity-service/       # :8001  Laravel · RBAC, JWT
│   ├── catalog-service/        # :8002  Laravel · Master data
│   ├── purchase-service/       # :8003  .NET · PR/PO, outbox relay
│   ├── inventory-service/      # :8004  .NET · Tồn kho, consumer, outbox
│   ├── sales-service/          # :8005  Laravel · Bán hàng, outbox relay
│   ├── notification-service/   # :8006  Node.js · Thông báo, Socket.IO, email
│   ├── eoffice-service/        # :8007  Laravel · Nghỉ phép, phê duyệt, outbox relay
│   └── finance-service/        # :8008  Laravel · Tài chính kế toán
├── frontend/                   # Vue 3 + PrimeVue + Tailwind CSS
├── docs/                       # Đặc tả API, event, kiến trúc
├── rules/                      # Quy chuẩn giao diện
├── setup.ps1 / setup.bat       # Cài đặt lần đầu
└── start_dev.ps1 / start_dev.bat
```

## 10. Tài liệu

| Tài liệu | Nội dung |
| :--- | :--- |
| [docs/contracts/events-spec.md](docs/contracts/events-spec.md) | Envelope chuẩn và danh mục domain event |
| [docs/contracts/api-spec.md](docs/contracts/api-spec.md) | Đặc tả REST API |
| [docs/notification-architecture.md](docs/notification-architecture.md) | Kiến trúc thông báo qua message queue |
| [docs/realtime-architecture-roadmap.md](docs/realtime-architecture-roadmap.md) | Lộ trình đồng bộ realtime và chat nội bộ |
| [rules/ui-guidelines.md](rules/ui-guidelines.md) | Quy chuẩn giao diện |
