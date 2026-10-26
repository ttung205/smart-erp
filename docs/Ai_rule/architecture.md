# ARCHITECTURE GUIDELINES — Smart Distributed ERP

> **Mục tiêu**: Quy định kiến trúc đích (target architecture) cho toàn bộ microservices: ranh giới service, quyền sở hữu dữ liệu, cách các service giao tiếp (REST hay Queue), cách chia sẻ dữ liệu qua ranh giới, và các pattern phân tán bắt buộc.
>
> Tài liệu này là **chuẩn**. Code hiện tại lệch chuẩn ở đâu được liệt kê tại [§13 Hiện trạng & lộ trình](#13-hiện-trạng-lệch-chuẩn--lộ-trình-khắc-phục). Code mới không được tạo thêm vi phạm.

---

## 1. NGUYÊN TẮC NỀN TẢNG

1. **Một service = một bounded context = một database.** Service là chủ sở hữu (owner) duy nhất của dữ liệu của nó. Không service nào khác được đọc/ghi DB đó — kể cả "chỉ đọc", kể cả "tạm thời".
2. **Giao tiếp chỉ qua hợp đồng công khai**: REST API (đồng bộ) hoặc Event/Message (bất đồng bộ). Hợp đồng được mô tả trong [api-spec.md](../contracts/api-spec.md) và [events-spec.md](../contracts/events-spec.md).
3. **Tự chủ khi chạy (runtime autonomy)**: một service sập không được kéo theo dây chuyền. Ưu tiên thiết kế sao cho nghiệp vụ cốt lõi của service vẫn chạy khi service khác tạm ngừng.
4. **Triển khai độc lập**: đổi schema nội bộ của một service không bắt service khác phải deploy lại.
5. **KISS trước, phân tán sau**: chỉ thêm queue, replica, saga khi có nhu cầu thật (có consumer thật, có bài toán thật). Không dựng hạ tầng "để sau dùng".

---

## 2. BẢN ĐỒ MICROSERVICES & BOUNDED CONTEXTS

| Service | Công nghệ | Port | Sở hữu dữ liệu (Owner of) |
|---|---|---|---|
| `identity-service` | PHP Laravel | 8001 | Users, Departments, Roles, Permissions, cấp JWT |
| `catalog-service` | PHP Laravel | 8002 | **Chỉ sản phẩm**: Products, Brands, Categories, Product price history |
| `partner-service` *(kế hoạch)* | PHP Laravel | 8011 | Đối tác kinh doanh: Customers, Suppliers, Contacts, hạn mức tín dụng |
| `purchase-service` | C# .NET 10 | 8003 | Hỏi hàng, Purchase Requisition, PO, Goods Receipt, Purchase Invoice/Return |
| `inventory-service` | C# .NET 10 | 8004 | Warehouses, Stocks (On-Hand/Reserved/Available), Stock movements |
| `sales-service` | PHP Laravel | 8005 | Quotation, Sales Order, Delivery Request, Dispatch, Sales Invoice/Return, VAT invoice |
| `notification-service` | Node.js | 8006 | Thông báo in-app, hàng đợi email, email log |
| `eoffice-service` | PHP Laravel | 8007 | Đơn nghỉ phép, đề nghị mua sắm/thanh toán, luồng duyệt nội bộ |
| `finance-service` | PHP Laravel | 8008 | Phiếu thu/chi, sổ quỹ, công nợ, sổ cái |
| `chat-service` | C# .NET 10 | 8009 | Hội thoại 1-1/nhóm, tin nhắn |
| `ai-service` | Python FastAPI | 8010 | Kho phân tích (analytics warehouse), hội thoại trợ lý AI |

### 2.1. Vì sao tách `partner-service` khỏi catalog
* Sản phẩm và đối tác là hai vòng đời khác nhau, hai nhóm người dùng khác nhau (phòng hàng hoá vs. kinh doanh/mua hàng/kế toán), hai bộ quyền khác nhau.
* Customer và Supplier **không** chia cho sales/purchase vì finance cần cả hai (công nợ phải thu/phải trả), và một doanh nghiệp có thể vừa là khách vừa là NCC → gom vào một **Business Partner master** (giống mô hình Business Partner của SAP).

### 2.2. Ranh giới — câu hỏi kiểm tra
Khi phân vân một bảng/logic thuộc service nào, hỏi: *"Ai được quyền **thay đổi** dữ liệu này và chịu trách nhiệm đúng/sai của nó?"* — service đó là owner. Service khác chỉ được **tham chiếu bằng ID** và **chụp lại (snapshot)** khi cần.

---

## 3. CLEAN ARCHITECTURE BÊN TRONG MỖI SERVICE

```text
Presentation (Controllers, Middleware, Request/Response DTO)
        │ phụ thuộc
        ▼
Application (Use cases / Command-Query handlers, Interfaces: IRepository, ICatalogClient, IEventPublisher)
        │ phụ thuộc
        ▼
Domain (Entities, Value Objects, Domain Events, quy tắc bất biến)   ◄── không phụ thuộc gì
        ▲
        │ triển khai interface
Infrastructure (EF Core / Eloquent, RabbitMQ, HTTP clients gọi service khác, Outbox relay)
```

* **Domain** không import framework, ORM, HTTP, RabbitMQ.
* **Application** điều phối: nhận DTO → gọi Domain → lưu qua repository → ghi outbox → trả DTO.
* **Infrastructure** là nơi DUY NHẤT biết URL/connection của service khác. Lời gọi sang service khác luôn đi qua một interface (`ICatalogClient`, `IdentityClient`…), không gọi `HttpClient`/`Http::` thẳng trong controller.
* **Presentation** mỏng: validate format, gọi use case, bọc API Envelope.

**Áp dụng theo ngôn ngữ:**
* .NET: 4 project `*.Api / *.Application / *.Domain / *.Infrastructure` (đã áp dụng ở inventory, purchase, chat).
* Laravel: không bắt buộc 4 project, nhưng bắt buộc tối thiểu `app/Http` (presentation) → `app/Services` hoặc `app/Actions` (application) → `app/Domain` (logic thuần) → `app/Infrastructure` (`Clients/`, `Messaging/`, repositories). **Controller không chứa logic nghiệp vụ.**

---

## 4. CHỌN CÁCH GIAO TIẾP: REST ĐỒNG BỘ HAY QUEUE BẤT ĐỒNG BỘ

### 4.1. Quy tắc quyết định

Hỏi theo thứ tự:

1. **Bên gọi có cần câu trả lời ngay để làm tiếp không?** (validate, lấy dữ liệu để tính toán, người dùng đang chờ kết quả) → **REST**.
2. **Đây là thông báo "việc X đã xảy ra" để service khác tự phản ứng?** Bên phát không cần biết ai nghe, không cần chờ → **Event qua queue**.
3. **Việc nặng/chậm/dễ lỗi tạm thời** (gửi email, sinh PDF, ETL, gọi API bên ngoài) mà người dùng không cần chờ → **Message qua queue** (command message, 1 consumer, có retry).
4. **Luồng nghiệp vụ kéo dài qua nhiều service** cần đảm bảo cuối cùng hoàn tất kể cả khi một service tạm sập → **Event + Saga**.
5. Còn lại → **REST**. REST là mặc định vì đơn giản, dễ debug.

### 4.2. Bảng so sánh

| Tiêu chí | REST đồng bộ | Queue bất đồng bộ |
|---|---|---|
| Bên gọi chờ kết quả | Có | Không |
| Bên nhận phải đang sống | **Có** — sập là lỗi ngay | Không — message nằm chờ trong queue |
| Số bên nhận | 1, bên gọi biết rõ là ai | 0..N, bên phát **không** biết |
| Độ nhất quán | Tức thời | Cuối cùng (eventual) — trễ ms → phút |
| Độ phức tạp | Thấp | Cao: outbox, idempotency, retry, DLQ, thứ tự |
| Debug | Dễ | Khó hơn — cần correlation_id, log |

### 4.3. Ví dụ áp dụng trong dự án

| Tình huống | Chọn | Lý do |
|---|---|---|
| Sales tạo báo giá cần tên/giá/đơn vị sản phẩm | **REST** → catalog (`GET /products?ids=`) | Cần dữ liệu ngay để snapshot vào báo giá |
| Tạo chứng từ bán hàng → giữ hàng | **REST** → inventory (`POST /stocks/reserve/batch`) | Người lập chứng từ cần biết ngay đủ hàng hay không; Inventory giữ cả lô nguyên tử + idempotent (§8) |
| Đơn được duyệt → báo cho người tạo | **Event** → notification | Bên duyệt không cần chờ thông báo gửi xong |
| Gửi email hỏi giá NCC | **Command message** `erp.email.send` | Chậm, lỗi SMTP tạm thời cần retry |
| Purchase nhập kho xong → tăng tồn | **Event** `GoodsReceived` → inventory | Inventory tự cập nhật; purchase không phụ thuộc inventory đang sống |
| Màn hình chi tiết đơn hiển thị tồn kho hiện tại | **REST** (frontend/BFF gọi inventory) | Chỉ đọc, cần số liệu mới nhất |
| Chat cần danh sách user | **REST** → identity (có cache ngắn) | Tra cứu, không có nghiệp vụ phản ứng |
| User bị khoá → gỡ khỏi nhóm chat, chuyển người duyệt | **Event** `UserDeactivated` | Nhiều service cần tự phản ứng, identity không nên biết họ |

### 4.4. Anti-pattern bị cấm
* Dùng queue để làm request/response trong khi người dùng đang chờ (giả lập RPC qua RabbitMQ).
* Chuỗi REST đồng bộ dài > 2 bước (A → B → C → D): độ sẵn sàng nhân với nhau, độ trễ cộng dồn.
* Gọi REST trong vòng lặp từng phần tử (N+1). Phải có endpoint batch: `GET /products?ids=1,2,3`.
* Phát event "phòng khi có ai cần". **Chỉ thêm event khi có consumer thật.**
* Dùng event để validate (ví dụ phát `ValidateCustomer` rồi chờ phản hồi).

---

## 5. CHIA SẺ DỮ LIỆU QUA RANH GIỚI SERVICE

Service B cần dữ liệu thuộc service A. Có 4 cách, **chọn theo thứ tự ưu tiên**:

### 5.1. Snapshot vào chứng từ — MẶC ĐỊNH cho dữ liệu giao dịch
* Lúc tạo chứng từ, gọi REST lấy dữ liệu và **lưu cứng** vào bảng của mình: `sku`, `product_name`, `unit`, `brand`, `gross_price`, `customer_code`, `customer_name`, `tax_code`, `created_by_name`…
* Hiển thị, in, báo cáo đều đọc từ snapshot. **Không join ngược về nguồn.**
* Đây là yêu cầu nghiệp vụ, không chỉ kỹ thuật: hoá đơn đã xuất phải giữ nguyên tên/giá lúc xuất dù catalog đổi sau đó.

### 5.2. Gọi REST lúc thực thi lệnh (command-time lookup)
* Dùng khi cần **giá trị đúng nhất tại thời điểm** để validate hoặc snapshot (giá hiện hành, khách còn hoạt động, hạn mức tín dụng).
* Bắt buộc: timeout, batch endpoint, lỗi rõ ràng (`DEPENDENCY_UNAVAILABLE` 503) nếu owner sập. Không âm thầm dùng dữ liệu cũ.

### 5.3. API Composition — cho màn hình cần dữ liệu "sống"
* Frontend (hoặc một BFF) gọi nhiều service rồi ghép lại để hiển thị. Ví dụ: chi tiết đơn (sales) + tồn kho hiện tại (inventory).
* Backend không tự đi gom dữ liệu chỉ để trả về cho UI.

### 5.4. Local Read Model (replica) đồng bộ bằng event — chỉ khi thật cần
Chỉ dùng khi **ít nhất một** điều kiện sau đúng:
* Cần **lọc/sắp xếp/join/báo cáo** theo thuộc tính *hiện tại* của dữ liệu service khác, với khối lượng lớn (ví dụ: báo cáo doanh số theo nhóm hàng hiện tại của 100k dòng).
* Tần suất đọc rất cao, REST sẽ thành nút cổ chai.
* Nghiệp vụ **bắt buộc chạy được khi owner sập**.

Quy tắc khi dùng:
* Bảng prefix `ref_` (ví dụ `ref_products`), **chỉ chứa cột cần dùng**, giữ nguyên ID gốc, model chặn ghi.
* Đồng bộ bằng event mang đầy đủ trạng thái (*event-carried state transfer*), consumer idempotent, bỏ qua event cũ hơn bản đang có (so `occurred_at`/`aggregate_version`).
* Có lệnh backfill (`sync-reference-data`) gọi API owner để dựng lại từ đầu.
* Chấp nhận dữ liệu trễ (eventual consistency) — không dùng replica cho validate quan trọng.

### 5.5. Cấm tuyệt đối
* Connection string tới DB của service khác (kể cả read-only, kể cả SQLite file path sang thư mục service khác).
* Foreign key / JOIN chéo database.
* Bảng dùng chung giữa hai service.

---

## 6. QUY CHUẨN REST NỘI BỘ (SERVICE-TO-SERVICE)

* **URL từ cấu hình** (`*_SERVICE_URL`), không hardcode `localhost:800x` trong code. Trong Docker dùng DNS tên service (`http://catalog-service:8002`).
* **Timeout bắt buộc**: 2–5s. Không có lời gọi nào được chờ vô hạn.
* **Retry** chỉ cho request idempotent (GET, hoặc POST có `Idempotency-Key`), tối đa 2 lần, exponential backoff + jitter.
* **Circuit breaker** cho lời gọi tần suất cao (.NET: `Microsoft.Extensions.Http.Resilience`; PHP: có thể bỏ qua giai đoạn đầu, nhưng phải có timeout).
* **Truyền ngữ cảnh**: luôn chuyển tiếp `X-Correlation-ID`. Gọi thay mặt người dùng → chuyển tiếp JWT người dùng. Gọi nội bộ hệ thống (job, consumer) → service token (xem §9).
* **Endpoint nội bộ** đặt dưới `/internal/...`, gateway **không** expose ra ngoài.
* **Bọc trong client riêng** ở Infrastructure (`CatalogClient`, `IdentityClient`) trả về DTO đã typed — không parse JSON rải rác trong controller.

---

## 7. KIẾN TRÚC HƯỚNG SỰ KIỆN (EVENT-DRIVEN)

### 7.1. Ba loại message
| Loại | Ý nghĩa | Đặt tên | Số consumer | Ví dụ |
|---|---|---|---|---|
| **Domain event** | "Việc X đã xảy ra" | Quá khứ, PascalCase | 0..N | `SalesOrderApproved`, `GoodsReceived` |
| **State-transfer event** | Domain event mang đủ trạng thái để consumer dựng replica | `<Aggregate>Created/Updated/Deleted` | 0..N | `ProductUpdated` (chỉ khi có replica) |
| **Command message** | "Hãy làm Y" giao cho đúng 1 worker | Mệnh lệnh | 1 | `erp.email.send` |

### 7.2. Topology RabbitMQ
* Một topic exchange `erp.events` cho mọi domain event; DLX `erp.dlx`.
* Routing key: `<service>.<aggregate>.<event>` — ví dụ `sales.order.approved`, `catalog.product.updated`, `identity.user.deactivated`.
* **Mỗi service consumer có queue riêng** `erp.<consumer>.events`, bind các routing key nó quan tâm. Producer không biết queue nào tồn tại.
* Message lỗi: retry có giới hạn (queue retry + TTL) → hết lượt vào DLQ, có cảnh báo.

### 7.3. Transactional Outbox — bắt buộc khi event sinh ra từ thay đổi DB
* Ghi dữ liệu nghiệp vụ + bản ghi `outbox_events` **trong cùng một transaction**.
* Relay worker đọc `PENDING` → publish (publisher confirm) → đánh dấu `PUBLISHED`.
* **Cấm publish thẳng lên RabbitMQ trong controller/service sau khi commit** (dual-write: DB commit được mà publish lỗi → mất event, hoặc ngược lại).

### 7.4. Idempotent Consumer — bắt buộc
* RabbitMQ đảm bảo *at-least-once* → message có thể đến 2 lần.
* Consumer ghi `(event_id, consumer_name)` vào `processed_events` **cùng transaction** với xử lý; trùng thì bỏ qua và ack.
* Không giả định thứ tự: với replica, so `occurred_at`/version, bỏ qua event cũ.

### 7.5. Envelope & versioning
* Envelope chuẩn tại [events-spec.md §1](../contracts/events-spec.md).
* Thay đổi **thêm trường** → giữ `version`. Consumer phải là *tolerant reader* (bỏ qua trường lạ).
* Thay đổi **phá vỡ** (đổi tên/xoá/đổi kiểu) → tăng `version`, phát song song bản cũ và mới cho tới khi mọi consumer chuyển xong.

---

## 8. LUỒNG BÁN HÀNG & GIỮ KHO

### 8.1. Nghiệp vụ
```text
Báo giá → Đơn bán hàng (DRAFT → PENDING_APPROVAL → APPROVED)      chưa giữ hàng
   → Yêu cầu giao hàng (chọn kho bán, duyệt)
   → Chứng từ bán hàng (Invoice)  ── GIỮ HÀNG: trừ tồn khả dụng
   → Điều phối kho (soạn / đóng gói / giao)
   → Kho ghi sổ xuất            ── XUẤT KHO: trừ tồn thực tế
   → Hạch toán, hóa đơn VAT, công nợ
```
* **Giữ hàng tại thời điểm tạo chứng từ bán hàng**, không phải lúc duyệt đơn: hàng chỉ bị khóa khi sắp xuất, tránh "chết" hàng cho đơn chưa giao.
* Inventory có 2 số dư: `inventory_stocks` (tồn thực tế) và `inventory_stocks_reservation` (tồn khả dụng). Giữ hàng trừ khả dụng; xuất kho trừ thực tế. Mỗi biến động ghi sổ cái tương ứng.

### 8.2. Vì sao REST đồng bộ, không phải saga qua event
Người lập chứng từ cần biết **ngay** đủ hàng hay không (§4.1 bước 1). Saga bất đồng bộ chỉ đáng khi bên gọi không cần chờ kết quả hoặc luồng kéo dài qua nhiều service.

### 8.3. Hợp đồng giữ hàng
* Sales gọi **một lần** `POST /api/v1/inventory/stocks/reserve/batch` cho cả chứng từ, `order_id` = số chứng từ.
* Inventory giữ **cả lô trong một transaction**: thiếu một dòng → rollback, trả `409 INSUFFICIENT_STOCK`. Dòng trùng sản phẩm được gộp; khóa theo thứ tự (kho, sản phẩm) để tránh deadlock.
* **Idempotent** theo (số chứng từ, kho, sản phẩm): gọi lại không giữ hai lần, dòng đã giữ được bỏ qua. Cùng chứng từ mà khác số lượng → `422 DOCUMENT_ALREADY_POSTED`.
* **Không giữ được hàng thì không có chứng từ**: sales gọi giữ hàng bên trong transaction lập chứng từ; thiếu hàng (409) hoặc Inventory sập (503) → rollback, yêu cầu giao vẫn ở `APPROVED` để lập lại. Ví dụ kho còn 3, hai yêu cầu giao mỗi cái 2: chứng từ đầu giữ 2 (còn 1), chứng từ thứ hai bị từ chối. Nếu đã giữ hàng mà lưu chứng từ lỗi → sales nhả lại (bù trừ).
* Chứng từ cũ ở trạng thái `FAILED` (lập trước khi có quy tắc trên) → **Đồng bộ giữ chỗ** (`POST /invoices/{id}/sync-reserve`), an toàn vì idempotent.
* Kho chỉ được ghi sổ xuất khi chứng từ đã `RESERVED`; xuất kho gọi `POST /stocks/issue` từng dòng, idempotent theo `{số chứng từ}:{id dòng}`.

### 8.4. Hủy chứng từ bán hàng
`POST /invoices/{id}/cancel` (quyền `invoice.manage`, bắt buộc lý do), chỉ khi **chưa xuất kho / chưa ghi sổ**:
1. Nhả toàn bộ hàng đã giữ: `POST /stocks/release/document` với số chứng từ — Inventory cộng lại đúng số đã giữ theo sổ cái, idempotent. Inventory lỗi → chưa đổi gì, hủy lại được.
2. Chứng từ → `warehouse_status = CANCELLED`, `inventory_reservation_status = RELEASED`, lưu người hủy/lý do; lệnh kho (warehouse dispatch) → `CANCELLED`.
3. Phần còn phải giao của yêu cầu giao về 0 (`quantity = delivered_quantity`), yêu cầu giao → `CANCELLED` (hoặc `DELIVERED` nếu đã giao một phần trước đó) → số lượng chưa giao của đơn bán tăng lại, lập yêu cầu giao mới được.
4. Chứng từ đã hủy không được gửi kho, điều phối, ghi sổ hay đồng bộ giữ chỗ (409 `INVOICE_CANCELLED`). Chứng từ đã xuất kho không hủy được (409 `INVOICE_ALREADY_ISSUED`) — dùng trả hàng.

---

## 9. BẢO MẬT

* **Xác thực người dùng**: identity-service cấp JWT. Mục tiêu: **RS256** — identity giữ private key, các service chỉ cần public key (JWKS). Không dùng chung một secret HS256 cho mọi service (service nào giữ secret cũng giả mạo được token).
* **Gateway** là cửa duy nhất từ ngoài vào; endpoint `/internal/*` không đi qua gateway.
* **Gọi nội bộ không có người dùng** (consumer, job): service token riêng cho từng service (client-credentials), không dùng chung một `X-Internal-Service-Key` cho toàn hệ thống.
* **Phân quyền** kiểm tra tại service owner theo permission trong JWT; frontend ẩn nút chỉ là UX.

---

## 10. KIỂM SOÁT ĐỒNG THỜI

### 10.1. Zero Overselling — Atomic SQL Guard
Cấm đọc tồn lên bộ nhớ, trừ, rồi ghi lại (hai thao tác đồng thời sẽ ghi đè nhau). Mọi biến động số dư dùng câu lệnh nguyên tử có điều kiện — ví dụ giữ hàng trên bảng tồn khả dụng:
```sql
UPDATE inventory_stocks_reservation
SET quantity = quantity - :qty, updated_at = :now
WHERE warehouse_id = :warehouse_id AND product_id = :product_id
  AND quantity >= :qty;
```
Số dòng ảnh hưởng = 0 → `409 INSUFFICIENT_STOCK`. Xuất kho áp dụng y hệt trên `inventory_stocks`. Cài đặt: `InventoryStockManager` (EF Core `ExecuteUpdateAsync`), kiểm chứng bởi test 20 luồng đồng thời.

### 10.2. Optimistic Concurrency
Bảng chứng từ có cột `version`. Cập nhật `WHERE id = :id AND version = :v`; 0 dòng → `409 CONCURRENT_CONFLICT`.

---

## 11. AI SERVICE — PERMISSION-AWARE & NGUỒN DỮ LIỆU

* AI không có quyền tối cao: đọc JWT người hỏi, giới hạn tool theo `roles/permissions` (sales chỉ xem đơn của mình, kho chỉ xem kho, BOD mới xem tài chính).
* **Nguồn dữ liệu cho analytics warehouse**: đích là consume event (queue `erp.analytics.events` đã bind `#`) hoặc gọi export API của từng service. Đọc thẳng schema DB của service khác chỉ được chấp nhận cho demo (`AI_WAREHOUSE_SYNC_ENABLED=true`, mặc định tắt) và phải tắt ở production.

---

## 12. VẬN HÀNH & QUAN SÁT

* Mỗi service: `GET /health` (liveness) và kiểm tra DB/RabbitMQ (readiness).
* Log có cấu trúc (JSON) kèm `correlation_id`, `service`, `user_id`.
* Mỗi service có `Dockerfile` riêng; `docker-compose.yml` chạy được toàn hệ thống. Cấu hình qua biến môi trường, không có đường dẫn file trỏ sang thư mục service khác.
* Không commit file DB (`*.db`, `*.sqlite`, `erp_*_db`), `.venv`, `bin/obj`, `vendor`, `node_modules`.

---

## 13. HIỆN TRẠNG LỆCH CHUẨN & LỘ TRÌNH KHẮC PHỤC

Cập nhật: 2026-10-20. Đánh dấu ✅ khi xong.

| # | Mức | Vấn đề | Hướng xử lý |
|---|---|---|---|
| 1 | ✅ | ~~`sales-service` có connection `catalog`/`identity` đọc thẳng DB~~ | Đã xong 2026-10-20: snapshot vào chứng từ + `CatalogClient`/`PartnerClient`/`IdentityClient` (`app/Infrastructure/Clients`), dữ liệu cũ điền bằng `php artisan sales:backfill-reference-snapshots` |
| 2 | 🔴 | `ai-service` ETL đọc thẳng schema MySQL của mọi service (chuyển từ file SQLite 2026-10-26; mặc định tắt) | Giữ cho demo; lộ trình chuyển sang consume event/export API (§11) |
| 3 | ✅ | ~~Giữ hàng từng dòng, không nguyên tử; đọc-rồi-ghi gây mất cập nhật khi đồng thời; thiếu hàng trả 500; saga `SalesOrderCreated` là code chết~~ | Đã xong 2026-10-20: sales gọi `reserve/batch` một lần/chứng từ; Inventory giữ cả lô trong 1 transaction bằng UPDATE có điều kiện, idempotent, thiếu hàng trả 409; gỡ consumer `SalesOrderCreated` (§8) |
| 4 | ✅ | ~~`catalog-service` publish RabbitMQ thẳng trong controller; thư viện AMQP chưa cài nên `ProductCreated` chưa từng được gửi; import Excel và kết quả hỏi hàng không phát event~~ | Đã xong 2026-10-20: cả 3 đường tạo sản phẩm ghi `ProductCreated` qua outbox trong cùng transaction; relay `php artisan outbox:relay` (có trong `start_dev`) |
| 5 | 🟠 | Customer/Supplier nằm trong catalog | Tách `partner-service` (§2.1); trước đó các service khác chỉ gọi qua API, không đọc DB |
| 6 | 🟠 | `ItemInquiriesController` (purchase) chứa logic + gọi `DbContext` + HTTP hardcode `localhost` | Chuyển logic vào Application, HTTP vào typed client ở Infrastructure (§3, §6) |
| 7 | 🟠 | Gọi REST nội bộ thiếu timeout/retry, URL mặc định hardcode | Áp §6 |
| 8 | 🟠 | Laravel services (catalog, identity) logic nằm trong controller | Tách `Services/Actions` (§3) khi sửa tới module đó |
| 9 | 🟠 | JWT HS256 dùng chung secret; `X-Internal-Service-Key` dùng chung | RS256 + service token (§9) |
| 10 | ✅ | ~~`docker-compose.yml` chỉ có hạ tầng, gateway trỏ tới container không tồn tại~~ | Đã xong 2026-10-24: Đã viết toàn bộ Dockerfile (10 microservices, relay workers, gateway, frontend SPA) + docker-compose.yml hoàn chỉnh (§12) |
| 11 | 🟡 | File DB, `.backup`, smoke DB nằm trong repo/git | Bổ sung `.gitignore`, `git rm --cached` (§12) |
