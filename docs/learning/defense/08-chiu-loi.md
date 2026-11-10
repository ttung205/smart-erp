# Bài 08b — Chương 8: Tính chịu lỗi (Fault Tolerance)

> Rubric Chương 8 (1 điểm). File `08-devops-aws.md` là bài DevOps/AWS; bài này là chương 8 của rubric.
> Mục tiêu: nói được **lỗi nào có thể xảy ra, hệ thống phát hiện thế nào, phản ứng ra sao, chỗ nào CHƯA chịu được**, và chỉ ra con số đo thật.

## 1. Khung tư duy
- **Fault → Error → Failure**: lỗi gốc (container chết) → trạng thái sai (không ai xử lý) → người dùng thấy hỏng. Chịu lỗi = chặn chuỗi này ở giữa.
- Bốn việc: **phát hiện** (health check, timeout) → **che giấu/cô lập** (circuit breaker, loại máy hỏng khỏi load balancer) → **phục hồi** (restart, retry, failover, replay) → **không mất dữ liệu** (outbox, idempotent, Multi-AZ, backup).
- Ba mức lỗi: **tiến trình** (restart), **phụ thuộc** (RabbitMQ/inventory/DB chết), **máy/vùng** (EC2 hoặc AZ chết). Từ khi có kiến trúc HA trên AWS, hệ thống chịu được cả ba mức ở tầng **web/API và DB**; tầng **realtime và broker** vẫn là điểm lỗi đơn (mục 5).
- Chịu lỗi (không sai/mất dữ liệu) khác **sẵn sàng cao** (giảm thời gian ngừng nhờ dư thừa). Dự án mạnh cả hai ở tầng web và DB, chỉ mạnh vế đầu ở tầng broker/realtime.

## 2. Kiến trúc HA trên AWS (cập nhật 09/11/2026)

```text
Người dùng → erp.transportbooking.xyz (CNAME → ALB)
 ALB (HTTPS 443, chứng chỉ ACM, health check /health mỗi 10s) ở 2 AZ
  ├ tg-erp-web  (mặc định)   → EC2-A (đứng riêng) + Auto Scaling Group erp-app-asg (Min 1, Desired 2, Max 3, AZ 1a và 1b), cổng 80
  ├ tg-erp-realtime (/api/v1/chat*, /api/v1/notifications*, stickiness) → chỉ EC2-A
  └ tg-erp-grafana  (/grafana*)  → chỉ EC2-A, cổng 3000
 Mỗi máy ứng dụng: nginx → Kong → các service (gọi nhau qua Docker DNS cục bộ trên cùng máy)
 Máy ASG tự dựng từ golden AMI: user data lấy .env và thẻ image từ SSM Parameter Store rồi deploy
 RDS MySQL 8.4 Multi-AZ (primary + standby đồng bộ) nằm trong subnet RIÊNG   ← dùng chung cho mọi máy
 EFS (mount target ở subnet riêng 1a và 1b): tệp đính kèm của sales, purchase, eoffice và tệp chat
 EC2-A còn gánh: RabbitMQ, Mongo, relay outbox (catalog, sales, eoffice), scheduler, chat, notification, giám sát, sao lưu
```

Vì sao thiết kế như vậy (câu hỏi phản biện hay gặp):
- **Một DB dùng chung (Multi-AZ), không phải mỗi máy một MySQL**: chốt chống bán âm `UPDATE ... WHERE quantity >= :qty` chỉ đúng khi mọi request đi qua cùng một bản dữ liệu. Hai DB độc lập thì hai đơn có thể cùng lấy hàng cuối ở hai nơi. Multi-AZ ghi **đồng bộ** sang bản chờ nên không mất dữ liệu đã commit; bản chờ không phục vụ truy vấn (khác read replica, sao chép bất đồng bộ).
- **Dùng ALB (L7) của AWS, không tự dựng Nginx/HAProxy**: tự dựng lại thành SPOF mới; ALB nhiều AZ, có health check, kết thúc HTTPS bằng ACM, định tuyến theo đường dẫn, hỗ trợ WebSocket.
- **Relay outbox và scheduler chỉ chạy ở một máy**: relay Laravel đọc 50 dòng rồi publish, không khóa dòng, chạy hai nơi sẽ phát trùng. Consumer idempotent chịu được trùng, nhưng cố ý tránh. Worker outbox .NET (purchase, inventory) nằm trong process dịch vụ nên tắt bằng `Outbox__RelayEnabled=false` ở máy ASG.
- **Chat và notification ghim vào máy A**: trạng thái kết nối (SignalR, Socket.IO) nằm trong bộ nhớ từng process; người ở máy A không nhận tin của người ở máy B. Muốn chạy hai bản phải thêm backplane (Redis).
- **Tệp đính kèm trên EFS**: mọi service có upload file (sales, purchase, eoffice, và chat ở máy A) ghi vào EFS, vì file nằm trong container thì mỗi máy một bản, không thấy file của nhau và mất khi container bị tạo lại. Mỗi service một thư mục con (`/mnt/efs/<service>`). Bind mount dùng `create_host_path: false` nên nếu EFS chưa mount, container **không khởi động** thay vì lặng lẽ ghi vào đĩa cục bộ. Service nào thêm upload file sau này cũng phải thêm mount tương tự trong `docker-compose.ha.yml`.
- **Auto Scaling Group chỉ cho tầng không giữ trạng thái**: máy A có RabbitMQ, Mongo, relay, chat (có trạng thái, chỉ được chạy một bản) nên **không** vào ASG. ASG thay vai trò máy B cũ: tự thay máy hỏng, tự thêm máy khi số request mỗi máy vượt ngưỡng.
- **Máy ASG tự cấu hình khi sinh ra**: golden AMI (Docker, AWS CLI, image kéo sẵn, **không có `.env`**); user data (`scripts/aws/asg-boot.sh`) lấy `.env` từ SSM Parameter Store (SecureString) và thẻ image từ tham số `image-tag` bằng IAM role, mount EFS, rồi chạy `deploy-aws.sh`. Bí mật không nằm trong AMI hay Git.
- **RDS và EFS trong subnet riêng**: subnet riêng là subnet có bảng định tuyến **không có đường tới Internet Gateway**; thêm một lớp "không có đường đi" trên lớp security group.

## 3. Bảng cơ chế → vị trí code/cấu hình

| Lỗi | Cơ chế | Code/cấu hình |
|---|---|---|
| Tiến trình chết | `restart: unless-stopped` + healthcheck | `docker-compose.yml` (mọi service) |
| Máy EC2 chết | ALB loại máy khỏi `tg-erp-web` sau vài lần health check hỏng | ALB, target group; `/health` ở `frontend/nginx.conf` |
| Máy ứng dụng chết hoặc quá tải | **Auto Scaling Group**: health check ELB và EC2 → tự thay máy; target tracking theo request mỗi máy → tự thêm hoặc bớt máy | ASG `erp-app-asg`, launch template, `scripts/aws/asg-boot.sh`, `scripts/aws/bake-ami.sh` |
| DB chết | RDS Multi-AZ tự chuyển sang bản chờ (endpoint không đổi) | RDS, `MYSQL_HOST` trong `.env` |
| Service phụ thuộc chậm/chết (REST) | timeout 2–5s, retry ≤2 chỉ cho lệnh idempotent, **circuit breaker** (5 lỗi liên tiếp → mở 30s → half-open) | `sales-service/app/Infrastructure/Clients/InternalHttp.php`, `CircuitBreaker.php` |
| Kết quả không chắc (timeout/5xx) khi giữ hàng | nhả hàng của số chứng từ đó, trả `503 DEPENDENCY_UNAVAILABLE` | `StockReservationFailedException.php`, `InvoiceController` |
| Broker chết lúc phát event | **Transactional outbox**; relay backoff gấp đôi (tối đa 60s), không bỏ event | `RelayOutboxEvents.php`; .NET: `OutboxPublisherWorker.cs`, `ConfirmedOutboxPublisher.cs` |
| Consumer lỗi tạm thời | retry queue TTL 10s, quá số lần → DLX `erp.dlx` → `erp.dead_letters` | `infrastructure/rabbitmq/definitions.json`; `scripts/replay-dead-letters` |
| Nhận event trùng | `processed_events (event_id, consumer_name)` cùng transaction | bài 04 |
| Saga lệch (giữ hàng mà chứng từ không tạo được) | job đối soát chạy mỗi 5 phút `withoutOverlapping` | `sales-service/routes/console.php:12` |
| Ghi trùng/bán âm do đồng thời | UPDATE có điều kiện, UNIQUE index trọng tài | bài 06 |
| Mất dữ liệu | sao lưu mysqldump/mongodump → S3, phục hồi có `--yes` | bài 07 (phục hồi 8 schema vào RDS: 12 giây) |
| Nhiều máy cùng deploy | CD deploy cuốn chiếu: máy A trước, rồi từng máy InService của ASG (tìm lúc chạy), có smoke test; máy nào lỗi thì dừng; chỉ khi tất cả xong mới ghi `image-tag` vào SSM | `.github/workflows/deploy.yml` |
| Quá tải một nhánh | PHP-FPM `pm.max_children=8`, `max_requests=500` | `sales-service/docker/php-fpm.conf` |

## 4. Thí nghiệm đã chạy THẬT (có số liệu, ngày 06–09/11/2026)

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
| **A4** | CD deploy cuốn chiếu (merge vào main, GitHub Actions → SSM, máy A rồi máy B, image `sha-2cea7c0`) | Deploy máy A (17:54): `chat` lỗi 502 liên tục ~32s (17:54:07–17:54:39), `web` lỗi khoảng một nửa số mẫu (7 lỗi trong ~16 mẫu) vì ALB chưa kịp loại máy đang tạo lại container. Deploy máy B (18:00:50–18:01:10): ~20s lỗi 502 khoảng một nửa số mẫu `web` (6 mẫu lỗi), `chat` 200 vì chạy ở máy A. Cả hai khối kết thúc bằng `SMOKE TEST ĐẠT` | Deploy cuốn chiếu bảo đảm luôn còn máy phục vụ nhưng **không phải zero-downtime**: mỗi máy có cửa sổ ~20–30s lỗi một phần. Muốn bằng 0 cần rút máy khỏi ALB trước khi deploy (deregister, chờ draining) rồi đăng ký lại |
| **A5** | Chuyển RDS vào subnet riêng: tắt Multi-AZ → sửa subnet group mặc định (thêm subnet riêng, bỏ subnet công khai) → bật Multi-AZ → *Reboot with failover* → tắt Multi-AZ → gỡ subnet công khai cuối → bật lại Multi-AZ | Lần failover: `sales` ngừng **~8s** (15:17:58 → 15:18:06 UTC), .NET khoảng trống **~20s** (15:17:54 → 15:18:14). Ba mẫu timeout lẻ lúc tắt Multi-AZ (15:30). Endpoint không đổi | DB nằm hoàn toàn trong subnet riêng; ngừng ngắn ở đúng một thao tác (failover). RDS **không cho đổi sang subnet group khác** trong cùng VPC nên phải sửa chính nhóm đang dùng |
| **A6** | Terminate một máy trong ASG | Lỗi đầu tiên 17:55:30, hết lỗi 17:56:02 (**~30s**, một phần mẫu). ASG phát hiện qua health check EC2 lúc 17:56:22 (~55s sau) và tạo máy thay 17:56:23–29 (dưới 10s sau khi phát hiện). Máy mới healthy sau khoảng 2,5 phút kể từ lúc tạo | Máy hỏng **tự được thay** không cần ai làm gì. Người dùng chỉ thấy lỗi lẻ ~30s vì còn máy A và một máy ASG phục vụ |
| **A7** | Tạo tải (nhiều `curl` song song vào `/health` từ CloudShell) | Số request mỗi máy mỗi phút ~820 (ngưỡng 300). `AlarmHigh` chuyển ALARM **18:18:11**; ASG tăng 2 → 3 lúc **18:18:23** (13s sau cảnh báo). Từ lúc tải bắt đầu đến lúc cảnh báo khoảng 6 phút | Tự mở rộng hoạt động; độ trễ chủ yếu do cần 3 điểm đo liên tiếp (3 phút) cộng độ trễ của CloudWatch. Lần thử đầu **không** tăng vì chính sách chưa được tạo (bị bỏ sót lúc tạo ASG) |
| **A8** | Dừng tải, chờ tự thu gọn | Điểm đo dưới ngưỡng từ 18:23. `AlarmLow` (ngưỡng < 210 khi có 3 máy, 15 điểm đo liên tiếp) ALARM **18:41:52** (~18 phút sau). ASG chọn terminate 18:42:00 (3 → 2) và 18:42:59 (2 → 1); mỗi máy hoàn tất sau ~5 phút (chờ ALB xả kết nối cũ) | Tự thu về mức tối thiểu 1 máy ASG. Giảm chậm có chủ ý (15 điểm đo) để tránh dao động. Muốn luôn có hai máy ASG khi rảnh thì đặt Min = 2 |

Bài học vận hành từ A4: lần chạy CD đầu **bỏ sót máy B** vì GitHub chụp giá trị biến (`vars.EC2_INSTANCE_ID_B`) lúc **tạo run**; biến tạo sau đó không có hiệu lực cho run đang chờ duyệt. Chạy lại run (re-run) đã nhận biến mới và deploy được cả hai máy. Nên kiểm tra dòng `INSTANCE_IDS:` trong log trước khi kết luận deploy xong.

Bài học vận hành từ A5 đến A8: (1) tạo chính sách scaling là một bước **riêng** khi tạo ASG, rất dễ bỏ sót (không có cảnh báo `TargetTracking-...` trong CloudWatch nghĩa là chưa có chính sách); (2) đổi mount target của EFS có ngừng dịch vụ có kiểm soát: container `sales` dừng cho tới khi mount lại (nhờ `create_host_path: false` nó không ghi nhầm vào đĩa cục bộ); tên DNS chung của EFS cập nhật chậm sau khi tạo lại mount target, còn tên riêng của AZ dùng được ngay; (3) ngưỡng giảm của chính sách tự đổi theo số máy (270 khi 2 máy, 210 khi 3 máy).

Lưu ý khi trình bày số liệu:
- `inventory /health` chỉ trả thông tin tĩnh, **không chạm DB**, nên health check của nó không phản ánh lỗi DB; vì vậy ta đo .NET qua log của worker outbox. `sales /health` có kiểm tra DB và inventory. Đây là điểm yếu: health của .NET mới ở mức liveness, chưa phải readiness.
- Máy đo chỉ là một vị trí (máy cá nhân), mẫu mỗi ~1–2 giây; con số là xấp xỉ, không phải đo chính xác tới mili-giây.

### Chưa chạy (thừa nhận)
- Kill giữa chừng một transaction giữ hàng nhiều dòng; tắt Kong; tải cao song song (chưa có load test).
- Mất cả một AZ thật (ta chỉ tắt từng máy, không mô phỏng mất mạng AZ); kịch bản suy luận ở mục 5.
- Mở circuit breaker (cần gọi nhiều request qua `InternalHttp` khi inventory chết).

## 5. Điều hệ thống KHÔNG chịu được (phải thừa nhận)
1. **EC2-A là điểm lỗi đơn cho phần "một bản"**: RabbitMQ, Mongo, relay outbox, scheduler, chat, notification, Grafana, sao lưu. A chết ⇒ realtime ngừng, sự kiện nằm trong outbox chờ A lên lại (không mất, nhưng trễ); web/API vẫn chạy.
2. **Máy ứng dụng tự phục hồi nhờ ASG (A6), nhưng máy A thì không**: A đứng riêng nên chết phải tự Start; ASG cũng chỉ thay máy khi health check EC2 hoặc ELB báo hỏng, và máy mới mất khoảng 2,5 phút mới nhận traffic.
3. **Cửa sổ lỗi ~50s** khi mất máy vì health check (10s × ngưỡng); ALB **không** tự thử lại request đã gửi vào máy chết. Giảm bằng interval ngắn hơn (đổi lại dễ báo động giả) hoặc connection draining chủ động trước khi tắt máy.
4. **Một vùng (region), một ALB logic**: mất cả region là mất hệ thống; bucket sao lưu S3 `smart-erp-backup` cùng vùng `ap-southeast-1`, chưa bật sao chép chéo vùng (là hướng nâng cấp).
5. **RabbitMQ và Mongo một node.** Mongo chạy replica set một thành viên chỉ để dùng transaction.
6. **Bí mật nằm trong SSM Parameter Store và `.env` trên máy A** (chmod 600); máy ASG không giữ `.env` trong AMI nhưng giữ bản sao trên đĩa sau khi khởi động. Hướng tốt hơn: Secrets Manager có xoay mật khẩu.
7. **Rate limit của Kong theo từng máy** (`policy: local`) nên hạn mức tính riêng từng máy; tổng hạn mức tăng theo số máy ASG.
8. **Health check mức liveness** (xem lưu ý mục 4); và `/health` của ALB chỉ đo tới Kong, không đo từng service phía sau (cố ý: một service lẻ chết không nên khiến cả máy bị loại khỏi ALB).
9. **Tệp đính kèm của sales, purchase, eoffice (EFS `/mnt/efs/{sales,purchase,eoffice}`) không nằm trong bản sao lưu.** `backup.sh` chỉ gom MySQL, Mongo và tệp chat (`/mnt/efs/chat`, có trong bản sao lưu mỗi giờ). EFS chịu được mất một AZ nhưng không chịu được xoá nhầm.
10. Container MySQL cũ vẫn còn trên máy A làm đường lui sau khi chuyển sang RDS (nên gỡ khi chắc chắn không cần).

### Kịch bản mất cả AZ 1a (máy A nằm ở 1a): suy luận từ thiết kế, **chưa thử thật**
- **Tiếp tục chạy:** ALB loại các target ở 1a; máy ASG ở 1b (10 container: 8 service + Kong + frontend) vẫn phục vụ web/API. RDS Multi-AZ chuyển sang bản chờ (~1 phút), EFS còn mount target ở 1b. ASG thêm máy ở 1b để bù.
- **Ngừng:** chat, notification (chỉ chạy ở A), RabbitMQ, Mongo, Grafana/Loki, `sales-scheduler` (job đối soát giữ hàng chạy 5 phút một lần), sao lưu mỗi giờ.
- **Không mất dữ liệu nghiệp vụ:** service ở 1b vẫn ghi dữ liệu và dòng `outbox_events` trong cùng transaction vào RDS. Relay chỉ chạy ở A nên các dòng `PENDING` nằm chờ trong RDS; khi A lên lại, relay phát, notification tạo thông báo (trễ, không mất; `processed_events` chặn xử lý trùng).
- **Dữ liệu nằm trên A:** hộp thư và tin nhắn (Mongo), hàng đợi RabbitMQ. A hỏng vĩnh viễn thì phục hồi từ bản sao lưu S3 (RPO tối đa 1 giờ) hoặc EBS còn nguyên nếu chỉ mất AZ tạm thời. Queue RabbitMQ không cần phục hồi vì outbox còn bản gốc. Tệp chat nằm trên EFS nên không mất theo máy A.
- **Cách khôi phục A:** Start lại khi AZ về, hoặc dựng máy mới ở 1b bằng `ec2-bootstrap.sh` rồi phục hồi từ S3 (làm tay, chưa tự động).

## 6. Hướng nâng cấp (trả lời "nếu có thêm thời gian")
- Sao lưu tệp EFS (AWS Backup) và bật sao chép chéo vùng cho bucket S3.
- Chuyển RabbitMQ sang Amazon MQ (cụm 3 node) và Mongo sang replica set 3 node hoặc DocumentDB; thêm Redis backplane để chạy chat/notification ở cả hai máy; khi đó máy A không còn đặc biệt.
- Đưa cả máy A vào ASG sau khi tách phần có trạng thái (Amazon MQ, Redis, Mongo cụm); thêm CloudFront và WAF phía trước ALB; đặt EC2 vào subnet riêng kèm NAT hoặc VPC endpoint.
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
15. **Auto Scaling Group làm gì, vì sao máy A không vào?** ASG giữ số máy đúng mong muốn (thay máy hỏng) và thêm bớt máy theo tải. Máy A có RabbitMQ, Mongo, relay, chat (trạng thái trong bộ nhớ hoặc đĩa, chỉ được chạy một bản) nên không thể nhân bản hay tự thay; ASG chỉ hợp với tầng không giữ trạng thái.
16. **Máy mới của ASG tự cấu hình thế nào?** Từ golden AMI (Docker, AWS CLI, image kéo sẵn, không có `.env`); user data lấy `.env` và thẻ image từ SSM Parameter Store bằng IAM role, mount EFS rồi `deploy-aws.sh`. Đo được: user data chạy khoảng 89 giây, cộng khởi động hệ điều hành và ALB kiểm tra thành khoảng 2,5 phút.
17. **Vì sao ASG tăng nhanh nhưng giảm chậm?** Tăng cần 3 điểm đo liên tiếp trên ngưỡng (khoảng 3 phút); giảm cần 15 điểm đo liên tiếp dưới ngưỡng (khoảng 15 phút). Đo được: tăng sau ~6 phút kể từ lúc tải bắt đầu, giảm sau ~18 phút kể từ lúc tải dừng. Thiết kế bất đối xứng để tránh dao động và tránh cắt máy đúng lúc tải quay lại.
18. **Vì sao để RDS và EFS trong subnet riêng?** Subnet riêng không có đường tới Internet Gateway: tài nguyên bên trong nói chuyện được trong VPC nhưng không ai từ Internet vào được, kể cả khi lỡ cấu hình sai security group. Đây là lớp phòng thủ thứ hai (đường đi), bên cạnh lớp thứ nhất (security group cho phép hay không).
19. **Làm sao chuyển RDS vào subnet riêng khi RDS không cho đổi subnet group?** Sửa chính subnet group đang dùng: thêm subnet riêng, giữ lại subnet công khai nơi primary đang đứng, bật Multi-AZ để bản chờ nằm ở subnet riêng, failover để primary dời sang đó, tắt Multi-AZ để xóa bản cũ ở subnet công khai, gỡ subnet công khai khỏi nhóm, rồi bật lại Multi-AZ. Ngừng chỉ ở lúc failover (~8s với PHP, ~20s với .NET).
20. **Nếu mất cả AZ 1a (nơi máy A đứng) thì hệ thống còn gì?** Web/API còn nhờ máy ASG ở 1b và RDS tự chuyển; chat, notification, scheduler ngừng vì chỉ chạy ở A. Dữ liệu nghiệp vụ không mất: outbox ghi trong RDS, dòng `PENDING` chờ đến khi A lên lại thì relay phát, notification tạo thông báo trễ (idempotent qua `processed_events`). Hộp thư, tin nhắn, tệp chat nằm trên A, khôi phục từ bản sao lưu mỗi giờ. Đây là suy luận từ thiết kế, chưa thử thật (mục 4 phần "Chưa chạy").
21. **Nếu ASG đang scale-in mà chạy CD thì sao?** CD hỏi ASG máy nào đang InService lúc chạy, deploy từng máy và chỉ ghi `image-tag` khi tất cả thành công; máy mới sinh ra trong lúc deploy đọc thẻ cũ (đã được xác nhận) nên không chạy bản chưa kiểm chứng.

## 8. Kịch bản demo chịu lỗi (6 phút, dùng khi bảo vệ)
Chuẩn bị (trước ~30 phút): bật RDS, rồi máy A, rồi đặt ASG Min = 1, Desired = 2, Max = 3 (máy ASG tự dựng từ AMI, khoảng 3 đến 4 phút). Mở `https://erp.transportbooking.xyz/health`, trang Targets của `tg-erp-web` (3 máy healthy: A và hai máy ASG; 4 máy sau khi tạo tải), tab Activity của ASG và Grafana (`/grafana/`).
1. Chạy máy đo (vòng lặp `curl` mỗi giây tới `/api/v1/sales/health`) trên terminal chiếu lên màn hình.
2. **Terminate một máy ASG** trong console. Chỉ cho giảng viên: vài lỗi 502 hoặc timeout trong khoảng 30 giây rồi 200 trở lại (ALB loại máy chết); nói: *còn hai máy phục vụ nên không có downtime toàn phần*. Mở tab Activity: sau khoảng 1 phút ASG ghi dòng tạo máy thay thế; khoảng 2,5 phút sau máy mới healthy. Nói: *hệ thống tự phục hồi, không cần ai can thiệp*.
3. (Tùy thời gian) **Reboot RDS with failover**: `sales` ngừng khoảng 8 giây rồi tự phục hồi; nói: *Multi-AZ đồng bộ, endpoint không đổi, DB nằm trong subnet riêng*.
4. (Nếu có thời gian) Tạo tải ngắn để cho thấy cảnh báo và chính sách: nói rằng tăng máy cần khoảng 6 phút (3 điểm đo) và giảm cần khoảng 18 phút; đưa số liệu A7 và A8 thay vì chờ.
5. Kết luận thẳng thắn: web/API và DB chịu lỗi và tự phục hồi; broker và realtime còn một bản ở máy A (mục 5), hướng nâng cấp ở mục 6.
6. Sau demo: đặt ASG Min = 0 và Desired = 0, Stop máy A, Stop RDS.

Dự phòng nếu demo AWS trục trặc: ảnh chụp và video các thí nghiệm A1 đến A8 ở mục 4, và demo cục bộ TN1, TN2 bằng `docker compose stop`.

## 9. Tự kiểm tra
- Vì sao outbox giải quyết "ghi DB xong nhưng publish thất bại"?
- Giải thích đường đi của một request từ trình duyệt đến Kong và tại sao mất một máy ứng dụng không sập hệ thống.
- Ba trạng thái circuit breaker và ngưỡng của bạn?
- Chỉ ra 3 SPOF còn lại và cách xử lý từng cái.
- Mất AZ 1a thì cái gì còn chạy, cái gì ngừng, dữ liệu nào chờ ở đâu?
- Vì sao chat và notification không chạy được ở cả hai máy?
- Phân biệt timeout (không chắc) với 409 (chắc chắn) trong luồng giữ hàng.
- Vì sao mất 50 giây mới loại máy hỏng khỏi ALB, và cách rút ngắn?
