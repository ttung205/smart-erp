# 04. Chương 4: Trao đổi thông tin giữa các node

> Rubric: "Đánh giá các phương thức giao tiếp giữa các node trong hệ thống phân tán" (1 điểm).
> Gợi ý của rubric: REST, Message Queue, Real-time, Caching, RPC, Event Streaming.
> Project có 3 trong 6 loại một cách đầy đủ (REST, Message Queue, Real-time). Hai loại không có (Caching phân tán, RPC) và bài này nói rõ vì sao, để bạn trả lời được khi thầy hỏi.

## 1. Bức tranh: ba kênh, mỗi kênh có lý do riêng

```
                  ┌── REST (đồng bộ) ─────────► cần trả lời NGAY để làm tiếp
 Node A ──────────┼── RabbitMQ (bất đồng bộ) ─► chỉ cần báo "đã xảy ra", A không chờ
                  └── WebSocket (đẩy) ────────► server chủ động báo tới trình duyệt
```

Quy tắc chọn kênh của project (tóm từ `CLAUDE.md` mục 4), hỏi theo thứ tự:
1. Bên gọi có cần câu trả lời ngay mới đi tiếp được không? → **REST**.
2. Chỉ là thông báo "việc X đã xảy ra" để service khác tự phản ứng? → **Event qua queue**.
3. Việc nặng, chậm, dễ lỗi tạm thời (gửi email) mà người dùng không chờ? → **Command message qua queue**, có retry.

Ví dụ trong project:
| Tình huống | Kênh | Vì sao |
|---|---|---|
| Lập hoá đơn: giữ hàng ở Inventory | REST | Người lập cần biết ngay đủ hàng hay không, không đủ thì không được tạo chứng từ |
| Đơn được duyệt: báo cho người tạo | Event | Sales không cần chờ Notification; Notification chết thì đơn vẫn được duyệt |
| Gửi email báo giá | Command qua queue `erp.email.send` | Chậm, dễ lỗi tạm thời, người dùng không đợi |
| Chuông thông báo nhảy lên | WebSocket | Server đẩy xuống trình duyệt, không để trình duyệt hỏi liên tục |

## 2. Kênh 1: REST (đồng bộ) và các lớp bảo vệ

REST giữa hai service dễ gặp lỗi mạng, nên mỗi lời gọi trong project được bọc 4 lớp:

| Lớp | Chống lỗi gì | Code (sales gọi service khác) |
|---|---|---|
| Timeout | Service kia treo, bên gọi bị treo theo | [InternalHttp.php:23](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L23) (Inventory 3s), [:29](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L29) (Finance 5s) |
| Retry có chọn lọc | Lỗi mạng thoáng qua | [InternalHttp.php:39](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L39): tối đa 2 lần, chỉ khi lỗi **kết nối** ([:43-46](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L43-L46)) |
| Circuit breaker | Service kia chết hẳn, gọi tiếp chỉ làm chậm thêm | [CircuitBreaker.php](../../../services/sales-service/app/Infrastructure/Clients/CircuitBreaker.php) |
| Chuyển tiếp danh tính | Service đích cần biết "ai" đang gọi | [InternalHttp.php:34](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L34): có người dùng thì chuyển JWT, không có (job) thì dùng `X-Internal-Service-Key` |

### Retry có chọn lọc: vì sao không retry mọi thứ?
Retry lệnh **ghi** có thể ghi hai lần. Project chia rõ:
- **Retry bật** cho lệnh đọc và lệnh kho **idempotent** theo số chứng từ (giữ hàng, nhả hàng, xuất kho): gọi lại cùng số chứng từ không đổi kết quả ([InternalHttp.php:18-20](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L18-L20)).
- **Retry tắt** cho Finance (ghi công nợ) và lệnh trả hàng vì chúng không chống ghi trùng ([InternalHttp.php:26](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L26)).
- Chỉ retry `ConnectionException`, không retry lỗi 4xx/5xx từ nghiệp vụ, và **không retry khi mạch đang mở** ([:45](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L45)).

### Circuit breaker: 3 trạng thái
```
        5 lỗi liên tiếp                30 giây
CLOSED ───────────────► OPEN ───────────────► HALF_OPEN ──thử 1 request──► thành công → CLOSED
  ▲  (gọi bình thường)   (từ chối ngay,        (cho đúng 1                    lỗi → OPEN lại
  └─ thành công ◄────── không gọi service)      request đi thử)
```
- Hằng số: [CircuitBreaker.php:25-27](../../../services/sales-service/app/Infrastructure/Clients/CircuitBreaker.php#L25-L27) (5 lỗi, mở 30s).
- Chỉ tính lỗi kết nối và HTTP 5xx; **4xx như 409 hết hàng không tính** vì đó là câu trả lời nghiệp vụ hợp lệ ([:16](../../../services/sales-service/app/Infrastructure/Clients/CircuitBreaker.php#L16), [:58](../../../services/sales-service/app/Infrastructure/Clients/CircuitBreaker.php#L58)).
- Nửa mở chỉ cho **một** request thử nhờ `Cache::add` (thao tác nguyên tử) ([:88](../../../services/sales-service/app/Infrastructure/Clients/CircuitBreaker.php#L88)).
- Xem trạng thái thật: `GET /api/v1/sales/health` có mục `circuit_breakers`.
- Cùng ý tưởng ở .NET dùng thư viện Polly: [chat-service DependencyInjection.cs:25-35](../../../services/chat-service/src/Chat.Infrastructure/DependencyInjection.cs#L25-L35) (timeout 5s, retry 2 lần, mở 30s).

**Điểm yếu cần biết:** trạng thái mạch lưu trong cache kiểu file ([docker-compose.yml](../../../docker-compose.yml) `CACHE_STORE: file`), nên mỗi container có trạng thái riêng. Một service chạy nhiều bản sẽ có nhiều mạch độc lập. Với 1 bản mỗi service như hiện tại thì không sao.

### Hợp đồng REST chung
- Mọi service trả cùng một **envelope**: `{success, data, error, meta{correlation_id, timestamp}}` (xem `CLAUDE.md` mục 6, `docs/contracts/api-spec.md`).
- JSON luôn `snake_case`, thời gian ISO 8601 UTC.
- Service phụ thuộc không phản hồi thì trả `503 DEPENDENCY_UNAVAILABLE`, không âm thầm dùng dữ liệu rỗng.

## 3. Kênh 2: RabbitMQ (bất đồng bộ)

### 3.1. Bài toán và lời giải
**Bài toán (dual write):** sau khi lưu đơn vào MySQL, phải báo cho service khác. Làm hai việc "ghi DB" và "gửi message" thì có thể một việc thành công, việc kia thất bại. Mất message, hoặc báo cho người khác về một đơn thực ra đã rollback.

**Lời giải: Transactional Outbox.** Ghi event vào bảng `outbox_events` **trong cùng transaction** với dữ liệu nghiệp vụ. Một tiến trình riêng (relay) đọc bảng đó và gửi lên RabbitMQ.

```
 [Sales API]                          [sales-relay]                 [RabbitMQ]            [Notification]
  BEGIN                                   │                              │                      │
   INSERT sales_orders ...                │  quét PENDING mỗi 1,5s       │                      │
   INSERT outbox_events (PENDING) ◄───────┤                              │                      │
  COMMIT  ← cùng transaction              │── publish + chờ confirm ────►│── route theo key ───►│ xử lý
                                          │  đánh dấu PUBLISHED          │                      │ idempotent
```

| Bước | Code |
|---|---|
| Bảng outbox (`event_id` UNIQUE, `status`, `retry_count`) | [create_outbox_events_table.php:12-25](../../../services/sales-service/database/migrations/2026_10_15_160000_create_outbox_events_table.php#L12-L25) |
| Ghi event (gọi **bên trong** `DB::transaction`), có `event_id` UUID và `correlation_id` | [Outbox.php:17-40](../../../services/sales-service/app/Support/Outbox.php#L17-L40) |
| Relay: vòng lặp quét `PENDING/FAILED`, tối đa 50 event, theo thứ tự `id` | [RelayOutboxEvents.php:77-110](../../../services/sales-service/app/Console/Commands/RelayOutboxEvents.php#L77-L110) |
| Publisher: `confirm_select`, `mandatory`, bắt message trả về và nack | [RabbitMqPublisher.php:16-26](../../../services/sales-service/app/Messaging/RabbitMqPublisher.php#L16-L26), [:52-59](../../../services/sales-service/app/Messaging/RabbitMqPublisher.php#L52-L59) |
| Chạy trong container riêng | [docker-compose.yml](../../../docker-compose.yml) `sales-relay` (cùng image với `sales-service`, lệnh `php artisan outbox:relay`) |
| Bản .NET (purchase, inventory): dispatcher + publisher có confirm, lưu từng event ngay sau khi broker xác nhận | [OutboxDispatcher.cs:64-67](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/OutboxDispatcher.cs#L64-L67), [ConfirmedOutboxPublisher.cs:20](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/ConfirmedOutboxPublisher.cs#L20), [:38](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/ConfirmedOutboxPublisher.cs#L38) |

### 3.2. Relay chịu lỗi thế nào
- **Broker chết:** relay dừng lượt đó để giữ thứ tự, chờ giãn dần (gấp đôi mỗi lượt, tối đa 60s) rồi thử lại ([RelayOutboxEvents.php:60-63](../../../services/sales-service/app/Console/Commands/RelayOutboxEvents.php#L60-L63)). Event không mất vì vẫn nằm trong MySQL.
- **Event không có queue nào nhận** (sai routing key): đánh `FAILED`, thử lại thưa dần, **không chặn** các event phía sau ([:98-101](../../../services/sales-service/app/Console/Commands/RelayOutboxEvents.php#L98-L101)).
- **Relay crash giữa lúc publish và đánh dấu `PUBLISHED`:** event bị gửi lại. Đây là bản chất **at-least-once** nên consumer phải idempotent (mục 3.4).
- **Chỉ một relay mỗi service:** khoá `flock` ([:32-37](../../../services/sales-service/app/Console/Commands/RelayOutboxEvents.php#L32-L37)) để giữ thứ tự. Đây cũng là lý do relay không scale ngang được (xem bài 06).

### 3.3. Topology RabbitMQ
Khai báo trong [definitions.json](../../../infrastructure/rabbitmq/definitions.json) (nạp lúc broker khởi động).

```
Publisher ──routing key──► [erp.events] (topic, durable)
                              │ binding theo routing key
                  ┌───────────┴───────────┐
          erp.inventory.events     erp.notification.events ◄── retry (TTL 10s) ──┐
          (purchase.goods.*,       (14 key: sales.order.*,       erp.notification.retry
           catalog.product.*)       eoffice.leave.*, ...)
                  │ nack / quá số lần                │ nack / quá 5 lần
                  └───────────► [erp.dlx] ───────────┘
                                    │ binding "#"
                              erp.dead_letters  (chờ người xử lý)
```

| Thành phần | Dòng trong definitions.json | Vai trò |
|---|---|---|
| Exchange `erp.events` (topic) | [:10](../../../infrastructure/rabbitmq/definitions.json#L10) | Cửa vào mọi event; routing key dạng `<service>.<aggregate>.<event>` |
| Exchange `erp.dlx` | [:19](../../../infrastructure/rabbitmq/definitions.json#L19) | Nhận message bị nack/hết lượt thử |
| Queue `erp.notification.events` | [:80](../../../infrastructure/rabbitmq/definitions.json#L80) | Có `x-dead-letter-exchange` trỏ về `erp.dlx` |
| Queue `erp.notification.retry` | [:90](../../../infrastructure/rabbitmq/definitions.json#L90) | TTL 10 giây; hết hạn thì quay lại queue chính (retry có độ trễ) |
| Queue `erp.dead_letters` | [:122](../../../infrastructure/rabbitmq/definitions.json#L122) | "Nghĩa địa" để người vận hành xem và phát lại |

Phát lại dead letter sau khi sửa lỗi: [scripts/replay-dead-letters.ps1](../../../scripts/replay-dead-letters.ps1) (không `-Replay` thì chỉ liệt kê).

Vì sao exchange kiểu **topic**? Một event có thể có nhiều người quan tâm (sales.order.approved → Notification, sau này thêm Analytics). Publisher chỉ biết routing key, không biết ai nhận → **tách rời** (decoupling). Thêm consumer mới không phải sửa publisher.

### 3.4. Consumer: ack, retry và idempotent
Cả hai consumer đều **ack thủ công** (không `autoAck`), chỉ ack sau khi xử lý xong.

| | Notification (Node) | Inventory (.NET) |
|---|---|---|
| Ack/Nack | [consumer.js:92-111](../../../services/notification-service/src/messaging/consumer.js#L92-L111) | [RabbitMqConsumerWorker.cs:69-79](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/RabbitMqConsumerWorker.cs#L69-L79) |
| Khi lỗi | Chuyển sang `erp.notification.retry`, tối đa 5 lần, quá thì nack → DLX ([:97-102](../../../services/notification-service/src/messaging/consumer.js#L97-L102)) | Nack không requeue → DLX ngay (chưa có retry queue) |
| Prefetch | cấu hình | 10 ([:61](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/RabbitMqConsumerWorker.cs#L61)) |
| Tự nối lại khi mất kết nối | 15 giây ([consumer.js:39-49](../../../services/notification-service/src/messaging/consumer.js#L39-L49)) | 20 giây ([:42-50](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/RabbitMqConsumerWorker.cs#L42-L50)) |

**Idempotent consumer** (gửi trùng thì xử lý một lần), vì ngữ nghĩa là at-least-once:
- **Inventory:** kiểm tra `processed_events`, rồi nhập kho **và** ghi `processed_events` trong **một transaction**. Crash giữa chừng thì rollback cả hai, message giao lại được xử lý đúng một lần ([EventConsumerHandler.cs:45-82](../../../services/inventory-service/src/Inventory.Infrastructure/Services/EventConsumerHandler.cs#L45-L82)). Nếu hai bản sao xử lý đồng thời thì khoá chính `(event_id, consumer_name)` chặn bản thứ hai ([:84-92](../../../services/inventory-service/src/Inventory.Infrastructure/Services/EventConsumerHandler.cs#L84-L92)).
- **Notification:** `processed_events` trong Mongo với `_id = event_id` ([notification.repository.js:35-45](../../../services/notification-service/src/notifications/notification.repository.js#L35-L45)) và index UNIQUE `(event_id, recipient_id)` ([db.js:23](../../../services/notification-service/src/db.js#L23)) chống tạo thông báo trùng.

> **Câu nói chốt:** "Không thể bảo đảm giao đúng một lần trong hệ phân tán. Bọn em chọn **at-least-once** ở phía gửi (outbox + relay + confirm) và **idempotent** ở phía nhận (processed_events + UNIQUE), kết quả là xử lý đúng một lần về mặt hiệu quả (effectively once)."

## 4. Kênh 3: Real-time (đẩy từ server xuống trình duyệt)

| | Socket.IO (thông báo) | SignalR (chat) |
|---|---|---|
| Service | notification-service (Node) | chat-service (.NET) |
| Xác thực | JWT ở bước handshake, sai thì từ chối ([socket.js:14-19](../../../services/notification-service/src/realtime/socket.js#L14-L19)) | JWT, `UserId <= 0` thì ngắt kết nối ([ChatHub.cs:19-25](../../../services/chat-service/src/Chat.Api/Realtime/ChatHub.cs#L19-L25)) |
| Phòng/nhóm | Mỗi user một room `user:{id}` ([socket.js:23](../../../services/notification-service/src/realtime/socket.js#L23)) | Mỗi user một nhóm `user_{id}` ([ChatHub.cs:15](../../../services/chat-service/src/Chat.Api/Realtime/ChatHub.cs#L15), [:26](../../../services/chat-service/src/Chat.Api/Realtime/ChatHub.cs#L26)) |
| Gửi tới một user | `toUser(userId, event, data)` ([socket.js:29-31](../../../services/notification-service/src/realtime/socket.js#L29-L31)) | `IHubContext` |
| Timeout ở Kong | 1 giờ ([kong.yml:69-70](../../../infrastructure/kong/kong.yml#L69-L70)) | 1 giờ ([kong.yml:100-101](../../../infrastructure/kong/kong.yml#L100-L101)) |

Nguyên tắc thiết kế: **socket chỉ là kênh báo hiệu**; dữ liệu gốc luôn đọc lại được qua REST ([socket.js:5-6](../../../services/notification-service/src/realtime/socket.js#L5-L6)). Mất kết nối thì mất tín hiệu nhưng không mất dữ liệu.

Luồng đầy đủ khi sale gửi duyệt báo giá, để kể liền một mạch:
```
Sale bấm "Gửi duyệt" → Sales: DB::transaction { cập nhật báo giá; Outbox::record(...) } → trả 200 ngay
 → sales-relay publish "sales.quotation.submitted" → RabbitMQ → erp.notification.events
 → Notification: tra người có quyền duyệt (REST sang Identity) → ghi Mongo (UNIQUE chống trùng)
 → Socket.IO đẩy "notification:new" tới room user:{managerId} → chuông của manager sáng lên
```

## 5. Hai kênh rubric gợi ý nhưng project không có

| Loại | Có không | Lý do (trả lời thầy) |
|---|---|---|
| **Caching phân tán** (Redis, Memcached) | Không | Chưa có nhu cầu: dữ liệu chủ yếu là giao dịch, ghi nhiều. Cache hiện chỉ là file cho trạng thái circuit breaker (mỗi container riêng). Nếu mở rộng nhiều bản thì cần Redis để chia sẻ trạng thái mạch và rate limit (Kong đang đếm trong bộ nhớ, `policy: local`). |
| **RPC** (gRPC, Thrift) | Không | REST/JSON đủ cho khối lượng hiện tại, dễ debug bằng trình duyệt/curl, dùng được từ cả 4 ngôn ngữ. gRPC có lợi khi cần hiệu năng cao hoặc hợp đồng chặt giữa service; có thể thay cho các lời gọi nội bộ Sales↔Inventory về sau. |
| **Event streaming** (Kafka) | Không | RabbitMQ (message queue) đủ cho mô hình "báo sự kiện, mỗi consumer xử lý một lần". Kafka hợp khi cần giữ lịch sử event để phát lại hoặc xử lý luồng lớn. Đổi lại, project có sổ cái tồn kho bất biến phục vụ việc tra cứu lịch sử. |

Đừng nói "em không làm vì không biết"; hãy nói "em đã cân nhắc và chọn REST + RabbitMQ vì...".

## 6. Thầy có thể hỏi

**H: Vì sao dùng cả REST lẫn message queue? Dùng một loại cho đơn giản không?**
Đ: Hai loại giải hai bài toán khác nhau. Giữ hàng cần câu trả lời ngay (thiếu hàng thì không được lập chứng từ) nên là REST. Báo thông báo thì không cần chờ và không được làm sales phụ thuộc vào notification nên là event. Dùng queue giả lập hỏi đáp thì phức tạp vô ích; dùng REST cho thông báo thì sales chết theo notification.

**H: Outbox giải quyết vấn đề gì? Sao không publish thẳng trong controller?**
Đ: Publish thẳng thì "ghi DB" và "gửi message" là hai việc rời nhau, một trong hai có thể hỏng và hệ thống lệch. Outbox biến việc gửi thành một dòng trong cùng transaction với dữ liệu: có đơn thì chắc chắn có event, không có đơn (rollback) thì không có event. Relay chỉ việc gửi cho đến khi thành công.

**H: Message bị gửi hai lần thì sao?**
Đ: Bình thường và đã tính trước. Relay có thể gửi lại nếu crash trước khi đánh dấu; broker có thể giao lại. Consumer kiểm tra `processed_events` trong cùng transaction với việc xử lý, kèm khoá chính `(event_id, consumer_name)` làm lớp chặn cuối cùng khi hai bản đến cùng lúc.

**H: Thứ tự message có được bảo đảm không?**
Đ: Chỉ mức độ: mỗi service một relay (khoá `flock`) và gửi theo `id` tăng dần, dừng cả lượt khi broker lỗi để không đảo thứ tự. Nhưng consumer **không được giả định** thứ tự tuyệt đối vì retry queue có thể làm một message đến muộn hơn message sau nó.

**H: Circuit breaker mở thì người dùng thấy gì?**
Đ: Lập hoá đơn khi Inventory chết trả `503 DEPENDENCY_UNAVAILABLE` rõ ràng thay vì treo 30 giây; chứng từ không được tạo. Hệ thống cố ý thất bại nhanh ("fail fast") để giữ tài nguyên và bảo toàn tính đúng đắn.

**H: Nếu RabbitMQ chết hẳn một ngày?**
Đ: Mọi nghiệp vụ vẫn chạy bình thường, chỉ thiếu thông báo và đồng bộ bất đồng bộ. Event tích trong `outbox_events` ở trạng thái PENDING/FAILED; khi broker sống lại, relay gửi hết theo thứ tự. (Bạn có thể **chứng minh** bằng Bài 2 trong hướng dẫn Adminer: `docker compose stop rabbitmq`, tạo báo giá, xem `PENDING`, rồi `start` lại.)

## 7. Điểm yếu (nói thật khi bị hỏi)

- **Hai nhóm outbox cài đặt khác nhau nhưng cùng bảo đảm:** Laravel (`RabbitMqPublisher`) và .NET (`ConfirmedOutboxPublisher`, ở cả purchase lẫn inventory) đều bật publisher confirm và chỉ đánh `PUBLISHED` sau khi broker xác nhận. Khác biệt: Laravel còn dùng `mandatory` để bắt message không có queue nhận. Lưu ý `docs/architecture/ARCHITECTURE.md` còn ghi .NET không có confirm; mục đó đã lỗi thời so với code.
- **Inventory không có retry queue** (nack là vào thẳng dead letter), khác Notification có retry 5 lần.
- **`IssueStock` (xuất kho) không atomic với outbox:** cập nhật kho và ghi event `inventory.goods.issued` ở hai bước tách ([StocksController.cs:566-595](../../../services/inventory-service/src/Inventory.Api/Controllers/StocksController.cs#L566-L595)). Crash giữa hai bước thì kho đã trừ nhưng thiếu event (Notification không báo). Dữ liệu kho vẫn đúng.
- **Khai báo queue lặp ở hai nơi** (definitions.json và code của consumer) nên có nguy cơ lệch cấu hình.
- **Không có Redis**: rate limit và circuit breaker không chia sẻ được giữa nhiều bản.

## 8. Thực hành (để nhớ lâu)

1. **Nhìn topology thật:** mở http://localhost:15672 (user `erp_rabbit`, mật khẩu `RABBITMQ_PASSWORD` trong `.env`), vào **Exchanges → erp.events → Bindings**, đối chiếu với bảng ở mục 3.3.
2. **Chứng minh outbox** (Bài 2 trong hướng dẫn Adminer): dừng RabbitMQ, tạo báo giá, xem `outbox_events` có dòng `PENDING`, bật lại và thấy chuyển `PUBLISHED`. Chụp ảnh để đưa vào báo cáo.
3. **Chứng minh circuit breaker:** `docker compose stop inventory-service`, lập hoá đơn vài lần, rồi gọi `GET /api/v1/sales/health` xem `circuit_breakers`. Bật lại và quan sát mạch đóng sau tối đa 30 giây.
4. **Xem dead letter:** `.\scripts\replay-dead-letters.ps1` để liệt kê (không `-Replay`).

## 9. Tự kiểm tra
- [ ] Kể 3 kênh giao tiếp và cho mỗi kênh một ví dụ có thật trong code.
- [ ] Giải thích outbox giải bài toán gì, vẽ sơ đồ trong 1 phút.
- [ ] Phân biệt at-least-once và effectively-once, chỉ ra đâu là chỗ làm nên "effectively once".
- [ ] Kể 4 lớp bảo vệ của một lời gọi REST giữa hai service và nói vì sao không retry lệnh ghi Finance.
- [ ] Vẽ lại topology: exchange, queue, retry, DLX, dead letters.
- [ ] Nói 3 điểm yếu của phần giao tiếp mà không nhìn tài liệu.
