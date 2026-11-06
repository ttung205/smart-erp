# Bài 08b — Chương 8: Tính chịu lỗi (Fault Tolerance)

> Rubric Chương 8 (1 điểm). Lưu ý: file `08-devops-aws.md` là bài DevOps/AWS; bài này là chương 8 của rubric.
> Mục tiêu: nói được **lỗi nào có thể xảy ra, hệ thống phát hiện thế nào, phản ứng ra sao, và chỗ nào CHƯA chịu được**.

## 1. Khung tư duy
- **Fault → Error → Failure**: lỗi gốc (container chết) → trạng thái sai (không có ai xử lý) → người dùng thấy hỏng. Chịu lỗi = chặn chuỗi này ở giữa.
- Bốn việc: **phát hiện** (health check, timeout) → **che giấu/cô lập** (circuit breaker, degrade) → **phục hồi** (restart, retry, replay) → **không mất dữ liệu** (outbox, idempotent, backup).
- Ba mức: chịu được lỗi **tiến trình** (restart), lỗi **phụ thuộc** (RabbitMQ/inventory chết), lỗi **máy/vùng** (EC2 chết, mất AZ). Hệ thống hiện chịu tốt hai mức đầu, **chưa** chịu mức thứ ba (1 EC2).

## 2. Bảng cơ chế → vị trí code

| Lỗi | Cơ chế | Code/cấu hình |
|---|---|---|
| Tiến trình chết | `restart: unless-stopped` + healthcheck | `docker-compose.yml` (mọi service) |
| Service phụ thuộc chậm/chết (REST) | timeout 2–5s, retry ≤2 chỉ cho lệnh idempotent, **circuit breaker** (5 lỗi liên tiếp → mở 30s → half-open) | `sales-service/app/Infrastructure/Clients/InternalHttp.php`, `CircuitBreaker.php` |
| Kết quả không chắc (timeout/5xx) khi giữ hàng | nhả hàng của số chứng từ đó ngay, trả `503 DEPENDENCY_UNAVAILABLE` | `StockReservationFailedException.php`, `InvoiceController` |
| Broker chết lúc phát event | **Transactional outbox**: ghi event cùng transaction nghiệp vụ; relay thử lại với backoff gấp đôi (tối đa 60s), **không bao giờ bỏ event** | `RelayOutboxEvents.php:14-21,65-82,115` ; .NET: `ConfirmedOutboxPublisher.cs`, `OutboxPublisherWorker.cs` |
| Consumer lỗi tạm thời | retry queue có TTL 10s, quá số lần → DLX `erp.dlx` → `erp.dead_letters` | `infrastructure/rabbitmq/definitions.json` ; `scripts/replay-dead-letters` |
| Nhận event trùng | `processed_events (event_id, consumer_name)` cùng transaction | bài 04 |
| Saga treo/lệch (giữ hàng mà chứng từ không tạo được) | job đối soát `ReconcileStockReservations` chạy mỗi 5 phút `withoutOverlapping` | `sales-service/routes/console.php:12` |
| Ghi trùng/bán âm do đồng thời | UPDATE có điều kiện, UNIQUE index trọng tài | bài 06 |
| Mất dữ liệu | sao lưu mysqldump/mongodump → S3, restore có `--yes` | bài 07 (RTO đo: restore một schema 48s, drill đầy đủ 84s) |
| Mất DB một service | DB-per-service: lỗi `erp_sales_db` không kéo theo `erp_catalog_db` | bài 02 |
| Quá tải một nhánh | PHP-FPM `pm.max_children=8`, `max_requests=500` (tái chế worker rò rỉ bộ nhớ) | `sales-service/docker/php-fpm.conf` |

## 3. Thí nghiệm đã chạy THẬT trên dev (có số liệu)

### TN1 — Tắt inventory-service (`docker compose stop inventory-service`)
Quan sát:
- `GET /api/v1/sales/health` → `status: "DEGRADED"`, `checks: {database: UP, inventory_service: DOWN}`, `success:false`. **Sales không sập, chỉ tự báo suy giảm** (graceful degradation).
- `GET /api/v1/inventory/health` qua Kong → **503**, `"name resolution failed"`, mất ~2,6 giây. (Docker DNS không còn tên vì container đã dừng.)
- catalog, finance, eoffice vẫn **200** → lỗi bị **cô lập** theo service, không lan rộng (lợi ích của microservices).
- Bật lại: inventory trả 200 sau khoảng **1 phút** (khởi động .NET + healthcheck). Trong lúc đó vẫn 503. Đây là **thời gian phục hồi (RTO) của một service** — con số nên nhớ.

### TN2 — Tắt RabbitMQ (`docker compose stop rabbitmq`), rồi tạo sản phẩm
Quan sát:
- `POST /api/v1/catalog/products` vẫn **201** dù broker chết (nghiệp vụ không phụ thuộc broker nhờ outbox).
- Bảng `outbox_events`: event `ProductCreated` chuyển `FAILED` (relay log: *thất bại lần 1: Connection reset by peer*, *lần 2: Unable to connect to tcp://rabbitmq:5672*).
- Bật RabbitMQ lại, chờ ~45s: event chuyển `PUBLISHED`. **Không mất, không trùng** (consumer còn có `processed_events` phòng khi phát lại).
- Đây chính là bằng chứng cho khẳng định "mất broker không mất event": dữ liệu nghiệp vụ và ý định phát event cùng nằm trong một transaction DB.
- (Đã dọn: hai sản phẩm thử CHAOS-1/2 bị xóa mềm.)

### Chưa chạy (nói thẳng, cần làm tiếp nếu còn thời gian)
- Kill giữa chừng một transaction giữ hàng nhiều dòng (kiểm tra rollback toàn lô).
- Tắt MySQL; tắt Kong; kill container ngẫu nhiên khi đang tải.
- Mở circuit breaker (cần gọi nhiều request đi qua `InternalHttp` khi inventory chết — `/health` của sales đo trực tiếp nên chưa làm mạch chuyển OPEN trong thí nghiệm trên; hiện `circuit_breakers` vẫn báo CLOSED).

## 4. Điều hệ thống KHÔNG chịu được (phải thừa nhận)
1. **Một EC2, một instance mỗi service** ⇒ EC2 chết / mất AZ = ngừng toàn bộ. Không có failover thật. Thời gian phục hồi = khởi động lại hoặc dựng máy mới + restore từ S3 (RTO phút đến chục phút, RPO = tuổi bản sao lưu gần nhất).
2. **Không scale ngang**: mọi service có `container_name` nên `--scale` không dùng được; Kong chưa cân bằng tải/health check chủ động nhiều bản sao.
3. **MySQL, RabbitMQ, Mongo mỗi cái 1 node** (Mongo replica set 1 thành viên chỉ để dùng transaction). Chết là mất dịch vụ của phần phụ thuộc cho đến khi restart.
4. **Khóa `flock` chỉ có tác dụng trên một máy**; khi nhiều máy cần khóa phân tán (Redis/etcd) hoặc dựa vào DB, như bài 06.
5. Retry của tin nhắn có số lần hữu hạn → tin xấu vào dead letter, cần người replay.
6. Backup trên cùng vùng AWS; chưa có sao chép chéo vùng.

## 5. Hướng nâng cấp (trả lời "nếu có thêm thời gian")
- Bỏ `container_name`, chạy 2 bản sao stateless (`--scale`), Kong upstream với active health check ⇒ kill một bản vẫn phục vụ. Hoặc ECS/EKS.
- RDS Multi-AZ cho MySQL, Amazon MQ (hoặc RabbitMQ cluster quorum queue), MongoDB replica set 3 node.
- Auto Scaling Group + ALB, AMI dựng sẵn, chạy 2 AZ.
- Bulkhead (thread pool riêng mỗi dependency), rate limit ở Kong, chaos test định kỳ.

## 6. Câu hỏi hay gặp
1. **RabbitMQ sập thì đơn hàng có tạo được không?** Có (TN2). Event nằm trong outbox, phát lại sau.
2. **Làm sao không mất event nếu process chết giữa chừng?** Event đã ghi trong DB cùng transaction; relay quét `PENDING/FAILED`. Mô hình là *at-least-once* + consumer idempotent ⇒ hiệu quả *exactly-once xử lý*.
3. **Circuit breaker để làm gì? Trạng thái?** Tránh dồn request vào service đang chết (cạn worker PHP-FPM, thác đổ). CLOSED → OPEN sau 5 lỗi liên tiếp, giữ 30s → HALF_OPEN cho 1 request thử. 4xx (ví dụ 409 hết hàng) không tính là lỗi.
4. **Vì sao không retry mọi lời gọi?** Chỉ retry lệnh idempotent (GET, giữ/nhả hàng theo số chứng từ). Ghi công nợ/trả hàng không retry để tránh ghi trùng.
5. **Kết quả timeout mơ hồ thì làm gì?** Coi là không chắc: nhả hàng của số chứng từ ngay, trả 503; job đối soát dọn nốt phần lệch. Còn 409 là chắc chắn nên không cần nhả.
6. **Hệ thống có SPOF nào?** Có: EC2, MySQL, RabbitMQ, Kong đều 1 bản (§4). Nêu hướng khắc phục (§5).
7. **RTO/RPO của bạn?** RTO một service ≈ 1 phút (TN1); restore một schema 48s, drill đầy đủ 84s; RPO = chu kỳ sao lưu.
8. **Khác nhau giữa chịu lỗi và sẵn sàng cao?** Chịu lỗi: không sai/mất dữ liệu khi có lỗi; sẵn sàng cao: giảm thời gian ngừng nhờ dư thừa (nhiều bản sao). Dự án mạnh ở vế đầu, yếu ở vế sau.
9. **Dead letter xử lý thế nào?** Queue `erp.dead_letters`, kiểm tra nguyên nhân rồi `scripts/replay-dead-letters`.
10. **Làm sao biết service đang lỗi?** `/health` có kiểm tra phụ thuộc, Prometheus/Grafana, log Loki theo correlation id.

## 7. Kịch bản demo chịu lỗi (3 phút, dùng khi bảo vệ)
1. Mở Grafana/`/health`: tất cả UP.
2. `docker compose stop inventory-service` → `curl /api/v1/sales/health` thấy `DEGRADED`, catalog vẫn 200. Nói: *lỗi được cô lập*.
3. `docker compose start inventory-service`; nói: *tự phục hồi, ~1 phút*.
4. `docker compose stop rabbitmq` → tạo 1 sản phẩm vẫn 201 → xem `outbox_events` có `FAILED` → `docker compose start rabbitmq` → thành `PUBLISHED`. Nói: *outbox*.
5. Kết luận thẳng thắn: SPOF ở mức máy; hướng nâng cấp §5.

Gợi ý thao tác trên AWS: dùng Session Manager vào EC2, chạy đúng các lệnh `docker compose` ở thư mục dự án; nhớ bật lại dịch vụ sau demo.

## 8. Tự kiểm tra
- Vì sao outbox giải quyết "ghi DB xong nhưng publish thất bại"?
- Ba trạng thái circuit breaker và ngưỡng của bạn?
- Chỉ ra 3 SPOF và cách xử lý từng cái.
- Phân biệt timeout (không chắc) với 409 (chắc chắn) trong luồng giữ hàng.
