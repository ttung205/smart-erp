# 12. Demo chịu lỗi: 4 kịch bản và đọc code từng dòng

> Dùng để ôn và để demo. Ba service chính: **sales**, **catalog**, **inventory** (thêm notification ở chặng thông báo).
> Mọi số dòng dưới đây khớp code ngày 12/11/2026; nếu sửa code thì số dòng lệch, hãy tìm theo tên hàm.
> Những chỗ ghi "chưa chạy thử" là suy ra từ code, cần xác nhận khi chạy thật.

## 0. Bốn kịch bản và điều chúng chứng minh

| # | Tắt gì | Thao tác | Điều chứng minh | Loại giao tiếp |
|---|---|---|---|---|
| 1 | RabbitMQ | Gửi duyệt báo giá | Nghiệp vụ vẫn thành công; thông báo đến **sau** | Bất đồng bộ (outbox) |
| 2 | RabbitMQ | Tạo sản phẩm mới | Sản phẩm tạo được; dòng tồn = 0 đến **sau** | Bất đồng bộ (outbox) |
| 3 | Inventory | Tạo sản phẩm mới | Event nằm chờ trong queue, inventory bật lại thì xử lý | Bất đồng bộ (queue bền) |
| 4 | Inventory | Tạo chứng từ bán | Chứng từ **không** được tạo, báo lỗi rõ ràng, không treo | Đồng bộ (REST + timeout + circuit breaker) |

Ý tưởng xuyên suốt: **một việc đã xảy ra** (đã gửi duyệt, đã tạo sản phẩm) thì đi bằng event và được phép đến trễ; **một việc cần câu trả lời ngay** (giữ hàng cho chứng từ bán) thì đi bằng REST và phải thất bại rõ ràng khi bên kia chết. Đây là quy tắc ở mục 4 của CLAUDE.md.

```
KB 1, 2:  Sales/Catalog --(cùng transaction)--> outbox_events --relay--> RabbitMQ --> Notification / Inventory
                                                        ▲ RabbitMQ chết: dòng nằm lại PENDING/FAILED, relay thử lại

KB 3:     Catalog --> outbox --relay--> RabbitMQ --> queue erp.inventory.events (bền) --> Inventory
                                                        ▲ Inventory chết: message nằm Ready trong queue

KB 4:     Sales --REST (timeout 3s, retry 2, circuit breaker)--> Inventory /stocks/reserve/batch
                                                        ▲ Inventory chết: 503 DEPENDENCY_UNAVAILABLE, rollback chứng từ
```

---

## 1. Chuẩn bị trước buổi demo

### 1.1. Build lại các service đã sửa
```bash
docker compose up -d --build frontend catalog-service sales-service purchase-service inventory-service notification-service ai-service
```
Nếu chỉ demo bốn kịch bản này thì cần: `frontend catalog-service sales-service inventory-service notification-service`.

### 1.2. Container liên quan
| Vai trò | Container |
|---|---|
| RabbitMQ | `erp-rabbitmq` (UI: http://localhost:15672, user `erp_rabbit`) |
| Catalog + relay | `erp-catalog`, `erp-catalog-relay` |
| Sales + relay | `erp-sales`, `erp-sales-relay` |
| Inventory | `erp-inventory` |
| MySQL dev | `erp-mysql` (cổng 3306 trên máy, không phải 3307) |

Kiểm tra relay đang chạy: `docker ps --format "{{.Names}} {{.Status}}" | findstr relay`. **Relay chết thì event không bao giờ được gửi**, kể cả khi RabbitMQ đã sống lại.

### 1.3. Câu lệnh SQL dùng đi dùng lại
Chạy trong terminal (Git Bash). Chỉ đọc, không sửa dữ liệu:
```bash
q() { docker exec erp-mysql sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" --default-character-set=utf8mb4 -e \"$1\" 2>/dev/null"; }

# Outbox của sales và catalog
q "SELECT id,event_type,status,retry_count,LEFT(last_error,60) err,published_at FROM erp_sales_db.outbox_events ORDER BY id DESC LIMIT 5"
q "SELECT id,event_type,status,retry_count,LEFT(last_error,60) err,published_at FROM erp_catalog_db.outbox_events ORDER BY id DESC LIMIT 5"

# Dòng tồn của một sản phẩm (thay 123 bằng id sản phẩm)
q "SELECT warehouse_id,product_id,quantity FROM erp_inventory_db.inventory_stocks WHERE product_id=123"
q "SELECT warehouse_id,product_id,quantity FROM erp_inventory_db.inventory_stocks_reservation WHERE product_id=123"

# Event đã được inventory xử lý
q "SELECT event_id,consumer_name,processed_at FROM erp_inventory_db.processed_events ORDER BY processed_at DESC LIMIT 5"

# Chứng từ bán mới nhất
q "SELECT id,invoice_number,created_at FROM erp_sales_db.invoices ORDER BY id DESC LIMIT 3"
```

### 1.4. Dữ liệu cần có sẵn
- **KB 1:** một báo giá ở trạng thái nháp, tài khoản người gửi là `sale@erp.local` (mã `SE-8103`). Người nhận thông báo: tài khoản có quyền `quotation.approve`, ví dụ `sale.manager@erp.local` (`SE-6187`). Đăng nhập tài khoản này ở **cửa sổ ẩn danh** để xem chuông thông báo cùng lúc.
- **KB 2, 3:** tài khoản có quyền `catalog.create` (ví dụ `admin@erp.local`, `SE-9999`). Chuẩn bị sẵn một brand và designation chưa dùng, ví dụ designation `DEMO100` với brand có tên bắt đầu bằng 3 chữ rõ ràng.
- **KB 4:** một **yêu cầu giao hàng đã duyệt** (`APPROVED` hoặc `PARTIAL`), nếu không `store()` trả `409 INVALID_DELIVERY_STATUS` ngay (InvoiceController.php:134-139). Chuẩn bị sẵn màn hình "Tạo chứng từ bán" đã điền đủ.
- Mật khẩu tài khoản demo: `password123` (DemoUserSeeder).

### 1.5. Hai độ trễ cần biết (tránh đứng hình khi demo)
1. **Relay giãn dần tới 60 giây** khi RabbitMQ lỗi (`MAX_BACKOFF_MS = 60_000`, RelayOutboxEvents.php:21). Sau khi bật lại RabbitMQ có thể phải chờ tới ~1 phút mới thấy event được publish. Mẹo để demo nhanh: `docker restart erp-catalog-relay erp-sales-relay` sau khi RabbitMQ đã lên, relay khởi động lại với nhịp nghỉ ban đầu 1,5 giây.
2. **Notification tự nối lại sau 15 giây** (`setTimeout(..., 15000)` ở consumer.js, hàm `scheduleReconnect`). Thông báo có thể hiện chậm tới ~15 giây sau khi RabbitMQ lên.

Hãy nói trước với người xem: "Hệ thống sẽ tự hồi phục, em chờ trong khoảng một phút" thay vì để họ nghĩ là bị treo.

---

## 2. Kịch bản 1: Tắt RabbitMQ, gửi duyệt báo giá

### 2.1. Các bước
1. Mở RabbitMQ UI (tab Queues) và cửa sổ ẩn danh đăng nhập `SE-6187` để thấy chuông.
2. `docker stop erp-rabbitmq`.
3. Đăng nhập `sale@erp.local`, mở báo giá nháp, bấm **Gửi duyệt**.
4. **Thấy:** báo giá chuyển sang "Chờ duyệt" bình thường. Chuông của `SE-6187` **chưa** có thông báo mới.
5. Chạy SQL outbox của sales: có dòng `QuotationSubmitted`, `status = PENDING` hoặc `FAILED`, `retry_count` tăng dần, `last_error` ghi lỗi kết nối.
6. Xem log relay: `docker logs --tail 20 erp-sales-relay`, thấy dòng "Publish QuotationSubmitted ... thất bại lần N".
7. `docker start erp-rabbitmq`; chờ (xem 1.5; có thể `docker restart erp-sales-relay`).
8. **Thấy:** outbox chuyển `PUBLISHED` (có `published_at`), chuông `SE-6187` có thông báo "Báo giá ... cần phê duyệt".

### 2.2. Vì sao RabbitMQ chết mà gửi duyệt vẫn thành công
Vì request gửi duyệt **không hề nói chuyện với RabbitMQ**. Nó chỉ ghi hai thứ vào MySQL trong một transaction. RabbitMQ chỉ được relay (một tiến trình khác) gọi đến, sau khi dữ liệu đã commit.

### 2.3. Đọc code theo thứ tự thực thi

**(a) Hàm `submitApproval`**, [QuotationController.php:422-442](sales-service/app/Http/Controllers/QuotationController.php#L422-L442)

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 424 | `Quotation::where('id', $id)->orWhere('quotation_number', $id)->first()` | Tìm báo giá theo id hoặc theo số báo giá |
| 425-427 | Không thấy thì trả 404 | Lỗi định dạng envelope; code `NOT_FOUND` |
| 429-431 | `SalesWorkflow::canTransition('quotation', $q->status, 'PENDING_APPROVAL')`; sai thì 409 `INVALID_STATUS_TRANSITION` | Máy trạng thái: chỉ cho chuyển sang chờ duyệt từ trạng thái hợp lệ (ví dụ không gửi duyệt hai lần) |
| 432-435 | `DB::transaction(function () { ... })` | Mọi thứ trong khối này thành công cùng nhau hoặc rollback cùng nhau |
| 433 | `$q->update(['status' => 'PENDING_APPROVAL'])` | Ghi trạng thái nghiệp vụ |
| 434 | `$this->recordEvent('QuotationSubmitted', 'sales.quotation.submitted', $q, $request)` | Ghi event vào outbox **trong cùng transaction** |
| 437-441 | Trả 200 `success: true` | Người dùng nhận kết quả ngay, không đợi ai |

Điểm mấu chốt: dòng 433 và 434 nằm chung transaction. Nếu bước nào lỗi thì cả hai cùng rollback, nên **không bao giờ có** báo giá đã chuyển trạng thái mà thiếu event, hay có event mà báo giá chưa đổi trạng thái.

**(b) Hàm `recordEvent`**, [QuotationController.php:825-837](sales-service/app/Http/Controllers/QuotationController.php#L825-L837)

| Dòng | Nội dung |
|---|---|
| 825 | Chữ ký: loại event, routing key, báo giá, request, dữ liệu thêm `$extra` |
| 827 | `Outbox::record(eventType, routingKey, 'Quotation', $q->id, [...])`: `'Quotation'` là loại aggregate, `$q->id` là id aggregate |
| 828-833 | Payload: id, số báo giá, tên khách, tổng tiền, tiền tệ, `created_by_id`. Đây là **snapshot**: notification không cần gọi ngược sales để lấy tên khách |
| 834 | `actor`: người thực hiện (để notification **loại trừ chính người gửi** khỏi danh sách nhận) |
| 835 | `...$extra`: trường bổ sung (ví dụ lý do từ chối ở event khác) |

**(c) `Outbox::record`**, [Outbox.php:17-40](catalog-service/app/Support/Outbox.php#L17-L40). Sales có file cùng cấu trúc `sales-service/app/Support/Outbox.php`; ví dụ dưới đọc bản catalog.

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 19 | `$eventId = Str::uuid()` | Mã định danh duy nhất của event. Consumer dùng nó để loại trùng |
| 21 | `OutboxEvent::create([...])` | `INSERT INTO outbox_events`. Chạy trong transaction đang mở của người gọi |
| 22-26 | `event_id`, `event_type`, `aggregate_type`, `aggregate_id`, `routing_key` | Cột tra cứu và định tuyến |
| 27-37 | `payload` là JSON của **cả envelope**: `event_id`, `event_type`, `aggregate_*`, `timestamp`, `version`, `correlation_id`, `producer`, `payload` | Envelope chuẩn: consumer đọc được `version` để tolerant reader |
| 32 | `now()->utc()->format('Y-m-d\TH:i:s.v\Z')` | Giờ UTC, hậu tố `Z`, đúng quy tắc mục 0.2 CLAUDE.md |
| 34 | `correlationId()` | Truyền `X-Correlation-ID` của request vào event để truy vết xuyên service |
| 38 | `'status' => OutboxEvent::PENDING` | Trạng thái ban đầu: chờ relay gửi |
| 42-46 | `correlationId()`: lấy từ header, thiếu thì sinh `corr_<uuid>` | Chạy từ console không có request nên phải tự sinh |

**(d) Model `OutboxEvent`**, [OutboxEvent.php:9-13](catalog-service/app/Models/OutboxEvent.php#L9-L13): ba hằng `PENDING`, `PUBLISHED`, `FAILED`. Vòng đời: `PENDING → PUBLISHED`, hoặc `PENDING → FAILED → (thử lại) → PUBLISHED`.

**(e) Relay**, [RelayOutboxEvents.php](catalog-service/app/Console/Commands/RelayOutboxEvents.php). Đọc kỹ phần này vì đây là trái tim của kịch bản 1, 2.

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 21 | `MAX_BACKOFF_MS = 60_000` | Trần thời gian nghỉ khi broker lỗi liên tục: 60 giây |
| 23 | `signature = 'outbox:relay {--once} {--sleep=1500}'` | Lệnh artisan chạy lặp; mặc định nghỉ 1,5 giây giữa các lượt |
| 29-41 | `handle()`: `flock(..., LOCK_EX \| LOCK_NB)` | **Chỉ cho một relay chạy mỗi service.** Hai relay song song sẽ publish trùng và đảo thứ tự. Không lấy được khóa thì thoát |
| 43-52 | `do { relayBatch(); ... usleep(backoffMs(...)) } while (! once)` | Vòng lặp vô hạn: quét một lượt, rồi nghỉ |
| 50 | `usleep(self::backoffMs($sleepMs, $consecutiveBrokerFailures) * 1000)` | Nghỉ càng lâu nếu broker lỗi liên tiếp |
| 59-63 | `backoffMs`: `min(60000, sleep * 2^min(failures,10))` | 1,5s → 3s → 6s → 12s → 24s → 48s → 60s (trần). Đây là **exponential backoff** |
| 66-73 | `isDue`: event `PENDING` thì luôn đến hạn; event `FAILED` chờ `min(60, 2^retry_count)` giây kể từ lần lỗi trước | Mỗi event có nhịp thử lại riêng |
| 77-110 | `relayBatch` | Phần chính, bên dưới |
| 84 | `->limit(50)` | Mỗi lượt xử lý tối đa 50 event, sắp theo `id` (giữ thứ tự tạo) |
| 90 | `$publisher->publish(routing_key, payload, event_id)` | Gửi lên RabbitMQ |
| 92 | `update(['status' => PUBLISHED, 'published_at' => now(), 'last_error' => null])` | **Chỉ đánh dấu đã gửi sau khi publish thành công** |
| 94 | `catch (\Throwable $e)` | Mọi lỗi publish (kết nối chết, timeout, nack) |
| 95 | `markFailed(...)`: `status = FAILED`, `retry_count + 1`, `last_error` | Dòng này bạn thấy trong SQL khi RabbitMQ tắt |
| 98-101 | `UnroutableMessageException` thì `continue` | Lỗi cấu hình riêng của event này (không có queue nào nhận), **không chặn** event phía sau |
| 105 | `break` (lỗi broker) | Dừng cả lượt để giữ thứ tự; lượt sau thử lại từ event lỗi |

Hai câu quan trọng:
- **At-least-once:** nếu relay gửi xong nhưng chết **trước** khi kịp `update(PUBLISHED)` ở dòng 92, event sẽ được gửi lại lần sau, nên consumer có thể nhận trùng. Vì vậy consumer **phải** idempotent (xem kịch bản 3).
- **Không bao giờ bỏ event:** không có chỗ nào xóa hay bỏ qua event vĩnh viễn; chỉ đổi trạng thái và chờ.

**(f) Publisher**, [RabbitMqPublisher.php](catalog-service/app/Messaging/RabbitMqPublisher.php)

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 19-24 | `basic_publish(msg, exchange, routingKey, true)` | Tham số `true` ở cuối là `mandatory`: không queue nào nhận thì RabbitMQ trả lại message |
| 21 | `DELIVERY_MODE_PERSISTENT` | Message ghi xuống đĩa, RabbitMQ restart không mất |
| 25 | `wait_for_pending_acks_returns(5)` | **Chờ RabbitMQ xác nhận (publisher confirm) tối đa 5 giây.** Chỉ khi có xác nhận mới coi là gửi thành công |
| 47-50 | `AMQPStreamConnection(... connection_timeout: 3.0 ...)` | RabbitMQ tắt thì sau 3 giây ném lỗi, relay vào nhánh `catch` ở dòng 94 |
| 52 | `confirm_select()` | Bật chế độ publisher confirm |
| 54-56 | `set_return_listener` ném `UnroutableMessageException` | Message bị trả lại thì coi là lỗi, không để mất âm thầm |
| 57-59 | `set_nack_handler` ném `RuntimeException` | RabbitMQ từ chối cũng là lỗi |

**(g) Notification nhận và quyết định gửi cho ai**
- Rule: [rules.js:92-100](notification-service/src/notifications/rules.js#L92-L100). `recipients: () => ({ permissions: ['quotation.approve'] })` nghĩa là gửi cho **mọi người có quyền duyệt báo giá**, không riêng một line manager. `title`, `body`, `link` dựng từ payload (đây là lý do payload phải có `customer_name`, `total_amount`).
- Xử lý: [notification.service.js:15-79](notification-service/src/notifications/notification.service.js#L15-L79):

| Dòng | Nội dung |
|---|---|
| 16-20 | Thiếu `event_id` hoặc `event_type` thì bỏ qua (message hỏng) |
| 21-23 | `isProcessed(eventId)`: đã xử lý rồi thì trả `DUPLICATE`, không tạo thông báo lần hai |
| 45-49 | Không có rule cho loại event này thì đánh dấu đã xử lý và bỏ qua |
| 52-56 | `resolver.resolve({...recipients, excludeUserIds: [actorId]})`: tìm người nhận, **loại trừ người gửi** |
| 58-69 | Dựng nội dung thông báo từ rule |
| 71-72 | Comment: không dùng transaction; unique index `(event_id, recipient_id)` giữ cho việc ghi idempotent, `markProcessed` đặt cuối cùng |
| 73-75 | `resolveActions` → `insertMany` → `markProcessed` |
| 77 | `pushRealtime`: bắn Socket.IO để chuông cập nhật ngay |

### 2.4. Câu hỏi hội đồng có thể hỏi
- *Nếu relay publish xong, chưa kịp đánh PUBLISHED thì chết?* → Gửi lại lần sau; consumer chống trùng bằng `event_id` (notification qua `isProcessed`, inventory qua `processed_events`).
- *Sao không publish thẳng trong controller?* → Nếu commit DB xong mà RabbitMQ chết thì mất event; nếu publish trước mà DB rollback thì có event "ma". Outbox đảm bảo hai việc đồng thời đúng hoặc đồng thời không xảy ra.
- *Chuông thông báo gửi cho line manager phải không?* → Chính xác là người có quyền `quotation.approve`; trong dữ liệu demo đó là các trưởng phòng.

---

## 3. Kịch bản 2: Tắt RabbitMQ, tạo sản phẩm mới

### 3.1. Các bước
1. Giữ RabbitMQ đang tắt (hoặc tắt lại: `docker stop erp-rabbitmq`).
2. Vào **Danh mục sản phẩm → Thêm mới**: chọn brand, nhập designation `DEMO100`. Ô Internal Code hiện bản xem trước `DEMO100-XXX` (3 chữ đầu brand).
3. Bấm **Tạo sản phẩm**. **Thấy:** tạo thành công, toast "Đã lưu sản phẩm DEMO100-XXX vào Catalog".
4. Vào **Tồn kho**: sản phẩm mới **chưa có dòng tồn** (chưa có dòng nào với product này).
5. SQL: `erp_catalog_db.outbox_events` có dòng `ProductCreated` ở `PENDING/FAILED`; `erp_inventory_db.inventory_stocks` **không có** dòng cho product id này.
6. `docker start erp-rabbitmq`, chờ (hoặc `docker restart erp-catalog-relay`).
7. **Thấy:** outbox `PUBLISHED`; tab Tồn kho xuất hiện dòng `quantity = 0` ở mọi kho đang hoạt động; `processed_events` có thêm một dòng; thông báo realtime "Sản phẩm mới ... đã thêm vào hệ thống".

### 3.2. Đọc code: tạo sản phẩm

**(a) Frontend, xem trước Internal Code**, [form.vue](../../../frontend/src/pages/product/form.vue)
- Hàm `slug` và `skuPreview` (computed): ghép `slug(designation) + '-' + 3 ký tự đầu của slug(brand)`. Chỉ để hiển thị. Ô `<InputText>` có `readonly disabled`, **người dùng không sửa được**.
- Khi gửi, `form.sku` rỗng; mã thật do backend sinh.

**(b) Backend `store`**, [ProductController.php](catalog-service/app/Http/Controllers/ProductController.php), hàm `store` (tìm theo tên hàm):

| Bước | Nội dung | Ý nghĩa |
|---|---|---|
| 1 | Đọc `gross_price`, `description`, `designation` | Dữ liệu đầu vào |
| 2 | Xác định `brandId` (từ `brand_id`, hoặc tạo brand mới theo tên) | Brand là bắt buộc để sinh mã |
| 3 | `if ($designationInput === '' \|\| ! $brandId)` thì trả 422 `VALIDATION_FAILED` | Thiếu một trong hai thì không sinh được mã |
| 4 | Tra tên brand, gọi `generateSku($brandRow->name, $designationInput)` | **Internal Code do server sinh**, bỏ qua `sku` client gửi |
| 5 | `DB::transaction(...)`: `insertGetId` vào `products`, `recordPriceChange(... 'CREATE')`, `ProductEvents::created($id)` | Sản phẩm, lịch sử giá và event cùng một transaction |
| 6 | Trả 201 với `id` và `sku` | |

**(c) `generateSku`** (cùng file, ngay trước `update`):
- `$clean = fn => strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii($s)), '-'))`: bỏ dấu tiếng Việt, mọi ký tự lạ thành `-`, viết hoa.
- `$brandCode = substr(str_replace('-', '', $clean($brand)), 0, 3)`: 3 chữ cái/số đầu của brand.
- `$base = substr($clean($designation), 0, 90) . '-' . $brandCode`: `DESIGNATION-BRD`.
- Vòng `for ($i = 2; exists($sku); $i++)` thêm hậu tố `-2`, `-3` nếu trùng.
- Giới hạn: hai request đồng thời cùng designation và brand có thể cùng thấy mã chưa tồn tại; khi đó UNIQUE INDEX trên `sku` sẽ chặn một request (lỗi 500). Chấp nhận được cho demo.

**(d) Event `ProductCreated`**, [ProductEvents.php:14-28](catalog-service/app/Support/ProductEvents.php#L14-L28)

| Dòng | Nội dung |
|---|---|
| 16-17 | Đọc lại sản phẩm vừa tạo, chỉ chọn cột cần (`first([...])`, không `SELECT *`) |
| 19 | `Outbox::record('ProductCreated', 'catalog.product.created', 'Product', id, [...])` |
| 20-26 | Payload: `product_id`, `sku`, `name`, `origin`, `unit`, `gross_price`, `min_stock`. Đủ để inventory tạo dòng tồn **mà không cần gọi ngược catalog** |

Relay, publisher chạy y hệt kịch bản 1 (mục 2.3 e, f); chỉ khác `routing_key` là `catalog.product.created`.

### 3.3. Vì sao tồn kho "đến sau" mà không hỏng gì
Tạo sản phẩm chưa cần dòng tồn để thành công; dòng tồn = 0 chỉ là dữ liệu dẫn xuất. Khi event đến, inventory tạo dòng bằng 0 rồi mọi thứ nhất quán. Đây là **eventual consistency** (nhất quán cuối cùng).

---

## 4. Kịch bản 3: Tắt Inventory, tạo sản phẩm mới

### 4.1. Các bước
1. Đảm bảo RabbitMQ đang chạy. `docker stop erp-inventory`.
2. Tạo sản phẩm mới (designation khác, ví dụ `DEMO200`). **Thấy:** tạo thành công.
3. RabbitMQ UI → Queues → `erp.inventory.events`: **Ready = 1** (hoặc tăng), **Consumers = 0**. Đây chính là ảnh bạn đã chụp.
4. SQL: outbox catalog `PUBLISHED` (RabbitMQ sống nên relay gửi được), nhưng `inventory_stocks` chưa có dòng cho sản phẩm này.
5. `docker start erp-inventory`.
6. **Thấy:** Consumers = 1, Ready về 0, `Consumer ack` nhảy lên; có dòng tồn = 0; `processed_events` có event mới.
7. Chụp lại UI để so sánh trước/sau.

### 4.2. Điểm khác kịch bản 2
Ở kịch bản 2 event **chưa rời** DB (kẹt ở outbox). Ở kịch bản 3 event **đã rời** DB, đang nằm trong **queue bền của RabbitMQ**. Hai nơi lưu chờ khác nhau, cùng một kết quả: không mất event.

### 4.3. Đọc code: queue và consumer của inventory

**(a) Khai báo queue**, [RabbitMqConnection.cs:55-79](inventory-service/src/Inventory.Infrastructure/Messaging/RabbitMqConnection.cs#L55-L79)

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 40-49 | `ConnectionFactory`: host, port, user, `AutomaticRecoveryEnabled = true`, `NetworkRecoveryInterval = 5s` | Mất kết nối thì thư viện tự nối lại mỗi 5 giây |
| 55-60 | `ExchangeDeclare("erp.events", Topic, durable: true)` | Exchange kiểu topic, bền |
| 63-72 | `QueueDeclare("erp.inventory.events", durable: true, ...)` | **Queue bền**: RabbitMQ restart vẫn còn |
| 70-71 | `x-dead-letter-exchange = erp.dlx`, `x-dead-letter-routing-key = dead.letter.inventory` | Message bị nack sẽ sang DLX |
| 77 | `QueueUnbind(... "sales.order.*")` | Gỡ binding cũ: giữ hàng nay đi bằng REST, không còn đi bằng event |
| 78-79 | `QueueBind(... "purchase.goods.*")`, `QueueBind(... "catalog.product.*")` | Inventory nghe hai nhóm event: nhập kho và sản phẩm |
| 85-97 | `catch`: chỉ cảnh báo một lần, trả `false` | Không crash khi chưa kết nối được |

Vì queue được khai báo bền và có binding **ngay khi inventory khởi động lần đầu**, nên khi inventory tắt, RabbitMQ vẫn route `catalog.product.created` vào queue này. Đây là lý do event "chờ" được.

**(b) Worker tiêu thụ**, [RabbitMqConsumerWorker.cs](inventory-service/src/Inventory.Infrastructure/Messaging/RabbitMqConsumerWorker.cs)

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 37 | `ExecuteAsync`: `BackgroundService`, chạy nền suốt đời service | |
| 41-50 | Vòng `while`: `TryConnect()`, chưa được thì `Task.Delay(20000)` | Chưa có RabbitMQ thì thử lại mỗi 20 giây, service vẫn chạy bình thường |
| 61 | `BasicQos(prefetchCount: 10)` | Mỗi lần nhận tối đa 10 message chưa ack |
| 64 | `consumer.Received += async ...` | Hàm gọi mỗi khi có message |
| 71 | `await ProcessMessageAsync(...)` | Xử lý nghiệp vụ |
| 72 | `BasicAck` | **Chỉ ack sau khi xử lý xong**. Chết giữa chừng thì chưa ack, RabbitMQ giao lại |
| 74-79 | `catch`: `BasicNack(requeue: false)` | Lỗi xử lý thì không đưa lại hàng đợi (tránh lặp vô hạn), đi sang DLX |
| 82-85 | `BasicConsume(queue, autoAck: false, consumer)` | `autoAck: false`: ack thủ công, an toàn hơn |
| 96-135 | `ProcessMessageAsync`: đọc `event_type`, `switch` | Phân loại event |
| 114 | `case "GoodsReceived"` | Nhập kho |
| 122 | `case "ProductCreated"` | Tạo dòng tồn |
| default | Loại event chưa hỗ trợ thì bỏ qua, ghi log debug | Tolerant reader |

**(c) Xử lý `ProductCreated`**, [EventConsumerHandler.cs:100-185](inventory-service/src/Inventory.Infrastructure/Services/EventConsumerHandler.cs#L100-L185)

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 100 | `HandleProductCreatedAsync(envelope)` | |
| 109-118 | `ProcessedEvents.AnyAsync(EventId && ConsumerName)`; có rồi thì `return` | **Idempotency check**: event đã xử lý thì bỏ qua |
| 120-123 | Lấy mọi kho `IsActive` | Tạo dòng tồn cho từng kho đang hoạt động |
| 132 | `foreach (var warehouse in warehouses)` | |
| 135-146 | Chưa có dòng `InventoryStocks` thì `Add(new InventoryStock(... initialQuantity: 0 ...))` | **Tồn thực tế = 0** |
| 149-159 | Chưa có `InventoryStockReservations` thì `Add(... initialQuantity: 0)` | **Tồn khả dụng = 0** (bảng thứ hai theo bất biến tồn kho) |
| 163-168 | `ProcessedEvents.Add(new ProcessedEvent { EventId, ConsumerName, ProcessedAt = UtcNow })` | Ghi dấu đã xử lý |
| 172 | `SaveChangesAsync` | **Một lần lưu duy nhất** cho dòng tồn + dấu đã xử lý (cùng transaction ngầm của EF) |
| 174-182 | `catch (DbUpdateException)`; nếu `WasProcessedConcurrentlyAsync` thì bỏ qua | Hai tiến trình cùng xử lý một event: tiến trình sau bị vi phạm khóa chính `(event_id, consumer_name)` và êm đẹp bỏ qua |

Vì sao **an toàn khi giao lại**: nếu inventory chết sau khi `Add` nhưng trước `SaveChangesAsync` (dòng 172), chưa lưu gì, chưa ack; message được giao lại và xử lý lại từ đầu. Nếu chết sau khi lưu nhưng trước khi ack, message giao lại, dòng 109-118 thấy đã xử lý và bỏ qua.

**Lưu ý mô hình chung:** idempotency check ở dòng 109 và cú `SaveChanges` ở 172 có khe hở (hai tiến trình cùng qua dòng 109). Khe hở được chặn bởi khóa chính của `processed_events` (xử lý ở khối `catch`). Đó là mẫu "kiểm tra rồi chặn bằng ràng buộc DB".

### 4.4. So sánh notification và inventory (câu hỏi ôn)
[consumer.js:81-112](notification-service/src/messaging/consumer.js#L81-L112):

| | Inventory (.NET) | Notification (Node.js) |
|---|---|---|
| Lỗi xử lý | `Nack`, không requeue, sang DLX ngay | Đẩy sang `erp.notification.retry` (có TTL), tối đa `maxRetries` lần rồi mới nack sang DLX |
| Lý do | Lỗi nghiệp vụ tồn kho thường không tự hết | Lỗi tạm thời (ví dụ identity chưa sẵn sàng để tra người nhận) thì thử lại có ý nghĩa |
| Mất kết nối | `AutomaticRecoveryEnabled` + vòng chờ 20s | `scheduleReconnect` sau 15s |

Chi tiết retry của notification (consumer.js):
- Dòng 84-90: message không phải JSON thì `nack(false, false)` ngay vào dead letter (không thử lại vì hỏng vĩnh viễn).
- Dòng 97: `attempt = (header x-retry-count || 0) + 1`.
- Dòng 98-102: `attempt > maxRetries` thì nack vào dead letter.
- Dòng 104-110: còn lượt thì `sendToQueue(retryQueue, ...)` kèm header đếm lần rồi `ack` message gốc. Queue retry có `x-message-ttl` và `x-dead-letter-routing-key = queue chính`, nên **sau TTL message tự quay lại queue chính**.

---

## 5. Kịch bản 4: Tắt Inventory, tạo chứng từ bán

### 5.1. Các bước
1. `docker stop erp-inventory`. RabbitMQ để chạy (không liên quan kịch bản này).
2. Mở màn hình tạo chứng từ bán cho yêu cầu giao hàng đã duyệt; bấm **Tạo**.
3. **Thấy:** báo lỗi "Inventory Service không phản hồi, vui lòng đồng bộ giữ chỗ lại sau." (HTTP 503, mã `DEPENDENCY_UNAVAILABLE`). Không treo màn hình.
4. SQL `invoices`: **không có chứng từ mới**. Số chứng từ đã cấp bị bỏ, lần sau cấp số kế tiếp.
5. Bấm tạo thêm vài lần liên tiếp và để ý thời gian phản hồi: những lần đầu chờ vài giây, sau đó **trả lỗi gần như tức thì** (circuit breaker mở). Xem log: `docker logs erp-sales 2>&1 | findstr CircuitBreaker` thấy "mở mạch 30s sau N lỗi liên tiếp".
6. `docker start erp-inventory`, chờ 30 giây (mạch tự chuyển sang nửa mở và cho thử một request).
7. Tạo lại: thành công, chứng từ được tạo và giữ hàng ("Đã tạo chứng từ ... và giữ hàng tại kho thành công").

### 5.2. Chạy lại mạch tư duy
- Giữ hàng cần **câu trả lời ngay**: người lập chứng từ phải biết đủ hàng hay không. Do đó dùng REST, không dùng event.
- Inventory chết thì **từ chối rõ ràng** (503) thay vì tạo chứng từ rồi hy vọng giữ hàng sau, vì như vậy có thể bán âm.
- Quy tắc CLAUDE.md: "Owner không phản hồi → trả 503 DEPENDENCY_UNAVAILABLE, không âm thầm dùng dữ liệu rỗng".

### 5.3. Đọc code: `store`, [InvoiceController.php:120-314](sales-service/app/Http/Controllers/InvoiceController.php#L120-L314)

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 122-130 | Tìm yêu cầu giao hàng theo id hoặc số | |
| 134-139 | Không có hoặc trạng thái không phải `APPROVED/PARTIAL` thì 409 `INVALID_DELIVERY_STATUS` | Chỉ lập chứng từ cho yêu cầu đã duyệt |
| 143-147 | Lấy người thực hiện, kho (`warehouseId`) | |
| 149-151 | `$invNumber = DocumentNumberGenerator::claimForCustomer('invoice', 'INV', customer_code)` | **Cấp số chứng từ NGOÀI transaction** theo quy tắc `INV-{mã khách}-{ddmmyy}[-A..]`. Comment dòng 149-150: lập thất bại thì số **không bị cấp lại**, để Inventory không nhầm giữ hàng của số này |
| 152-153 | `$reservation = null; $reservationAttempted = false` | Cờ đánh dấu "đã gọi giữ hàng chưa" cho nhánh dọn dẹp |
| 154-155 | `try { DB::transaction(function () {` | Bao quanh toàn bộ việc tạo chứng từ |
| 156-169 | `Invoice::create([...])` | Tạo đầu chứng từ (snapshot: `created_by`, `payment_terms`...) |
| 174-267 | Vòng `foreach ($itemsData)`: đối chiếu dòng với yêu cầu giao hàng và đơn bán, kiểm số lượng, tạo `InvoiceItem` | Có các `abort(422, ...)` (dòng 199, 203, 250) khi dòng không khớp hoặc **vượt số lượng còn phải giao** (`OVER_DELIVERY`) |
| 269 | `$inv->update(['total_amount' => $calculatedTotal])` | Tổng tính lại ở server, không tin số client gửi |
| 271-273 | `$dr->update(['status' => 'INVOICED'])` | Yêu cầu giao hàng chuyển trạng thái (nằm trong transaction nên sẽ rollback nếu giữ hàng lỗi) |
| 286-287 | Comment: giữ hàng ngay khi lập chứng từ; **không giữ được thì rollback, chứng từ không được tạo** | Quy tắc nghiệp vụ trung tâm |
| 288 | `$reservationAttempted = true` | Từ đây trở đi coi như đã thử giữ hàng |
| 289 | `$reservation = $this->reserveStockForInvoice($inv, $warehouseId, $request->bearerToken())` | Gọi Inventory (REST). Xem 5.4 |
| 290-292 | `if (! $reservation['reserved']) throw new StockReservationFailedException($reservation['error'])` | **Ném exception** để `DB::transaction` tự rollback toàn bộ (chứng từ, dòng, trạng thái yêu cầu giao) |
| 293 | `applyReservationResult($inv, $warehouseId, $reservation)` | Ghi kết quả giữ hàng lên chứng từ |
| 297-306 | `catch (\Throwable $e)`: nếu đã thử giữ hàng **và** không phải lỗi chắc chắn thì `releaseStockForInvoice(...)`, rồi `throw $e` | **Dọn dẹp**: kết quả không chắc (timeout/5xx) thì Inventory có thể đã giữ xong, nên nhả hàng của số này. Nhả là idempotent và số không bao giờ cấp lại nên an toàn |
| 300-301 | Comment: nhả cũng lỗi thì `sales:reconcile-reservations` xử lý sau | Lệnh đối soát là lớp bảo vệ cuối (chưa đọc kỹ, chưa dùng trong demo) |
| 308-313 | Trả 201 | |

**Exception thành HTTP 503**, [StockReservationFailedException.php:17-30](sales-service/app/Exceptions/StockReservationFailedException.php#L17-L30): `status()` ánh xạ mã lỗi sang HTTP: `INSUFFICIENT_STOCK` → 409, `PRODUCT_UNRESOLVED` → 422, `DEPENDENCY_UNAVAILABLE` → 503, còn lại 502. `render()` trả JSON `{ success: false, error: {...} }` với đúng status đó.

### 5.4. `reserveStockForInvoice`, [InvoiceController.php:999-1054](sales-service/app/Http/Controllers/InvoiceController.php#L999-L1054)

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 999 | Chữ ký: nhận chứng từ, kho, JWT người dùng | |
| 1002-1013 | Gom các dòng thành `[{sku, warehouse_id, product_id, quantity}]`, tìm `product_id` qua nhiều đường dự phòng | |
| 1015-1020 | Dòng không xác định được sản phẩm/số lượng thì trả `PRODUCT_UNRESOLVED` | Lỗi chắc chắn, không gọi Inventory |
| 1025-1030 | `InternalHttp::inventory($token)->post("{$inventoryUrl}/api/v1/inventory/stocks/reserve/batch", ['order_id' => $inv->invoice_number, 'items' => [...]])` | **Một lần gọi cho cả chứng từ**, `order_id` là số chứng từ; URL lấy từ cấu hình `config('services.inventory.url')` |
| 1030-1036 | `catch (\Throwable)`: log cảnh báo, trả `['reserved' => false, ... 'code' => 'DEPENDENCY_UNAVAILABLE']` | **Inventory tắt rơi vào nhánh này** |
| 1039-1041 | `$response->successful()` thì `reserved = true` | |
| 1043-1049 | Mã khác (ví dụ 409 `INSUFFICIENT_STOCK`): đọc `error` từ response, dựng thông báo "Không đủ hàng... cần X, kho chỉ còn Y" | |
| 1057-1064 | `isDefinitiveReservationFailure`: `INSUFFICIENT_STOCK`, `PRODUCT_UNRESOLVED`, `DOCUMENT_ALREADY_POSTED`, `VALIDATION_FAILED` là lỗi **chắc chắn** | Chắc chắn là Inventory **không giữ**, nên không cần nhả |

Phân loại lỗi này rất đáng nhớ:
- **Chắc chắn** (409 hết hàng): Inventory đã trả lời "không". Không có gì để nhả.
- **Không chắc chắn** (timeout, 5xx, mạch mở): có thể Inventory đã giữ xong rồi mới chết trước khi trả lời. Phải **nhả** hàng của số đó (dòng 302-304).

### 5.5. `InternalHttp`, [InternalHttp.php:21-46](sales-service/app/Infrastructure/Clients/InternalHttp.php#L21-L46)

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 21-24 | `inventory($token, $retry = true)` | Timeout lấy từ `services.inventory.timeout`, mặc định **3 giây** (config/services.php:47) |
| 34 | Có token thì `Http::withToken($token)`, không có (job nền) thì dùng `X-Internal-Service-Key` | Gọi thay người dùng thì chuyển JWT; job dùng khóa nội bộ |
| 35-37 | `acceptJson()->timeout($timeout)->withMiddleware(CircuitBreaker::for($service)->middleware())` | Gắn timeout và circuit breaker |
| 39 | `$request->retry(2, 200, fn ($e) => self::isRetryable($e), throw: false)` | Thử lại **tối đa 2 lần**, cách nhau 200ms, chỉ khi `isRetryable` |
| 43-46 | `isRetryable`: chỉ `ConnectionException` và **không phải** `CircuitOpenException` | Mạch đã mở thì không thử lại, trả lỗi ngay |

### 5.6. `CircuitBreaker`, [CircuitBreaker.php](sales-service/app/Infrastructure/Clients/CircuitBreaker.php)

Ba trạng thái:
```
CLOSED --(5 lỗi liên tiếp)--> OPEN --(30 giây)--> HALF_OPEN --(1 request thử thành công)--> CLOSED
                                                       └--(thử lỗi)--> OPEN lại 30 giây
```

| Dòng | Nội dung | Ý nghĩa |
|---|---|---|
| 25, 27 | `FAILURE_THRESHOLD = 5`, `OPEN_SECONDS = 30` | Hai tham số cần nhớ |
| 43-69 | `middleware()`: bọc mỗi request | |
| 46 | `beforeCall()` | Trước khi gọi: mạch mở thì ném `CircuitOpenException` ngay, **không gọi mạng** |
| 58 | `status >= 500 ? recordFailure() : recordSuccess()` | **5xx tính là lỗi; 4xx (kể cả 409 hết hàng) không tính**, vì 4xx là câu trả lời hợp lệ của một service đang sống |
| 62-65 | Lỗi mạng (reject) thì `recordFailure()` | |
| 71-79 | `state()`: không có `opened_until` thì CLOSED; chưa hết hạn thì OPEN; hết hạn thì HALF_OPEN | Trạng thái lưu trong Laravel Cache nên dùng chung giữa các request |
| 81-91 | `beforeCall`: OPEN thì ném; HALF_OPEN thì `Cache::add('probe')`, chỉ **một** request giành được quyền thử, các request khác bị từ chối | Tránh dội lại cả đàn request vào service vừa hồi phục |
| 93-101 | `recordSuccess`: xóa đếm lỗi, xóa `opened_until`, xóa `probe` | Đóng mạch |
| 103-114 | `recordFailure`: đếm lỗi; `if ($halfOpen \|\| $failures >= 5)` thì `opened_until = now + 30`, log "mở mạch 30s" | Đủ 5 lỗi hoặc nửa mở mà lỗi thì mở (lại) mạch |

**Một chi tiết cần xác nhận khi chạy thật (chưa chạy thử):** `retry(2, ...)` chạy lại request qua cùng chuỗi middleware, nên **mỗi lần thử lại cũng tính một lỗi**. Một lần tạo chứng từ lỗi có thể đếm 3 lỗi (1 lần gọi giữ hàng + 2 lần thử lại), và sau đó `releaseStockForInvoice` (dòng 303) cũng gọi Inventory, có thể thêm vài lỗi nữa. Vì vậy mạch **có thể mở ngay sau 1 đến 2 thao tác** chứ không phải sau 5 thao tác riêng lẻ. Hãy chạy thử để biết con số thật rồi chỉnh lời thuyết trình ("sau vài lần lỗi liên tiếp" là câu an toàn).

### 5.7. Câu hỏi hội đồng có thể hỏi
- *Vì sao không tạo chứng từ rồi giữ hàng sau khi Inventory sống lại?* → Dễ bán âm: chứng từ tồn tại mà chưa chắc còn hàng. Mục 0.4 CLAUDE.md cấm bán âm; giữ hàng là điều kiện để tạo chứng từ.
- *Mạch mở thì người dùng thấy gì?* → Vẫn thấy 503 `DEPENDENCY_UNAVAILABLE` nhưng **tức thì**, không phải chờ timeout 3 giây × 3 lần.
- *Timeout 3 giây có hợp lý không?* → CLAUDE.md quy định 2–5 giây; giữ hàng một lô trong một transaction thường rất nhanh.
- *Tại sao retry chỉ cho POST giữ hàng?* → Vì Inventory idempotent theo (số chứng từ, kho, sản phẩm): gọi lại cùng số và cùng số lượng thì an toàn; cùng số khác số lượng trả `422 DOCUMENT_ALREADY_POSTED`.

---

## 6. Cách đọc code hiệu quả (lộ trình 90 phút)

| Chặng | Nội dung | File | Thời gian |
|---|---|---|---|
| 1 | Ghi event cùng transaction | `QuotationController.php:422-442, 825-837`, `Outbox.php`, `ProductEvents.php`, `ProductController::store` | 20 phút |
| 2 | Relay và publisher | `RelayOutboxEvents.php`, `RabbitMqPublisher.php` | 20 phút |
| 3 | Queue và consumer inventory | `RabbitMqConnection.cs:55-79`, `RabbitMqConsumerWorker.cs`, `EventConsumerHandler.cs:100-185` | 20 phút |
| 4 | Consumer notification | `consumer.js`, `notification.service.js`, `rules.js:92-100` | 15 phút |
| 5 | REST đồng bộ | `InvoiceController.php:120-314, 999-1089`, `InternalHttp.php`, `CircuitBreaker.php`, `StockReservationFailedException.php` | 25 phút |

Mẹo:
- Đọc **theo thứ tự thực thi** của một request, không đọc theo thứ tự file.
- Với mỗi hàm, tự hỏi ba điều: (1) nếu bước này lỗi giữa chừng thì trạng thái dữ liệu thế nào? (2) có chạy lại được không (idempotent)? (3) ai biết là có lỗi?
- Chưa cần đọc: phần Swagger `#[OA\...]` ở đầu controller.

## 7. Bảng tra nhanh: lỗi nào, cơ chế nào

| Lỗi | Cơ chế | Nơi đọc |
|---|---|---|
| RabbitMQ tắt khi ghi nghiệp vụ | Outbox: ghi DB cùng transaction | Outbox.php, QuotationController:432-435 |
| RabbitMQ tắt khi relay publish | `FAILED` + backoff 1,5s→60s, giữ thứ tự | RelayOutboxEvents:43-105 |
| Publish thành công nhưng chưa đánh dấu | Gửi lại; consumer loại trùng | RelayOutboxEvents:90-92; EventConsumerHandler:109-118 |
| Không queue nào nhận | `mandatory` + `UnroutableMessageException`, không chặn event sau | RabbitMqPublisher:19-24, 54-56 |
| Inventory tắt (event) | Queue bền + binding, message `Ready` | RabbitMqConnection:63-79 |
| Inventory chết giữa lúc xử lý | Ack thủ công sau xử lý, `processed_events` | RabbitMqConsumerWorker:71-72; EventConsumerHandler:172 |
| Message lỗi vĩnh viễn | Nack không requeue sang DLX `erp.dlx` | RabbitMqConsumerWorker:74-79 |
| Notification lỗi tạm | Retry queue TTL, tối đa N lần, rồi DLX | consumer.js:97-110 |
| Mất kết nối RabbitMQ | Tự nối lại (inventory 5s/20s, notification 15s) | RabbitMqConnection:47-48; consumer.js:56 |
| Inventory tắt (REST) | Timeout 3s, retry 2, 503, rollback chứng từ | InternalHttp:39; InvoiceController:1030-1036, 290-292 |
| Inventory tắt liên tục | Circuit breaker mở 30s | CircuitBreaker:25-27, 103-114 |
| Kết quả giữ hàng không chắc | Nhả hàng theo số chứng từ (idempotent) | InvoiceController:297-306 |

## 8. Việc nên làm trước khi demo thật
1. Chạy cả bốn kịch bản **một lần** để ghi lại thời gian chờ thật (đặc biệt độ trễ sau khi bật RabbitMQ, mục 1.5) và số lần lỗi làm mạch mở (mục 5.6).
2. Chụp ảnh RabbitMQ UI trước và sau ở kịch bản 3.
3. Chuẩn bị hai tab SQL (outbox sales/catalog, và tồn kho) mở sẵn.
4. Quay video dự phòng nếu mạng hoặc Docker chậm.
