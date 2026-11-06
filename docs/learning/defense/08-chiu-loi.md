# Bài 08b — Chương 8: Tính chịu lỗi (Fault Tolerance)

> Rubric Chương 8 (1 điểm). File `08-devops-aws.md` là bài DevOps/AWS; bài này là chương 8 của rubric.
> Mục tiêu: nói được **lỗi nào có thể xảy ra, hệ thống phát hiện thế nào, phản ứng ra sao, chỗ nào CHƯA chịu được**, và chỉ ra con số đo thật.

## 1. Khung tư duy
- **Fault → Error → Failure**: lỗi gốc (container chết) → trạng thái sai (không ai xử lý) → người dùng thấy hỏng. Chịu lỗi = chặn chuỗi này ở giữa.
- Bốn việc: **phát hiện** (health check, timeout) → **che giấu/cô lập** (circuit breaker, loại máy hỏng khỏi load balancer) → **phục hồi** (restart, retry, failover, replay) → **không mất dữ liệu** (outbox, idempotent, Multi-AZ, backup).
- Ba mức lỗi: **tiến trình** (restart), **phụ thuộc** (RabbitMQ/inventory/DB chết), **máy/vùng** (EC2 hoặc AZ chết). Từ khi có kiến trúc HA trên AWS, hệ thống chịu được cả ba mức ở tầng **web/API và DB**; tầng **realtime và broker** vẫn là điểm lỗi đơn (mục 5).
- Chịu lỗi (không sai/mất dữ liệu) khác **sẵn sàng cao** (giảm thời gian ngừng nhờ dư thừa). Dự án mạnh cả hai ở tầng web và DB, chỉ mạnh vế đầu ở tầng broker/realtime.

## 2. Kiến trúc HA trên AWS (từ 07/11/2026)

```text
Người dùng → erp.transportbooking.xyz (CNAME → ALB)
 ALB (HTTPS 443, chứng chỉ ACM, health check /health mỗi 10s) ở 2 AZ
  ├ tg-erp-web  (mặc định)                       → EC2-A (AZ 1a) và EC2-B (AZ 1b), cổng 80
  ├ tg-erp-realtime (/api/v1/chat*, /api/v1/notifications*, stickiness) → chỉ EC2-A
  └ tg-erp-grafana  (/grafana*)                  → chỉ EC2-A, cổng 3000
 Mỗi EC2: nginx → Kong → các service ứng dụng (gọi nhau qua Docker DNS cục bộ trên cùng máy)
 RDS MySQL 8.4 Multi-AZ (primary + standby đồng bộ)     ← dùng chung cho cả hai máy
 EFS (mount ở cả hai AZ): tệp đính kèm của sales
 EC2-A còn gánh: RabbitMQ, Mongo, relay outbox (catalog, sales, eoffice), scheduler, chat, notification, giám sát, sao lưu
```

Vì sao thiết kế như vậy (câu hỏi phản biện hay gặp):
- **Một DB dùng chung (Multi-AZ), không phải mỗi máy một MySQL**: chốt chống bán âm `UPDATE ... WHERE quantity >= :qty` chỉ đúng khi mọi request đi qua cùng một bản dữ liệu. Hai DB độc lập thì hai đơn có thể cùng lấy hàng cuối ở hai nơi. Multi-AZ ghi **đồng bộ** sang bản chờ nên không mất dữ liệu đã commit; bản chờ không phục vụ truy vấn (khác read replica, sao chép bất đồng bộ).
- **Dùng ALB (L7) của AWS, không tự dựng Nginx/HAProxy**: tự dựng lại thành SPOF mới; ALB nhiều AZ, có health check, kết thúc HTTPS bằng ACM, định tuyến theo đường dẫn, hỗ trợ WebSocket.
- **Relay outbox và scheduler chỉ chạy ở một máy**: relay Laravel đọc 50 dòng rồi publish, không khóa dòng, chạy hai nơi sẽ phát trùng. Consumer idempotent chịu được trùng, nhưng cố ý tránh. Worker outbox .NET (purchase, inventory) nằm trong process dịch vụ nên tắt bằng `Outbox__RelayEnabled=false` ở máy B.
- **Chat và notification ghim vào máy A**: trạng thái kết nối (SignalR, Socket.IO) nằm trong bộ nhớ từng process; người ở máy A không nhận tin của người ở máy B. Muốn chạy hai bản phải thêm backplane (Redis).
- **Tệp đính kèm trên EFS**: disk `local` của Laravel nằm trong container, mỗi máy một bản sẽ không thấy file của nhau. Bind mount dùng `create_host_path: false` nên nếu EFS chưa mount, container **không khởi động** thay vì lặng lẽ ghi vào đĩa cục bộ.

## 3. Bảng cơ chế → vị trí code/cấu hình

| Lỗi | Cơ chế | Code/cấu hình |
|---|---|---|
| Tiến trình chết | `restart: unless-stopped` + healthcheck | `docker-compose.yml` (mọi service) |
| Máy EC2 chết | ALB loại máy khỏi `tg-erp-web` sau vài lần health check hỏng | ALB, target group; `/health` ở `frontend/nginx.conf` |
| DB chết | RDS Multi-AZ tự chuyển sang bản chờ (endpoint không đổi) | RDS, `MYSQL_HOST` trong `.env` |
| Service phụ thuộc chậm/chết (REST) | timeout 2–5s, retry ≤2 chỉ cho lệnh idempotent, **circuit breaker** (5 lỗi liên tiếp → mở 30s → half-open) | `sales-service/app/Infrastructure/Clients/InternalHttp.php`, `CircuitBreaker.php` |
| Kết quả không chắc (timeout/5xx) khi giữ hàng | nhả hàng của số chứng từ đó, trả `503 DEPENDENCY_UNAVAILABLE` | `StockReservationFailedException.php`, `InvoiceController` |
| Broker chết lúc phát event | **Transactional outbox**; relay backoff gấp đôi (tối đa 60s), không bỏ event | `RelayOutboxEvents.php`; .NET: `OutboxPublisherWorker.cs`, `ConfirmedOutboxPublisher.cs` |
| Consumer lỗi tạm thời | retry queue TTL 10s, quá số lần → DLX `erp.dlx` → `erp.dead_letters` | `infrastructure/rabbitmq/definitions.json`; `scripts/replay-dead-letters` |
| Nhận event trùng | `processed_events (event_id, consumer_name)` cùng transaction | bài 04 |
| Saga lệch (giữ hàng mà chứng từ không tạo được) | job đối soát chạy mỗi 5 phút `withoutOverlapping` | `sales-service/routes/console.php:12` |
| Ghi trùng/bán âm do đồng thời | UPDATE có điều kiện, UNIQUE index trọng tài | bài 06 |
| Mất dữ liệu | sao lưu mysqldump/mongodump → S3, phục hồi có `--yes` | bài 07 (phục hồi 8 schema vào RDS: 12 giây) |
| Hai máy cùng deploy | CD deploy cuốn chiếu: máy A trước (có smoke test), máy B sau; máy A lỗi thì dừng | `.github/workflows/deploy.yml` |
| Quá tải một nhánh | PHP-FPM `pm.max_children=8`, `max_requests=500` | `sales-service/docker/php-fpm.conf` |

## 4. Thí nghiệm đã chạy THẬT (có số liệu, ngày 06–07/11/2026)

### Trên dev (docker compose, một máy)
- **TN1 — tắt inventory-service**: `sales /health` báo `DEGRADED` (database UP, inventory_service DOWN) chứ không sập; Kong trả 503 sau ~2,6s; catalog, finance, eoffice vẫn 200 (lỗi cô lập theo service). Bật lại: inventory trả 200 sau ~1 phút.
- **TN2 — tắt RabbitMQ rồi tạo sản phẩm**: API vẫn **201**; sự kiện `ProductCreated` chuyển `FAILED` (relay log: *Connection reset by peer*, *Unable to connect to tcp://rabbitmq:5672*); bật lại, ~45s sau chuyển `PUBLISHED`. Không mất, không trùng.

### Trên AWS (HA: 2 EC2, ALB, RDS Multi-AZ); máy đo gọi site thật mỗi giây từ ngoài
| TN | Hành động | Quan sát (giờ UTC) | Kết luận |
|---|---|---|---|
| **A1** | Stop EC2-B | Lỗi đầu tiên 17:22:08; lỗi cuối 17:22:58. 9 request lỗi (3×502 + 6 timeout) trong ~50 mẫu; sau đó **200 liên tục**, thời gian phản hồi 0,36s (như trước) | Mất một máy không làm sập hệ thống. Cửa sổ lỗi **~50s** vì ALB cần đủ số lần health check hỏng mới loại máy |
| **A1b** | Start lại EC2-B | Bấm Start 17:25, healthy 17:26 (~1 phút). **0 request lỗi**. 10 container tự lên, EFS tự mount | Thêm máy vào lại hoàn toàn êm |
| **A2** | Stop EC2-A (máy giữ broker, chat, Grafana) | `web` (sales): lỗi 17:28:46 → 17:29:42 (**~56s**, 5 mẫu lỗi), sau đó 200 nhờ máy B. `chat` và `grafana`: 502 rồi **503 liên tục** đến 17:31:52. Khi A lên lại: 22 container tự chạy, user RabbitMQ còn nguyên | Web/API chịu được mất máy A; realtime thì **không** (đúng thiết kế: chỉ có một bản). Realtime ngừng khoảng 3 phút 8 giây, gồm thời gian thử website và khởi động lại máy |
| **A3** | RDS Reboot **with failover** | Sự kiện *failover started* 00:34 → *completed* 00:35 (giờ VN). PHP (`sales`): gián đoạn **~7s** (17:34:49 → 17:34:56), tự phục hồi. **.NET** (worker outbox của inventory): lần quét thành công cuối 17:34:42, kế tiếp 17:35:01, **khoảng trống 19s**; 1 lỗi `Connect Timeout expired` (17:34:58); tự phục hồi không cần restart. Sau failover Secondary Zone là `ap-southeast-1c` | DB không còn là SPOF. PHP hồi phục nhanh hơn vì mỗi request mở kết nối mới; .NET chậm hơn vì chờ hết thời gian kết nối (mặc định 15s) rồi retry với backoff |

Lưu ý khi trình bày số liệu:
- `inventory /health` chỉ trả thông tin tĩnh, **không chạm DB**, nên health check của nó không phản ánh lỗi DB; vì vậy ta đo .NET qua log của worker outbox. `sales /health` có kiểm tra DB và inventory. Đây là điểm yếu: health của .NET mới ở mức liveness, chưa phải readiness.
- Máy đo chỉ là một vị trí (máy cá nhân), mẫu mỗi ~1–2 giây; con số là xấp xỉ, không phải đo chính xác tới mili-giây.

### Chưa chạy (thừa nhận)
- Kill giữa chừng một transaction giữ hàng nhiều dòng; tắt Kong; tải cao song song (chưa có load test).
- Mất cả một AZ thật (ta chỉ tắt từng máy, không mô phỏng mất mạng AZ).
- Mở circuit breaker (cần gọi nhiều request qua `InternalHttp` khi inventory chết).

## 5. Điều hệ thống KHÔNG chịu được (phải thừa nhận)
1. **EC2-A là điểm lỗi đơn cho phần "một bản"**: RabbitMQ, Mongo, relay outbox, scheduler, chat, notification, Grafana, sao lưu. A chết ⇒ realtime ngừng, sự kiện nằm trong outbox chờ A lên lại (không mất, nhưng trễ); web/API vẫn chạy.
2. **Không có tự phục hồi máy**: không có Auto Scaling Group. EC2 chết thì phải tự Start hoặc dựng máy mới bằng `scripts/aws/bootstrap-ec2.sh` (~5–8 phút) rồi dán `.env` và deploy.
3. **Cửa sổ lỗi ~50s** khi mất máy vì health check (10s × ngưỡng); ALB **không** tự thử lại request đã gửi vào máy chết. Giảm bằng interval ngắn hơn (đổi lại dễ báo động giả) hoặc connection draining chủ động trước khi tắt máy.
4. **Một vùng (region), một ALB logic**: mất cả region là mất hệ thống; backup S3 cùng vùng.
5. **RabbitMQ và Mongo một node.** Mongo chạy replica set một thành viên chỉ để dùng transaction.
6. **Bí mật nằm trong `.env`** trên từng máy (chmod 600); hướng tốt hơn: Secrets Manager hoặc SSM Parameter Store.
7. **Rate limit của Kong theo từng máy** (`policy: local`) nên hạn mức tính riêng, tổng cộng gấp đôi.
8. **Health check mức liveness** (xem lưu ý mục 4); và `/health` của ALB chỉ đo tới Kong, không đo từng service phía sau (cố ý: một service lẻ chết không nên khiến cả máy bị loại khỏi ALB).
9. Container MySQL cũ vẫn còn trên máy A làm đường lui sau khi chuyển sang RDS (nên gỡ khi chắc chắn không cần).

## 6. Hướng nâng cấp (trả lời "nếu có thêm thời gian")
- Chuyển RabbitMQ sang Amazon MQ (cụm 3 node) và Mongo sang replica set 3 node hoặc DocumentDB; thêm Redis backplane để chạy chat/notification ở cả hai máy; khi đó máy A không còn đặc biệt.
- Auto Scaling Group với launch template (dùng lại script bootstrap làm user data) để máy chết tự được thay.
- Health check readiness thật cho từng service (kiểm tra DB, broker); bulkhead và rate limit dùng chung (Redis).
- Triển khai xanh-lục (blue/green) hoặc canary bằng trọng số target group; nhiều region nếu cần.

## 7. Câu hỏi hay gặp
1. **RabbitMQ sập thì đơn hàng có tạo được không?** Có (TN2): sự kiện nằm trong outbox, phát lại sau.
2. **Làm sao không mất event nếu process chết giữa chừng?** Event ghi trong DB cùng transaction; relay quét `PENDING/FAILED`. At-least-once + consumer idempotent ⇒ xử lý đúng một lần về hiệu quả.
3. **Circuit breaker để làm gì? Trạng thái?** Tránh dồn request vào service đang chết (cạn worker PHP-FPM). CLOSED → OPEN sau 5 lỗi liên tiếp, giữ 30s → HALF_OPEN cho 1 request thử. 4xx (như 409 hết hàng) không tính lỗi.
4. **Vì sao không retry mọi lời gọi?** Chỉ retry lệnh idempotent. Ghi công nợ/trả hàng không retry để tránh ghi trùng.
5. **Timeout mơ hồ thì làm gì?** Coi là không chắc: nhả hàng của số chứng từ, trả 503; job đối soát dọn phần lệch. 409 là chắc chắn nên không cần nhả.
6. **Vì sao không để mỗi EC2 một MySQL?** Dữ liệu không thống nhất, chốt chống bán âm mất tác dụng. Dùng một DB Multi-AZ (đồng bộ).
7. **Multi-AZ khác read replica thế nào?** Multi-AZ: bản chờ đồng bộ, chỉ để chuyển khi lỗi, không đọc được. Read replica: bất đồng bộ, đọc được, có độ trễ, dùng để tăng đọc.
8. **Máy chết thì người dùng có thấy lỗi không?** Có một cửa sổ ~50s với một phần request lỗi (TN A1), vì ALB cần vài lần health check mới loại máy; sau đó không lỗi. Muốn bằng 0 cần connection draining chủ động và retry ở client.
9. **Hệ thống có SPOF nào?** Có: EC2-A cho broker, realtime, relay (mục 5). Nêu hướng khắc phục (mục 6).
10. **RTO/RPO của bạn?** Mất một máy web: ~50s ngắt quãng, tự khôi phục; máy mới vào lại ~1 phút; DB failover ~1 phút (PHP ~7s gián đoạn, .NET ~19s); phục hồi 8 schema vào RDS 12s; RPO của DB bằng 0 với lỗi một AZ (đồng bộ), RPO của sao lưu bằng chu kỳ backup.
11. **Vì sao .NET phục hồi chậm hơn PHP khi RDS failover?** Mỗi request PHP mở kết nối mới nên đi theo DNS mới; .NET giữ connection pool và có thời gian chờ kết nối 15s, rồi backoff trước lần thử kế (log: khoảng trống 19s).
12. **Health check của ALB kiểm tra gì? Có đủ không?** Chỉ `/health` qua nginx → Kong (liveness). Cố ý không kiểm tra từng service, tránh loại cả hai máy cùng lúc khi một service lẻ lỗi; đổi lại không phát hiện service chết riêng lẻ. Hướng: readiness chi tiết + báo động riêng.
13. **Deploy có làm gián đoạn không?** CD deploy A trước rồi B, mỗi máy có smoke test; luôn còn một máy phục vụ (phần realtime thì tạm ngừng khi A deploy).
14. **Dead letter xử lý thế nào?** Queue `erp.dead_letters`, kiểm tra nguyên nhân rồi `scripts/replay-dead-letters`.

## 8. Kịch bản demo chịu lỗi (5 phút, dùng khi bảo vệ)
Chuẩn bị: bật 2 EC2 và RDS trước ~30 phút; mở `https://erp.transportbooking.xyz/health` và trang Targets của `tg-erp-web` (cả hai Healthy), Grafana (`/grafana/`).
1. Chạy máy đo (vòng lặp `curl` mỗi giây tới `/api/v1/sales/health`) trên terminal chiếu lên màn hình.
2. **Stop EC2-B** trong console. Chỉ cho giảng viên: vài lỗi 502/timeout trong ~50s rồi 200 trở lại; nói: *ALB loại máy hỏng, không có downtime toàn phần*. Mở Targets thấy B chuyển Unhealthy.
3. **Start EC2-B**: ~1 phút sau healthy, không lỗi.
4. (Tùy thời gian) **Reboot RDS with failover**: lỗi ngắn rồi tự phục hồi; nói: *Multi-AZ đồng bộ, endpoint không đổi*.
5. Kết luận thẳng thắn: web/API và DB chịu lỗi; broker và realtime còn một bản ở máy A (mục 5), hướng nâng cấp ở mục 6.
6. Sau demo: bật lại mọi thứ đã tắt.

Dự phòng nếu demo AWS trục trặc: video hoặc ảnh chụp các thí nghiệm A1–A3 ở mục 4, và demo cục bộ TN1, TN2 bằng `docker compose stop`.

## 9. Tự kiểm tra
- Vì sao outbox giải quyết "ghi DB xong nhưng publish thất bại"?
- Giải thích đường đi của một request từ trình duyệt đến Kong và tại sao mất máy B không sập hệ thống.
- Ba trạng thái circuit breaker và ngưỡng của bạn?
- Chỉ ra 3 SPOF còn lại và cách xử lý từng cái.
- Vì sao chat và notification không chạy được ở cả hai máy?
- Phân biệt timeout (không chắc) với 409 (chắc chắn) trong luồng giữ hàng.
- Vì sao mất 50 giây mới loại máy hỏng khỏi ALB, và cách rút ngắn?
