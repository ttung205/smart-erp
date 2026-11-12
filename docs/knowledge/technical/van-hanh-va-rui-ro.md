---
title: Vận hành, rủi ro và điểm lệch chuẩn
group: technical
audience: developer, admin
source: docs/architecture/ARCHITECTURE.md dòng 419–501 (§12–§15)
verified_by: null
verified_at: null
---

## 12. Topology triển khai và hành vi vận hành

- Một mạng Docker bridge (`erp-network`) cho phép tìm service bằng tên DNS của Compose.
- Các cổng công khai trên host mở trực tiếp mọi backend (`8001`–`8010`) song song với Kong `8000`; vì vậy gateway không phải là ranh giới mạng duy nhất trên host.
- MySQL và RabbitMQ có named volume và health check. Dữ liệu Notification/Chat/AI và file chat có named volume. Dữ liệu Laravel/Purchase/Inventory được lưu trong MySQL.
- Gateway chờ health check của cả 10 service. Một service AI/Chat tùy chọn bị suy giảm sẽ làm gateway mặc định không khởi động được, tạo ra sự phụ thuộc khi khởi động trên diện rộng.
- Các container Laravel chạy Nginx + PHP-FPM (`docker/start-web` của từng service). Không còn dùng `php artisan serve`.
- `AUTO_SEED=true` được đặt cho Identity, Catalog, Sales, E-Office và Finance; hành vi entrypoint quyết định việc khởi động production có thay đổi dữ liệu seed hay không. Bằng chứng: Compose và `docker-entrypoint.sh` của từng service.
- Compose ghim phiên bản Kong (`3.9.1`), MySQL (`8.0`), RabbitMQ (`3.13-management-alpine`), Node (`22-alpine`), Python (`3.12-slim`) và .NET (`10.0`); frontend/Nginx runtime và Mailpit dùng tag không cố định.

### Quan sát và chống chịu lỗi — IMPLEMENTED (2026-10-27)

| Khía cạnh | Triển khai | Bằng chứng |
|---|---|---|
| Log | Grafana Alloy thu log mọi container `smart-erp` vào Loki (label `service`, metadata có cấu trúc `correlation_id`); các service Laravel ghi log ra stderr | `infrastructure/monitoring/alloy/config.alloy`; Compose `LOG_CHANNEL` |
| Metric | Prometheus thu từ Kong (plugin `prometheus`) và RabbitMQ (`rabbitmq_prometheus`); chưa thu CPU/RAM container | `infrastructure/monitoring/prometheus/prometheus.yml` |
| Dashboard / cảnh báo | Grafana: ERP Overview, RabbitMQ, Logs; cảnh báo dead letter, tỷ lệ 5xx, target bị down | `infrastructure/monitoring/grafana`, `prometheus/alerts.yml` |
| Bảo vệ ở biên | Kong rate limiting theo IP, timeout/retry theo từng service, DNS TTL 10 giây | `infrastructure/kong/kong.yml`; Compose `KONG_DNS_VALID_TTL` |
| Circuit breaker | Guzzle middleware dựa trên cache của Laravel (Sales, E-Office); Polly standard handler (Chat, Purchase) | `sales-service/app/Infrastructure/Clients/CircuitBreaker.php`; `Chat.Infrastructure.DependencyInjection`; `Purchase.Api/Program.cs` |
| Dead letter | Cảnh báo + `scripts/powershell/replay-dead-letters.ps1` | `scripts/powershell/replay-dead-letters.ps1` |

Chưa triển khai: distributed tracing (OpenTelemetry), gửi cảnh báo log/metric (chưa có Alertmanager), circuit breaker cho Notification → Identity (consumer đó dựa vào hàng đợi retry của RabbitMQ).

## 13. Rủi ro, điểm nghẽn và chỗ không nhất quán

| Mức độ | Phát hiện | Tác động | Bằng chứng / trạng thái |
|---|---|---|---|
| Nghiêm trọng | Secret và application key mặc định được nhúng làm giá trị dự phòng trong Compose. | Một deployment không ghi đè biến môi trường sẽ có thông tin đăng nhập/JWT/internal key có thể đoán được. | environment trong `docker-compose.yml` — **rủi ro CONFIGURED** |
| Cao | Routing key `GoodsIssued` không có consumer nghiệp vụ và không khớp binding `inventory.stock.*`. | Cập nhật phía Sales/Notification có thể không bao giờ xảy ra; Inventory đánh dấu sự kiện đã publish mà không có đảm bảo confirm/mandatory. | `StocksController`; `definitions.json`; `OutboxPublisherWorker` — **lệch khớp ĐÃ TRIỂN KHAI** |
| Cao | Analytics queue có wildcard binding nhưng không có consumer; queue/consumer của Chat chỉ tồn tại trong roadmap. | Hàng đợi tăng không giới hạn và tạo kỳ vọng kiến trúc sai. | Rabbit definitions; manifest/source của AI và Chat; roadmap — **CONFIGURED_ONLY / DOC_ONLY** |
| Cao | ETL của AI đọc trực tiếp file/DB của service khác và không dùng được trong topology MySQL Compose mặc định. | Vi phạm ranh giới DB nghiêm ngặt trong thiết kế dev; dữ liệu phân tích production có thể cũ hoặc thiếu. | `warehouse.py`; cấu hình AI trong Compose — **tắt khi triển khai** |
| Cao | Mọi schema MySQL dùng chung một server, một volume, một miền lỗi root và một credential `erp_user` có quyền rộng. | Cách ly logic không đảm bảo tính sẵn sàng/mở rộng độc lập; nếu credential bị lộ hoặc grant sai thì có thể vượt qua các schema. | MySQL trong Compose; SQL khởi tạo |
| Cao | Mọi cổng backend đều được publish ra host. | Chính sách xóa header/kiểm soát của Kong có thể bị bỏ qua bằng cách truy cập trực tiếp qua host. | `docker-compose.yml` ports |
| Cao | Router email của Notification được mount ngoài `requireAuth`. | Có thể enqueue email hoặc truy cập log email mà không cần xác thực, nếu router không tự kiểm tra và không có lớp bảo vệ mạng. | `notification-service/src/app.js:19-21`; cần kiểm tra `email.routes.js` — **cần rà soát bảo mật** |
| Trung bình | Khởi động gateway phụ thuộc health của cả 10 service. | Một service tùy chọn lỗi có thể chặn điểm vào API duy nhất. | `docker-compose.yml — gateway.depends_on` |
| Trung bình | Cách triển khai outbox khác nhau: Laravel có confirm + mandatory, worker .NET đánh dấu đã publish sau `BasicPublish` mà không có confirm. | Đảm bảo giao sự kiện không nhất quán và yếu hơn ở Purchase/Inventory. | `RabbitMqPublisher.publish` của Laravel; worker relay .NET |
| Đã khắc phục | Container API PHP đã chuyển từ `artisan serve` sang Nginx + PHP-FPM | Không còn áp dụng | Dockerfile và `docker/start-web` của từng service Laravel |
| Trung bình | File đính kèm của Chat lưu trên volume cục bộ; MongoDB chạy replica set một node không xác thực (bind 127.0.0.1 khi phát triển, không publish khi production). | Chat không thể mở rộng sang nhiều node (thiếu lưu trữ dùng chung và backplane cho SignalR); bất kỳ ai trong `erp-network` đều truy cập được MongoDB. | Compose `mongo`, `chat_files` |
| Trung bình | Secret JWT đối xứng dùng chung cho mọi service. | Bất kỳ verifier nào bị lộ đều có thể tạo token; không có bằng chứng về xoay khóa hay thu hồi. | Compose `JWT_SECRET`; implementation verifier/issuer |
| Trung bình | Khai báo Rabbit bị trùng giữa định nghĩa tĩnh và mã khởi động consumer. | Lệch đối số hoặc binding có thể làm đóng channel hoặc thay đổi topology thực tế. | `definitions.json`; `RabbitMqConnection.TryConnect`; `consumer.setup` của Notification |
| Trung bình | Roadmap và báo cáo khẳng định các tính năng đã hoàn thành nhưng không có trong mã thực thi. | Tài liệu kiến trúc không đáng tin nếu không đối chiếu với code. | Giai đoạn 3 của roadmap Chat; endpoint trong roadmap AI |
| Thấp | Comment và số lượng phiên bản trong Compose đã cũ (ví dụ "8 schema", "PHP 8.3"; script khởi tạo tạo 9 schema, Docker dùng PHP 8.4). | Gây nhầm lẫn khi vận hành. | Comment trong Compose so với SQL khởi tạo và Dockerfile |

### Điểm nghẽn

1. Một instance Kong, một instance MySQL, một node RabbitMQ và một replica cho mọi service đều là điểm lỗi đơn lẻ.
2. Việc tạo đơn bán phụ thuộc đồng bộ vào Catalog (và đôi khi Identity); độ trễ và lỗi khi tạo chứng từ bị ràng buộc với các service này.
3. Việc xác định người nhận của Notification phụ thuộc đồng bộ vào Identity trong khi consume RabbitMQ; khi Identity lỗi, retry tăng và có thể làm nghẽn queue.
4. Trạng thái realtime của Chat/Notification nằm trong tiến trình; chưa cấu hình adapter Redis/backplane.
5. Polling của outbox cố định 1,5 giây với .NET, còn Laravel chạy vòng lặp liên tục; chưa có metric về thông lượng/backpressure.

Quan sát ngoài correlation ID và log container là **UNKNOWN**: không tìm thấy metric, bộ thu thập distributed tracing, log tập trung, cảnh báo hay cấu hình theo dõi độ sâu queue.

## 14. Chỉ mục bằng chứng theo kết luận chính

| Kết luận | Đường dẫn + symbol chính |
|---|---|
| JWT được cấp kèm vai trò/quyền | `services/identity-service/app/Http/Controllers/AuthController.php — login`; `app/Auth/JwtIssuer.php — issue` |
| Xác thực kép cho người dùng và service | `identity-service/app/Http/Middleware/AuthenticateUserOrService.php — handle`; tương đương ở Catalog |
| Sales gọi snapshot | `sales-service/app/Services/ReferenceSnapshot.php — customer/products/resolveUserId`; `Infrastructure/Clients/ServiceClient.php — getData/http` |
| Đơn bán không giữ hàng | `SalesOrderController.php — store` (không gọi Inventory, không outbox); việc giữ hàng chỉ nằm ở `InvoiceController.php — syncReserve/reserveStockForInvoice` |
| API giữ hàng theo lô của Inventory | `inventory-service/src/Inventory.Api/Controllers/StocksController.cs — BatchReserveStock` |
| Nhận hàng bất đồng bộ từ Purchase sang Inventory | `Purchase.Application/Services/GoodsReceiptService.cs`; `Inventory.Infrastructure/Messaging/RabbitMqConsumerWorker.cs — ProcessMessageAsync` |
| Relay outbox của Laravel | `app/Console/Commands/RelayOutboxEvents.php — handle/relayBatch` của từng service; Compose `catalog-relay`, `sales-relay`, `eoffice-relay` |
| Relay .NET chạy nền | `Purchase.Infrastructure/DependencyInjection.cs — AddInfrastructure`; `Inventory.Infrastructure/DependencyInjection.cs — AddInfrastructure` |
| Sự kiện/retry/idempotency của Notification | `notification-service/src/messaging/consumer.js — startConsumer`; `notifications/notification.service.js — handleEvent`; `notification.repository.js` |
| SignalR của Chat | `chat-service/src/Chat.Api/Program.cs — AddSignalR/MapHub`; `Realtime/ChatHub.cs`; frontend `chatStore.js — buildConnection` |
| Socket.IO của Notification | `notification-service/src/realtime/socket.js — createRealtime`; frontend `useNotifications.js — connect` |
| ETL của AI qua API export (Compose bật mặc định) | `docker-compose.yml — ai-service.WAREHOUSE_SYNC_ENABLED`; `ai-service/app/main.py — lifespan`; `warehouse.py — sync`; `export_client.py` |
| Route và xóa header của Kong | `infrastructure/kong/kong.yml — services/plugins` |
| Topology Rabbit | `infrastructure/rabbitmq/definitions.json` cùng các khai báo lúc chạy trong Inventory/Notification |

## 15. Các điểm UNKNOWN cần kiểm tra khi chạy thật

- Volume MySQL hiện tại đã chứa đủ mọi migration mới nhất hay chưa, và seeder đã chạy thành công chưa.
- Định nghĩa RabbitMQ đang chạy có khớp chính xác với file JSON trong repo sau các khai báo và unbind lúc chạy hay không.
- Độ sâu queue, dead letter, tồn đọng outbox, tỷ lệ trùng lặp và độ trễ giao sự kiện.
- Cả 10 health check có cùng đạt trạng thái khi chạy Compose hay không.
- Giá trị secret thật được ghi đè, TLS termination, quy tắc firewall, và các cổng service trực tiếp có truy cập được từ bên ngoài không.
- Chính sách backup/restore, lưu giữ dữ liệu, khôi phục thảm họa, giám sát và mở rộng theo chiều ngang trong production.
- Hành vi giao dịch đầu cuối khi có lỗi (timeout mạng sau khi remote đã commit, relay crash giữa lúc publish và cập nhật trạng thái).

Các mục này được đánh dấu **UNKNOWN** một cách có chủ đích, vì chỉ đọc source và cấu hình thì không thể chứng minh được trạng thái thực tế đang chạy.
