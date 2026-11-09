---
title: Triển khai và vận hành
group: technical
audience: developer, admin
source: docs/deployment.md
verified_by: null
verified_at: null
---

# Sổ tay triển khai Smart ERP

## Kiến trúc nền

Gateway mặc định là Kong OSS chạy ở chế độ DB-less. Phiên bản được ghim trong `docker-compose.yml`, còn toàn bộ route và plugin được khai báo trong `infrastructure/kong/kong.yml`. Gateway Nginx cũ chỉ còn ở profile `legacy-gateway`, để rollback và so sánh.

Kong cung cấp:

- một lớp `/api/v1/*` ổn định phía trước mọi service;
- gắn correlation qua `X-Correlation-ID`;
- xử lý CORS ở biên;
- xóa header `X-Internal-Service-Key` do client gửi lên;
- chuyển tiếp WebSocket cho notification và chat;
- endpoint `/health` của gateway;
- giới hạn tốc độ theo IP (600/phút tổng, 20/phút cho `/api/v1/auth`, 10/phút cho `/api/v1/ai`);
- timeout kết nối/đọc theo từng service và 2 lần retry (Nginx không bao giờ retry các method không idempotent);
- metric Prometheus trên listener trạng thái (`gateway:8100/metrics`) và access log có `correlation_id`.

## Triển khai lần đầu

1. Sao chép `.env.example` thành `.env`.
2. Thay mọi giá trị `change-me` và tạo năm giá trị `APP_KEY` Laravel độc lập. Không bao giờ commit `.env`.
3. Chạy `./scripts/powershell/deploy.ps1` trong PowerShell.
4. Mở ứng dụng tại `http://localhost:5175` và Kong tại `http://localhost:8000`.

Script deploy kiểm tra Compose, build image, chờ MySQL và RabbitMQ sẵn sàng, chạy migration Laravel như các job một lần riêng biệt, khởi động toàn bộ stack, rồi kiểm tra mọi route của gateway. Chỉ dùng `-SkipBuild` hoặc `-SkipMigrations` khi giai đoạn đó đã được hoàn thành riêng.

## Vận hành hằng ngày

```powershell
# Trạng thái và sức khỏe
docker compose ps
./scripts/powershell/smoke-test.ps1

# Theo dõi log một service và gateway
docker compose logs -f --tail 200 sales-service gateway

# Build lại và cập nhật một service
docker compose build sales-service
docker compose up -d --no-deps sales-service

# Kiểm tra thay đổi Kong trước khi khởi động lại
docker run --rm -e KONG_DATABASE=off `
  -v "${PWD}/infrastructure/kong/kong.yml:/kong/declarative/kong.yml:ro" `
  kong:3.9.1 kong config parse /kong/declarative/kong.yml
docker compose up -d --no-deps --force-recreate gateway
./scripts/powershell/smoke-test.ps1
```

## Giám sát

Stack giám sát là một profile Compose tùy chọn, để hệ thống chính vẫn chạy được trên máy cấu hình thấp:

```powershell
docker compose --profile monitoring up -d
```

| Thành phần | Vai trò | Truy cập |
|---|---|---|
| Grafana 12 | Dashboard *ERP Overview*, *RabbitMQ*, *Logs*; danh sách cảnh báo | `http://127.0.0.1:3000` (user `admin`, mật khẩu `GRAFANA_ADMIN_PASSWORD`) |
| Prometheus 3 | Thu từ Kong và RabbitMQ (`rabbitmq_prometheus`); đánh giá `infrastructure/monitoring/prometheus/alerts.yml` | `http://127.0.0.1:9090` (chỉ dùng cho dev) |
| Loki 3 | Kho log, lưu giữ 7 ngày | nội bộ |
| Grafana Alloy | Thu stdout/stderr của mọi container `smart-erp` qua Docker socket, gắn label `service` cho từng luồng | nội bộ |

Cảnh báo: `DeadLetterNotEmpty` (message bị kẹt trong `erp.dead_letters`), `ServiceHigh5xx` (hơn 5% lỗi 5xx qua Kong trong 5 phút), `ScrapeTargetDown`.

Theo dõi một request: sao chép `X-Correlation-ID` từ header phản hồi, mở dashboard *Logs* và dán vào ô tìm kiếm. Access log của Kong luôn có ID này; Chat và Purchase cũng ghi kèm khi log lỗi.

Không thu thập CPU/RAM của container: cAdvisor không đọc được container trên Docker Desktop với containerd image store.

## Chống chịu lỗi

| Cơ chế | Vị trí |
|---|---|
| Transactional Outbox + publisher confirm | Catalog, Sales, E-Office (container relay), Purchase, Inventory (worker nền) |
| Hàng đợi retry (10 giây × 5 lần) rồi dead letter `erp.dlx` → `erp.dead_letters` | Consumer của Notification |
| Consumer idempotent (theo `event_id`) | Notification, Inventory |
| Circuit breaker: mở sau 30 giây khi có 5 lỗi liên tiếp (lỗi kết nối hoặc 5xx; 4xx không tính), một probe half-open | Sales → Inventory / Finance / Identity / Catalog; E-Office → Identity / Finance (Guzzle middleware dựa trên cache); Chat → Identity, Purchase → Catalog / Identity / Notification (Polly, `Microsoft.Extensions.Http.Resilience`) |
| Retry 2 lần chỉ khi lỗi kết nối | Các lời gọi an toàn khi lặp lại: đọc dữ liệu, và Inventory reserve / release / issue (idempotent theo chứng từ). Không bao giờ retry POST tới Finance, chuyển hàng trả hàng bán, hay gửi email |
| Giới hạn tốc độ, timeout | Kong |
| Health check + `restart: unless-stopped` | Mọi container |

`GET /api/v1/sales/health` báo trạng thái của từng circuit breaker của Sales (`CLOSED`, `OPEN`, `HALF_OPEN`).

Các giữ hàng bị bỏ lại do tạo hóa đơn thất bại (Sales timeout hoặc crash sau khi Inventory đã giữ hàng) sẽ được `sales:reconcile-reservations` giải phóng; lệnh này chạy mỗi 5 phút trong container `sales-scheduler`. Có thể chạy tay với `--dry-run` để chỉ liệt kê:

```powershell
docker compose exec sales-scheduler php artisan sales:reconcile-reservations --dry-run
```

Các relay outbox (Sales, E-Office, Catalog trong PHP; Purchase, Inventory trong .NET) không bao giờ làm mất sự kiện khi RabbitMQ bị down: chúng chờ lùi lại tối đa 60 giây và tiếp tục đúng thứ tự khi broker hoạt động lại.

Dead letter: sau khi sửa nguyên nhân, liệt kê và replay bằng:

```powershell
./scripts/powershell/replay-dead-letters.ps1            # chỉ liệt kê
./scripts/powershell/replay-dead-letters.ps1 -Replay    # publish lại vào exchange/routing key gốc
```

## Thay đổi database và rollback

Các container web không chạy migration khi khởi động. Trước mỗi bản phát hành có thay đổi schema, cần backup MySQL, chạy migration tương thích ngược, deploy ứng dụng, và chỉ xóa cột cũ ở bản phát hành sau. Lệnh migration của Laravel được chạy bởi `scripts/powershell/deploy.ps1`.

Thư mục init của MySQL chỉ chạy khi volume dữ liệu được tạo. Sửa script init không cập nhật volume đã tồn tại. Áp dụng quyền mới hoặc thay đổi schema qua migration có phiên bản hoặc thao tác DBA có kiểm soát; không xóa volume để buộc khởi tạo lại.

Để so sánh với gateway cũ mà không thay Kong:

```powershell
docker compose --profile legacy-gateway up -d gateway-legacy
# Gateway Nginx cũ được expose tại http://localhost:8100
```

Để rollback traffic của Kong, dừng service `gateway`, tạm đổi ánh xạ cổng của legacy từ `8100:80` thành `8000:80`, rồi chạy smoke test.

## Giới hạn đã biết

Service AI giữ lịch sử hội thoại và read model phân tích trong schema MySQL `erp_analytics_db`. Đồng bộ kho dữ liệu bị tắt mặc định trong Docker (`AI_WAREHOUSE_SYNC_ENABLED=false`) vì ETL đọc trực tiếp schema MySQL của các service khác, làm vượt ranh giới service. Chỉ đặt `AI_WAREHOUSE_SYNC_ENABLED=true` cho demo. `/health` báo cả `analytics_ready` và `warehouse_sync_enabled`. Cần thay ETL bằng đường nạp dữ liệu dựa trên event/API trước khi bật phân tích AI trên production.
