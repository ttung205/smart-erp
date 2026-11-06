# 03. Chương 3: Tiến trình và luồng

> Rubric: "Mô tả về các tiến trình, luồng xử lý trong hệ thống phân tán và cách các tiến trình giao tiếp với nhau" (1 điểm).
> Gợi ý của rubric: Multithreading, Asynchronous Processing, Process Management (PM2, Supervisor), Task Scheduling (cron, Quartz), Background Jobs (Celery, Bull).
> Điểm đặc biệt của project: **4 ngôn ngữ nên có 4 mô hình xử lý đồng thời khác nhau**, và bạn có đủ cả năm nhóm gợi ý trên. Bài này gom chúng lại.

## 1. Hai khái niệm nền

| | Tiến trình (process) | Luồng (thread) |
|---|---|---|
| Là gì | Một chương trình đang chạy, có **bộ nhớ riêng** | Một đường thực thi **bên trong** một tiến trình, **dùng chung bộ nhớ** |
| Cô lập | Cao: một tiến trình chết không kéo theo tiến trình khác | Thấp: một luồng lỗi nặng có thể làm chết cả tiến trình |
| Chi phí tạo | Lớn | Nhỏ |
| Giao tiếp | Phải qua kênh ngoài (mạng, hàng đợi, file, tín hiệu) | Có thể dùng biến chung (kèm rủi ro tranh chấp) |

Trong hệ phân tán, ranh giới tiến trình cũng là **ranh giới hỏng hóc**: đó là lý do project tách web, relay và scheduler thành các tiến trình (container) riêng.

## 2. Bản đồ tiến trình của hệ thống

Khi chạy đủ, hệ thống có khoảng **20 container**, mỗi container là một cây tiến trình:

| Nhóm | Container | Tiến trình bên trong |
|---|---|---|
| **Web/API** | `*-service` (10 cái) | Laravel: Nginx + PHP-FPM (nhiều tiến trình). .NET: một tiến trình, thread pool. Node: một tiến trình, vòng lặp sự kiện. Python: một tiến trình, asyncio |
| **Nền, gửi event** | `catalog-relay`, `sales-relay`, `eoffice-relay` | `php artisan outbox:relay`: vòng lặp vô hạn quét bảng outbox ([docker-compose.yml](../../../docker-compose.yml): `command: ["php","artisan","outbox:relay"]`) |
| **Nền, chạy theo lịch** | `sales-scheduler` | `php artisan schedule:work`: bộ lập lịch |
| **Hạ tầng** | MySQL, Mongo, RabbitMQ, Kong, Caddy | Tiến trình riêng, nhiều luồng/worker bên trong |

**Điểm đáng nhớ:** `sales-service`, `sales-relay` và `sales-scheduler` dùng **cùng một image**, khác nhau ở **lệnh khởi động**. Cùng một mã, ba vai trò, ba tiến trình độc lập: web chết thì relay vẫn gửi event, relay chết thì web vẫn nhận yêu cầu, và mỗi thứ khởi động lại riêng.

## 3. Bốn mô hình xử lý đồng thời (một cho mỗi ngôn ngữ)

Cùng một yêu cầu "xử lý nhiều người dùng cùng lúc", mỗi ngôn ngữ giải bằng một cách khác:

### 3.1. PHP-FPM: **nhiều tiến trình, mỗi tiến trình một yêu cầu** (Laravel, 5 service)
- Mỗi yêu cầu được một **tiến trình worker** riêng xử lý từ đầu đến cuối. Cấu hình ở [docker/php-fpm.conf](../../../services/sales-service/docker/php-fpm.conf): `pm = dynamic`, `pm.max_children = 8` (tối đa 8 yêu cầu song song), `pm.start_servers = 2`, `pm.min_spare_servers = 2`, `pm.max_spare_servers = 4`.
- `pm.max_requests = 500`: mỗi worker tự khởi động lại sau 500 yêu cầu, để **chống rò rỉ bộ nhớ** tích luỹ.
- **Vì sao dùng tiến trình mà không dùng luồng?** PHP thiết kế theo kiểu "không chia sẻ gì" giữa các yêu cầu: mỗi yêu cầu bắt đầu sạch, nên không có tranh chấp bộ nhớ. Đổi lại tốn RAM hơn mỗi luồng.
- **Giới hạn cần biết:** 8 worker nghĩa là yêu cầu thứ 9 phải xếp hàng. Số 8 là đánh đổi giữa thông lượng và RAM (mỗi worker vài chục MB).
- Phía trước là **Nginx** ([nginx.conf](../../../services/sales-service/docker/nginx.conf): `worker_processes auto`, `worker_connections 1024`) nhận kết nối rồi chuyển qua FastCGI sang FPM.

### 3.2. .NET: **thread pool + async/await** (purchase, inventory, chat)
- Một tiến trình, nhiều luồng lấy từ **thread pool**. Với `async/await`, khi chờ I/O (truy vấn DB, gọi HTTP) luồng được **trả lại pool** để phục vụ yêu cầu khác, nên vài luồng có thể phục vụ hàng nghìn yêu cầu đang chờ.
- **Tiến trình nền ngay trong tiến trình web** nhờ `BackgroundService` (dịch vụ được host chạy song song với web): [OutboxPublisherWorker](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/OutboxPublisherWorker.cs) gửi event, `RabbitMqConsumerWorker` nhận event; đăng ký ở [DependencyInjection.cs:28-29](../../../services/inventory-service/src/Inventory.Infrastructure/DependencyInjection.cs#L28-L29). Purchase cũng có `OutboxRelayBackgroundService`.
- **Khác Laravel:** Laravel phải tách relay thành container riêng, còn .NET gộp relay và consumer vào chính tiến trình web (hai luồng nền). Đổi lại một lỗi nặng của web cũng làm dừng cả hai worker.
- Vòng lặp của worker ([OutboxPublisherWorker.cs:33-66](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/OutboxPublisherWorker.cs#L33-L66)): quét, ngủ `Task.Delay`, lỗi thì **giãn dần** (tối đa 30 giây), và thoát êm khi nhận `stoppingToken` (tín hiệu dừng).

### 3.3. Node.js: **một luồng + vòng lặp sự kiện** (notification)
- Chỉ **một luồng** chạy JavaScript, nhưng không bao giờ chờ lãng phí: mọi thao tác I/O (Mongo, RabbitMQ, HTTP, gửi email, Socket.IO) là **bất đồng bộ**, xong thì "gọi lại". Một luồng xử lý được rất nhiều kết nối WebSocket đồng thời.
- **Cái giá:** một đoạn mã **tính toán nặng** (không phải I/O) sẽ **chặn cả tiến trình**, mọi người dùng bị đứng. Notification chủ yếu là I/O nên phù hợp; đây là lý do tính nặng không đặt ở Node.
- Dùng `async/await` rộng rãi: consumer RabbitMQ nhận `async (message) => {...}` ([consumer.js:81](../../../services/notification-service/src/messaging/consumer.js#L81)) và `prefetch` giới hạn số message xử lý đồng thời.
- **Hai consumer độc lập** trong cùng tiến trình: một cho `erp.notification.events`, một cho `erp.email.send` ([email.queue.js:24](../../../services/notification-service/src/email/email.queue.js#L24)). Đây là **hàng đợi công việc nền kiểu Bull/Celery**: yêu cầu gửi email được xếp hàng, có retry, không chặn người gọi.

### 3.4. Python: **asyncio + đẩy việc nặng sang luồng** (ai-service)
- FastAPI chạy trên vòng lặp sự kiện asyncio (giống Node). Nhưng việc **tính toán nặng** (đồng bộ kho phân tích, chạy công cụ phân tích) **không được chặn vòng lặp**, nên dùng `asyncio.to_thread(...)` để chạy trong luồng riêng: [agent.py:104](../../../services/ai-service/app/agent.py#L104) (chạy công cụ), [main.py:26](../../../services/ai-service/app/main.py#L26) (đồng bộ kho).
- **Tác vụ nền định kỳ ngay trong tiến trình:** `sync_loop` ([main.py:23-28](../../../services/ai-service/app/main.py#L23-L28)) là `while True`: đồng bộ, rồi `await asyncio.sleep(SYNC_INTERVAL_SECONDS)` (300 giây), được khởi động bằng `asyncio.create_task` trong hàm `lifespan` và huỷ khi tắt ([main.py:30-44](../../../services/ai-service/app/main.py#L30-L44)). Mặc định **tắt** (`WAREHOUSE_SYNC_ENABLED=false`).

### Bảng so sánh (nên thuộc)
| | Laravel (PHP-FPM) | .NET | Node.js | Python (FastAPI) |
|---|---|---|---|---|
| Đơn vị song song | **Tiến trình** | **Luồng** (thread pool) | **Một luồng** + sự kiện | **Một luồng** + sự kiện, việc nặng sang luồng phụ |
| Chờ I/O | Worker đứng chờ | Trả luồng về pool (`await`) | Không chặn (callback/`await`) | Không chặn (`await`) |
| Việc nặng CPU | Không ảnh hưởng worker khác | Dùng luồng khác | **Chặn tất cả** | `to_thread` |
| Tiến trình nền | **Container riêng** | `BackgroundService` trong tiến trình web | Consumer trong tiến trình | `asyncio.create_task` |
| Hạn chế | `max_children` | Lỗi nặng ảnh hưởng cả worker | Tính nặng chặn | GIL (một lúc một luồng chạy mã Python) |

## 4. Quản lý tiến trình (Process Management)

Trong Docker, **người giám sát tiến trình là Docker**, không phải PM2 hay Supervisor. Ba tầng:

1. **Trong một container có nhiều tiến trình** (Nginx + PHP-FPM): script [docker/start-web](../../../services/sales-service/docker/start-web) làm vai trò "mini-Supervisor": chạy cả hai nền, rồi `wait -n` chờ **bất kỳ cái nào chết** ([start-web:19](../../../services/sales-service/docker/start-web#L19)). Một trong hai chết thì **dừng nốt và thoát** để Docker khởi động lại cả container, thay vì "sống dở" (Nginx còn sống, FPM chết thì trả 502 mãi). Nó cũng bắt tín hiệu `TERM`/`INT` để tắt cả hai êm ([start-web:14-17](../../../services/sales-service/docker/start-web#L14-L17)).
2. **Giữa các container:** `restart: unless-stopped` trong compose: tiến trình chính chết thì Docker khởi động lại (đây chính là cơ chế đã tự cứu MySQL trong sự cố lần trước).
3. **Healthcheck + `depends_on: service_healthy`:** container chỉ được coi là sẵn sàng khi `/health` trả lời, và container phụ thuộc chỉ khởi động sau khi nó khoẻ.

**Tắt êm (graceful shutdown):** khi nhận `SIGTERM` (lúc `docker stop` hoặc deploy), dịch vụ phải **ngừng nhận việc mới và xử lý nốt việc đang dở** rồi mới thoát. Notification làm đúng: dừng consumer, đóng Socket.IO, đóng server, đóng kết nối Mongo rồi `process.exit(0)` ([index.js:73-83](../../../services/notification-service/src/index.js#L73-L83)). Worker .NET dùng `stoppingToken`, `start-web` bắt `TERM`. Thiếu tắt êm thì một message đang xử lý có thể bị bỏ dở (và RabbitMQ sẽ giao lại, nhờ consumer idempotent mà không hại).

## 5. Lập lịch tác vụ (Task Scheduling)

Có **ba loại lịch** trong hệ thống:

| Lịch | Cơ chế | Việc | Code |
|---|---|---|---|
| **Scheduler của ứng dụng** | `php artisan schedule:work` chạy trong container `sales-scheduler`, đọc lịch khai báo trong code | Đối soát hàng giữ ở Inventory **mỗi 5 phút** | [console.php:12](../../../services/sales-service/routes/console.php#L12): `->everyFiveMinutes()->withoutOverlapping()` |
| **Vòng lặp thăm dò** (polling) | Worker tự ngủ rồi quét lại | Relay outbox quét mỗi 1,5 giây; ai-service đồng bộ 5 phút | [RelayOutboxEvents.php:23](../../../services/sales-service/app/Console/Commands/RelayOutboxEvents.php#L23) (`--sleep=1500`), [main.py:28](../../../services/ai-service/app/main.py#L28) |
| **Cron của hệ điều hành** | `/etc/cron.d/smart-erp-backup` trên EC2 | Sao lưu **mỗi giờ** | [infrastructure/aws/ec2-bootstrap.sh](../../../infrastructure/aws/ec2-bootstrap.sh) |

- **`withoutOverlapping()`:** nếu lần chạy trước (5 phút) chưa xong thì lần sau **không chạy chồng**, tránh hai tiến trình cùng nhả một khoản giữ hàng. Nó dùng một khoá trong cache, nên chỉ đúng khi có **một** scheduler (xem điểm yếu).
- **Vì sao đối soát chạy bằng lịch mà không bằng sự kiện?** Đối soát là việc "dọn dẹp những gì lỡ hỏng". Nó phải chạy **kể cả khi** chính cơ chế sự kiện đã hỏng, nên dùng thời gian làm tín hiệu là đáng tin hơn.

## 6. Công việc nền (Background Jobs)

Ba kiểu, từ đơn giản đến phức tạp:
1. **Relay outbox** (Laravel và .NET): "công việc nền" là *chuyển các dòng trong bảng outbox lên RabbitMQ*. Bền vì trạng thái nằm trong database (xem bài 04).
2. **Hàng đợi email** (`erp.email.send`): người gọi chỉ **xếp việc vào hàng đợi** rồi đi tiếp; consumer riêng gửi, có retry 5 lần rồi chuyển vào hàng chết. Đây là dạng Celery/Bull, nhưng dùng RabbitMQ.
3. **Đồng bộ kho phân tích** của ai-service: tác vụ định kỳ trong tiến trình.

## 7. Các tiến trình giao tiếp với nhau thế nào?

| Cách | Dùng ở đâu | Đặc điểm |
|---|---|---|
| **HTTP/REST** | Service gọi service | Đồng bộ, chờ kết quả (bài 04) |
| **Hàng đợi tin nhắn (AMQP)** | Relay → RabbitMQ → consumer | Bất đồng bộ, bền, tách rời |
| **Database làm kênh** | Outbox: web ghi, relay đọc | Hai tiến trình không biết nhau, chỉ chung bảng trong cùng service |
| **Khoá file `flock`** | Hai relay cùng lúc | Chỉ **một** relay chạy được; hoạt động trong một máy |
| **Tín hiệu hệ điều hành** | `TERM`/`INT` | Yêu cầu tắt êm |
| **Mạng Docker + DNS** | Mọi container | Gọi nhau bằng tên service |

## 8. Thầy có thể hỏi

**H: Tiến trình và luồng khác nhau thế nào? Project dùng cái nào?**
Đ: Tiến trình có bộ nhớ riêng, cô lập cao; luồng dùng chung bộ nhớ trong một tiến trình, nhẹ hơn nhưng dễ tranh chấp. Project dùng cả hai tuỳ ngôn ngữ: Laravel dùng nhiều tiến trình (PHP-FPM, tối đa 8 yêu cầu song song), .NET dùng thread pool với async/await, Node và Python dùng vòng lặp sự kiện một luồng.

**H: Vì sao Node và Python phải tránh việc tính toán nặng?**
Đ: Vì chỉ có một luồng chạy mã; một đoạn tính nặng chặn toàn bộ vòng lặp sự kiện và mọi người dùng bị đứng. Bên Python em đẩy việc nặng sang luồng phụ bằng `asyncio.to_thread`.

**H: Em quản lý tiến trình thế nào? Có dùng PM2 hay Supervisor không?**
Đ: Dùng Docker thay cho chúng. Trong container có hai tiến trình (Nginx và PHP-FPM) thì script `start-web` chạy cả hai và thoát khi một cái chết, để `restart: unless-stopped` của Docker khởi động lại cả container, tránh trạng thái "sống dở". Healthcheck và `depends_on` điều khiển thứ tự khởi động.

**H: Tắt êm là gì, vì sao cần?**
Đ: Khi nhận tín hiệu dừng, dịch vụ ngừng nhận việc mới và xử lý nốt việc đang dở rồi mới thoát. Notification dừng consumer, đóng WebSocket, đóng kết nối rồi mới thoát. Nếu tắt đột ngột, message đang xử lý có thể bị bỏ dở; nhờ consumer idempotent nên được giao lại cũng không hại.

**H: Việc định kỳ chạy ở đâu? Hai lần chạy chồng nhau thì sao?**
Đ: Đối soát giữ hàng chạy trong container `sales-scheduler` mỗi 5 phút, có `withoutOverlapping` để lần sau không chạy chồng lần trước. Sao lưu chạy bằng cron của hệ điều hành mỗi giờ. Relay outbox là vòng lặp thăm dò mỗi 1,5 giây.

**H: Vì sao tách relay thành container riêng thay vì chạy trong web?**
Đ: Để tách ranh giới hỏng hóc và đơn giản hoá vận hành. Web chết thì relay vẫn gửi event; relay chết thì web vẫn nhận yêu cầu; và mỗi thứ khởi động lại độc lập. Hơn nữa phải có đúng **một** relay mỗi service để giữ thứ tự, điều khó bảo đảm nếu relay nằm trong web mà web có thể chạy nhiều bản.

## 9. Điểm yếu (nói thật khi bị hỏi)

- **Relay và scheduler chỉ chạy một bản.** `flock` và `withoutOverlapping` chỉ hiệu lực trong một máy (khoá file, khoá trong cache file cục bộ). Chạy hai máy thì chạy chồng. Relay cũng là điểm đơn lẻ: chết thì event chờ (không mất) cho tới khi nó sống lại.
- **Các container relay và scheduler không có healthcheck**, chỉ dựa vào `restart`. Một relay "treo" (còn sống nhưng không làm gì) sẽ không bị phát hiện. Hướng khắc phục: xuất metric hoặc kiểm tra tuổi của dòng PENDING cũ nhất trong outbox.
- **`pm.max_children = 8` là con số cố định,** chưa dựa trên đo tải thật. Quá thấp thì nghẽn, quá cao thì hết RAM.
- **.NET gộp worker và web trong một tiến trình:** lỗi nặng của web làm dừng cả worker; Laravel thì tách nên tốt hơn ở điểm này.
- **Không có hàng đợi công việc kiểu Laravel Queue** (không có queue worker): việc nặng đi qua RabbitMQ (email) hoặc là yêu cầu đồng bộ.
- **Chưa có tự mở rộng (autoscaling):** số worker, số bản sao đều cố định.
- **Thứ tự khởi động và health có tính phụ thuộc:** health của `sales-service` kiểm tra **cả inventory-service**. Nếu bật sales mà inventory chưa lên thì sales báo `DEGRADED` (HTTP 503, `inventory_service: DOWN`) và `docker compose up --wait` dừng ngay vì thấy `unhealthy`, dù vài chục giây sau mọi thứ ổn định (đã gặp khi bật thử trên máy dev). Đó là hành vi có chủ đích (health phản ánh đúng tình trạng thật) nhưng khiến thứ tự khởi động quan trọng.

## 10. Thực hành

1. **Xem cây tiến trình trong container web:**
   ```bash
   docker compose exec sales-service ps aux
   ```
   Bạn thấy `nginx: master process` cùng các `nginx: worker process`, và `php-fpm: master process` cùng vài `php-fpm: pool www` (đây là các **worker xử lý yêu cầu**). Gửi nhiều yêu cầu cùng lúc rồi chạy lại, số `php-fpm: pool www` tăng dần theo `pm = dynamic` (tối đa 8).
2. **Thấy `start-web` hoạt động:** `docker compose exec sales-service sh -c 'pkill php-fpm'`, rồi `docker inspect -f '{{.RestartCount}}' erp-sales`: số lần khởi động lại tăng từ 0 lên 1 vì `start-web` thấy FPM chết nên thoát, và Docker dựng lại container (khoảng 10 giây sau lại `healthy`). Đã chạy thử: 0 → 1, rồi `Up ... (healthy)`.
3. **Thấy hai container cùng image, khác vai trò:** `docker compose exec sales-relay ps aux` và `docker compose exec sales-scheduler ps aux`: chỉ có `php artisan outbox:relay` hoặc `schedule:work`, không có Nginx.
4. **Chạy lịch bằng tay để thấy kết quả:** `docker compose exec sales-scheduler php artisan sales:reconcile-reservations --dry-run`.
5. **Xem cron trên EC2:** `cat /etc/cron.d/smart-erp-backup` và `tail /var/log/smart-erp-backup.log`.

## 11. Tự kiểm tra
- [ ] Phân biệt tiến trình và luồng; nêu mô hình của từng ngôn ngữ trong project.
- [ ] Giải thích vì sao việc tính nặng làm Node "đứng" còn Laravel thì không.
- [ ] Giải thích `start-web` làm gì và vì sao thoát khi một tiến trình chết.
- [ ] Kể ba loại lịch trong hệ thống và nơi chạy của từng loại.
- [ ] Giải thích tắt êm và chỉ ra ví dụ có thật trong code.
- [ ] Nói được 3 điểm yếu của phần tiến trình mà không nhìn tài liệu.
