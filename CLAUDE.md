# Smart ERP — quy tắc cho AI coding agent

Bạn đang làm việc với vai trò Senior Software Architect. Ưu tiên bảo vệ kiến trúc phân tán, không đánh đổi cấu trúc để lấy tiện lợi nhất thời. Trả lời người dùng bằng tiếng Việt.

Dự án gồm microservices: C# .NET 10 (purchase, inventory, chat), PHP Laravel (identity, catalog, sales, finance, eoffice), Node.js (notification), Python FastAPI (ai-service), và frontend Vue 3 + PrimeVue 4 + Tailwind CSS v4.

---

## 0. Luật cốt lõi (áp dụng cho mọi thay đổi)

### 0.1. Clean Architecture
- Mỗi service có bốn lớp: **Presentation** (Controller, Request/Response DTO), **Application** (use case, handler, interface), **Domain** (Entity, Value Object, quy tắc bất biến), **Infrastructure** (EF Core, Eloquent, RabbitMQ, HTTP client, Outbox relay). Infrastructure triển khai các interface do Application hoặc Domain khai báo.
- Chiều phụ thuộc chỉ đi một hướng: Presentation → Application → Domain, và Infrastructure phụ thuộc vào interface. **Domain không import framework, ORM, HTTP hay RabbitMQ.**
- Logic tính tiền, kho, công nợ chỉ nằm ở Domain hoặc Application. Controller chỉ validate định dạng, gọi use case, rồi bọc envelope.
- Chỉ Infrastructure được biết URL và connection của service khác. Không trả Domain Entity ra API.

### 0.2. UTC
- C#: dùng `DateTime.UtcNow`. **Cấm `DateTime.Now`.**
- PHP Laravel: dùng `now()->toIso8601String()` hoặc `now()->utc()`. **Cấm dùng múi giờ của server.**
- Trên wire: ISO 8601 UTC, có hậu tố `Z`, ví dụ `2026-09-10T14:30:00.000Z`. Hiển thị giờ địa phương là việc của frontend.

### 0.3. AsNoTracking và truy vấn chỉ đọc
- Mọi query EF Core chỉ đọc **bắt buộc** có `.AsNoTracking()`.
- Laravel: chọn cột rõ ràng bằng `select([...])` hoặc `DB::table(...)`. Cấm `SELECT *` khi chỉ cần vài cột, và cấm hydrate model nặng chỉ để đọc.
- Query chỉ đọc trả về DTO, không trả Entity.

### 0.4. Atomic Guard (chống bán âm)
- **Cấm read-modify-write:** không đọc số dư lên bộ nhớ, trừ, rồi ghi lại.
- Mọi biến động tồn dùng UPDATE có điều kiện, ví dụ `WHERE quantity >= :qty` (chi tiết ở mục 3). Số dòng ảnh hưởng = 0 → trả `409 INSUFFICIENT_STOCK`.
- Cả hai số dư luôn `>= 0`. Sổ cái là bất biến và có `UNIQUE (document_type, document_id, warehouse_id, product_id)`.
- Chứng từ nhiều dòng giữ hàng trong **một transaction**. Thiếu một dòng thì rollback toàn bộ.

---

## 1. Luồng nghiệp vụ trung tâm

Mọi tính năng phải nằm đúng vị trí trong chuỗi SCM:

```text
PROCUREMENT (PO, purchase)
  → GOODS RECEIPT (nhập kho, purchase/inventory)
  → INVENTORY (tồn On-Hand, inventory)
  → QUOTATION → SALES ORDER (báo giá → đơn bán, sales)
  → DELIVERY REQUEST (yêu cầu giao, chọn kho bán, sales)
  → SALES INVOICE + RESERVATION (chứng từ bán, giữ hàng nguyên tử cả lô qua REST, sales → inventory)
  → DELIVERY DISPATCH (soạn, đóng gói, giao, sales)
  → GOODS ISSUE (kho ghi sổ xuất, giảm tồn thực tế, inventory)
  → FINANCE / DEBT / REPORTING / AI (finance, ai-service)
```

## 2. Bảy câu tự vấn trước khi viết code

1. Logic này thuộc tầng nào của Clean Architecture? Tính tiền, kho, công nợ không được nằm trong Controller hay Infrastructure. Đặt ở Domain Entity hoặc Application Service/Handler/Action.
2. Có vi phạm hướng phụ thuộc một chiều không? Domain không import framework, ORM, HTTP, RabbitMQ. Presentation và Infrastructure phụ thuộc Application/Domain, không ngược lại.
3. Có dễ viết unit test không? Các phụ thuộc ngoài (DB, RabbitMQ, Clock, HTTP) đã đi qua interface và DI chưa?
4. Có trả Domain Entity ra API không? **Cấm tuyệt đối.** Luôn dùng DTO, Request/Response ViewModel, hoặc `JsonResource`.
5. Múi giờ có phải UTC không? C#: `DateTime.UtcNow`. PHP: `now()->toIso8601String()`. **Cấm `DateTime.Now` và local server time.**
6. Query chỉ đọc đã tối ưu chưa? EF Core: bắt buộc `.AsNoTracking()`. Laravel: chọn cột rõ ràng bằng `select(...)` hoặc Query Builder, không hydrate model nặng.
7. Đã đơn giản nhất chưa (KISS)? Không tạo 3–4 interface rỗng chỉ để bọc một dòng code.

## 3. Database-per-service

Mỗi service có DB riêng (`erp_identity_db`, `erp_catalog_db`, `erp_inventory_db`...), không service nào khác đọc hay ghi.

**Ba điều cấm tuyệt đối:**
1. Cấm JOIN chéo DB, ví dụ `JOIN erp_catalog_db.products`.
2. Cấm FK chéo DB. `FOREIGN KEY ... REFERENCES` chỉ trong cùng một schema.
3. Cấm connection tới DB khác, kể cả chỉ đọc. Cấm Eloquent `$connection` trỏ sang DB khác, cấm đọc file SQLite của service khác.

**Tham chiếu logic:** service khác chỉ được lưu ID nguyên thủy (`BIGINT` hoặc `UUID`), có index, không có FK. Ví dụ `sales_orders.customer_id`, `inventory_stocks.product_id`.

**Thông tin đi kèm** chọn theo thứ tự:
1. Snapshot vào chứng từ (mặc định).
2. Gọi REST lúc thực thi lệnh để validate và lấy dữ liệu snapshot.
3. API Composition ở frontend hoặc BFF.
4. Bảng replica `ref_*` đồng bộ qua event. Chỉ khi cần báo cáo khối lượng lớn hoặc phải chạy được khi owner sập. Bảng chỉ đọc, chỉ chứa cột cần dùng, giữ ID gốc, có lệnh backfill.

**Snapshot bắt buộc** trong `sales_order_items`, `purchase_order_items`, `delivery_items`, `invoices`:
- Dòng hàng: `product_name`, `sku`, `unit_code`, `brand`, `designation`, `com` (nếu in ra), `unit_price` hoặc `gross_price`, `unit_cost`, `discount_percent`.
- Đầu chứng từ: `customer_code`, `customer_name`, `tax_code`, `contact_name`, `created_by_name`, `approved_by_name`, `picker_name`. Không tra ngược identity để lấy tên.
- Lý do: khi catalog đổi giá, hóa đơn và sổ sách cũ không được thay đổi.

**Bất biến tồn kho** (`inventory-service`), mỗi cặp (kho, sản phẩm) một dòng:

| Bảng | Ý nghĩa | Tăng khi | Giảm khi |
|---|---|---|---|
| `inventory_stocks.quantity` | Tồn thực tế | Nhập kho mua, nhận hàng trả | Ghi sổ xuất chứng từ bán |
| `inventory_stocks_reservation.quantity` | Tồn khả dụng | Nhập kho mua, nhả giữ chỗ | Tạo chứng từ bán (giữ hàng) |
| `inventory_stocks_transaction` | Sổ cái tồn thực tế (bất biến) | — | — |
| `inventory_stocks_reservation_transaction` | Sổ cái tồn khả dụng (bất biến) | — | — |

- Hàng đang giữ = tồn thực tế − tồn khả dụng.
- Cả hai số dư luôn `>= 0`. **Cấm đọc tồn lên bộ nhớ rồi trừ và ghi lại.** Dùng UPDATE có điều kiện:
  ```sql
  UPDATE inventory_stocks_reservation
  SET quantity = quantity - :qty, updated_at = :now
  WHERE warehouse_id = :warehouse_id AND product_id = :product_id
    AND quantity >= :qty;
  ```
  Số dòng ảnh hưởng = 0 → trả `409 INSUFFICIENT_STOCK`. Xuất kho làm tương tự trên `inventory_stocks`.
- Sổ cái có `UNIQUE (document_type, document_id, warehouse_id, product_id)`, mỗi chứng từ chỉ ghi sổ một lần cho mỗi sản phẩm và kho.

**Bảng kỹ thuật cho event** (mỗi service có phát hoặc nhận event phải có):

```sql
CREATE TABLE outbox_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    aggregate_type VARCHAR(100) NOT NULL,
    aggregate_id VARCHAR(100) NOT NULL,
    routing_key VARCHAR(150) NOT NULL,
    payload LONGTEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING', -- PENDING, PUBLISHED, FAILED
    retry_count INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_created (status, created_at)
);

CREATE TABLE processed_events (
    event_id VARCHAR(100) NOT NULL,
    consumer_name VARCHAR(100) NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id, consumer_name)
);
```

**Index:** trường tham chiếu logic (`customer_id`, `product_id`, `warehouse_id`) bắt buộc có index. Mã nghiệp vụ (`sku`, `barcode`, `code`) bắt buộc `UNIQUE INDEX`. Cột trạng thái hay lọc và `created_at` có index.

## 4. REST hay queue

Hỏi theo thứ tự:
1. Bên gọi có cần câu trả lời ngay để làm tiếp không? → **REST**.
2. Đây là thông báo "việc X đã xảy ra" để service khác tự phản ứng? → **Event qua queue**.
3. Việc nặng, chậm, dễ lỗi tạm thời (gửi email, sinh PDF, ETL) mà người dùng không chờ? → **Command message qua queue**, có retry.
4. Luồng kéo dài nhiều service, cần đảm bảo hoàn tất? → **Event + Saga**.
5. Còn lại → **REST**. REST là mặc định.

**Cấm:** dùng queue để giả lập request/response; chuỗi REST đồng bộ dài hơn 2 bước; gọi REST trong vòng lặp (N+1), phải dùng endpoint batch; phát event "phòng khi có ai cần"; dùng event để validate.

**Ví dụ:** sales lấy dữ liệu sản phẩm qua REST để snapshot. Giữ hàng qua REST vì người lập chứng từ cần biết ngay đủ hàng hay không. Đơn được duyệt → event `SalesOrderApproved` cho notification. Gửi email → command message `erp.email.send`.

**Quy chuẩn REST service-to-service:**
- URL lấy từ cấu hình `*_SERVICE_URL`. **Cấm hardcode `localhost:800x`.**
- Timeout 2–5 giây bắt buộc.
- Retry tối đa 2 lần, chỉ cho request idempotent (GET, hoặc POST có `Idempotency-Key`), có backoff.
- Luôn chuyển tiếp `X-Correlation-ID`. Gọi thay người dùng thì chuyển tiếp JWT người dùng. Job và consumer dùng service token riêng.
- Endpoint nội bộ đặt dưới `/internal/*`, gateway không expose.
- Lời gọi nằm trong typed client ở Infrastructure (`CatalogClient`, `IdentityClient`). Controller chỉ gọi qua interface, không gọi `HttpClient` hay `Http::` rải rác.
- Owner không phản hồi → trả `503 DEPENDENCY_UNAVAILABLE`, không âm thầm dùng dữ liệu rỗng.

**Giữ hàng cho chứng từ bán:**
- Sales gọi **một lần** `POST /api/v1/inventory/stocks/reserve/batch` cho cả chứng từ, `order_id` là số chứng từ.
- Inventory giữ cả lô trong một transaction. Thiếu một dòng thì rollback và trả `409 INSUFFICIENT_STOCK`.
- Idempotent theo (số chứng từ, kho, sản phẩm). Cùng số mà khác số lượng → `422 DOCUMENT_ALREADY_POSTED`.
- Số chứng từ theo quy tắc `INV-{mã khách}-{ddmmyy}[-A..]`, giữ chỗ trong `document_number_claims` ngoài transaction để số đã thất bại không bao giờ được cấp lại.
- Kết quả không chắc (timeout, 5xx) → nhả hàng của số đó ngay. Thiếu hàng (409) là kết quả chắc chắn, không cần nhả.
- Hủy chứng từ: `POST /invoices/{id}/cancel` và nhả hàng qua `POST /stocks/release/document`. Chỉ hủy khi chưa xuất kho.

## 5. Event và Outbox

- Exchange `erp.events` (topic), DLX `erp.dlx`. Routing key `<service>.<aggregate>.<event>`, chữ thường, ví dụ `sales.order.approved`.
- Mỗi consumer có queue riêng `erp.<consumer>.events`.
- Event đặt tên quá khứ, PascalCase: `SalesOrderApproved`, `GoodsReceived`. Chỉ thêm event khi đã có consumer thật.
- **Transactional outbox bắt buộc:** ghi dữ liệu nghiệp vụ và `outbox_events` trong cùng transaction. Relay worker quét `PENDING`, publish, rồi đánh `PUBLISHED`. **Cấm publish thẳng trong Controller hay Action.**
- **Idempotent consumer bắt buộc:** ghi `(event_id, consumer_name)` vào `processed_events` cùng transaction xử lý. Trùng thì bỏ qua và ack. Không giả định thứ tự, so `occurred_at` hoặc version với replica.
- Thay đổi thêm trường thì giữ `version`, consumer là tolerant reader. Thay đổi phá vỡ thì tăng `version` và phát song song bản cũ và mới.

## 6. REST API và wire format

**Gateway:** mọi request từ frontend đi qua `http://localhost:8000/api/v1/{prefix}/{resources}`.

| Prefix | Service | Công nghệ | Port |
|---|---|---|---|
| `/api/v1/auth/` | identity-service | PHP Laravel | 8001 |
| `/api/v1/catalog/` | catalog-service | PHP Laravel | 8002 |
| `/api/v1/purchase/` | purchase-service | C# .NET 10 | 8003 |
| `/api/v1/inventory/` | inventory-service | C# .NET 10 | 8004 |
| `/api/v1/sales/` | sales-service | PHP Laravel | 8005 |
| `/api/v1/notifications/` | notification-service | Node.js | 8006 |
| `/api/v1/eoffice/` | eoffice-service | PHP Laravel | 8007 |
| `/api/v1/finance/` | finance-service | PHP Laravel | 8008 |
| `/api/v1/chat/` | chat-service | C# .NET 10 | 8009 |
| `/api/v1/ai/` | ai-service | Python FastAPI | 8010 |
| `/api/v1/partners/` (kế hoạch) | partner-service | PHP Laravel | 8011 |

- Đường dẫn dùng danh từ số nhiều, kebab-case: `/delivery-requests`.
- Verb: GET đọc, POST tạo hoặc hành động (`/orders/{id}/confirm`), PUT thay toàn bộ, PATCH cập nhật một phần, DELETE là soft-delete.
- Header bắt buộc: `Content-Type: application/json`, `Accept: application/json`, `Authorization: Bearer <JWT>` (trừ login và health). Khuyên dùng `X-Correlation-ID`.
- **JSON luôn `snake_case`**, cả request và response, PHP lẫn C#. C#: `JsonNamingPolicy.SnakeCaseLower` hoặc `[JsonPropertyName("...")]`.
- Thời gian: ISO 8601 UTC, ví dụ `2026-09-10T14:30:00.000Z`.
- Số tiền: số, không có dấu phân cách hàng nghìn trên wire. Số lượng: số nguyên không âm. Format hiển thị thuộc frontend.

**Envelope thành công (200, 201):**
```json
{
  "success": true,
  "data": { "id": 105, "code": "SO-202609-0042", "status": "CONFIRMED", "total_amount": 45000000.0 },
  "meta": {
    "correlation_id": "req_corr_01J8ABC123456789",
    "timestamp": 1788998400,
    "pagination": { "current_page": 1, "per_page": 15, "total_items": 120, "total_pages": 8 }
  }
}
```

**Envelope thất bại (400, 401, 403, 404, 409, 422, 500):**
```json
{
  "success": false,
  "error": { "code": "INSUFFICIENT_STOCK", "message": "...", "details": { } },
  "meta": { "correlation_id": "req_corr_01J8ABC123456789", "timestamp": 1788998400 }
}
```

**Mã lỗi nghiệp vụ chuẩn:**

| Code | HTTP | Ý nghĩa |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Token không hợp lệ, thiếu hoặc hết hạn |
| `PERMISSION_DENIED` | 403 | Thiếu quyền cụ thể |
| `RESOURCE_NOT_FOUND` | 404 | Không tìm thấy bản ghi |
| `INSUFFICIENT_STOCK` | 409 | Tồn khả dụng không đủ |
| `CONCURRENT_CONFLICT` | 409 | Xung đột đồng thời (optimistic concurrency) |
| `VALIDATION_FAILED` | 422 | Dữ liệu sai định dạng hoặc thiếu trường |
| `OVER_DELIVERY_NOT_ALLOWED` | 422 | Xuất vượt số lượng trên đơn bán |
| `DEPENDENCY_UNAVAILABLE` | 503 | Service phụ thuộc không phản hồi |
| `SAGA_TIMEOUT` | 504 | Quá thời gian chờ phản hồi từ Inventory |

Khi thêm mã lỗi mới, cập nhật `docs/contracts/api-spec.md`.

## 7. Quy ước code

**Đặt tên:**

| Thành phần | C# | PHP |
|---|---|---|
| Class, Entity, Model | PascalCase | PascalCase |
| Interface | `I` + PascalCase (`IStockRepository`) | `...Interface` hoặc `I...` |
| Method | PascalCase + `Async` nếu async (`ReserveStockAsync`) | camelCase (`reserveStock`) |
| Property công khai | PascalCase | camelCase |
| Private field | `_camelCase` | camelCase |
| Local, parameter | camelCase | camelCase |
| Hằng số, enum | PascalCase (`OrderStatus.PendingReservation`) | SCREAMING_SNAKE (`OrderStatus::PENDING_RESERVATION`) |
| JSON key | snake_case | snake_case |
| Bảng, cột DB | snake_case | snake_case |

**C#:**
- Clean Architecture 4 project: `*.Api`, `*.Application`, `*.Domain`, `*.Infrastructure`.
- Map Entity sang DTO trước khi trả về Controller.
- Query chỉ đọc có `.AsNoTracking()`.
- `DateTime.UtcNow`. Cấm `DateTime.Now`.
- I/O dùng `async/await`, truyền `CancellationToken` vào EF Core. Cấm `.Result` và `.Wait()`.

**PHP (Laravel 11/12):**
- Controller mỏng. Validate bằng `FormRequest` cho POST, PUT, PATCH. Logic nằm trong `app/Services` hoặc `app/Actions`. Response bọc bằng `JsonResource`.
- Thời gian: `now()->toIso8601String()` hoặc `now()->utc()`.
- Đọc nhiều: `DB::table(...)` hoặc `select([...])`. Cấm `SELECT *` khi chỉ cần vài cột.
- Client liên service ở `app/Infrastructure/Clients/`.

**Python (ai-service):**
- Đọc JWT, giới hạn tool và dữ liệu theo quyền của người hỏi. AI không có quyền tối cao.
- Pydantic schema, dùng alias hoặc snake_case thống nhất.

**Chung:**
- Không nuốt lỗi. Cấm `try-catch` rỗng. Khi bắt exception phải log kèm `correlation_id` hoặc re-throw exception có domain.
- KISS và DRY. Không tạo lớp trung gian thừa.
- Log lỗi luôn có `correlation_id`, `service`, `user_id`.

### 7.6. Giao diện (Vue 3 + PrimeVue 4 + Tailwind CSS v4)

Nguyên tắc: Clean Canvas, mật độ thông tin cao, phân khối bằng hairline border và khoảng cách, nhất quán 100% bộ token và component, 100% Tailwind (utility-first). Bản gốc đầy đủ nằm ở `.agents/rules/ui-guidelines.md`. Khi hai file khác nhau, file đó được ưu tiên.

**Điều cấm tuyệt đối:**
1. **Cấm gradient.** Không dùng `linear-gradient`, `radial-gradient`, `bg-gradient-*`, kể cả text gradient. Dùng màu phẳng.
2. **Hạn chế tím và indigo.** Chỉ dùng indigo cho nút Primary (`indigo-600`) và chữ nhấn mạnh (`text-indigo-600`). Không dùng indigo làm nền thẻ, nền bảng, viền hay badge.
3. **Cấm glassmorphism.** Không dùng `backdrop-filter: blur()`, không dùng nền trong suốt làm mờ. Bề mặt chứa dữ liệu là `bg-white` đục hoàn toàn.
4. **Cấm card lồng card.** Tối đa một cấp Card bọc ngoài. Bên trong phân tách bằng `<Divider />`, `border-b border-slate-200` hoặc khoảng cách lưới.
5. **Cấm shadow tràn lan.** Giao diện phẳng (`shadow-none`), phân khối bằng viền hairline `border-slate-200`. Chỉ cho phép shadow nhẹ với phần tử nổi: Dialog, Toast, Dropdown đang mở.
6. **Cấm CSS tự chế.** Không viết class tự đặt tên (`.my-box`, `.custom-btn`) trong `<style scoped>`. Không tạo file CSS rời. Class Tailwind đã có thì không viết lại bằng CSS.

**Design tokens:**
- **Bo góc:** chốt cứng `6px` (`rounded-[6px]`) cho button, input, select, card, panel, dialog, tag, badge, và header của DataTable (`rounded-t-[6px]`). **Cấm** `rounded-xl`, `rounded-2xl`, nút dạng pill. Ngoại lệ duy nhất: avatar và chấm trạng thái online/offline dùng `rounded-full`.
- **Viền:** hairline 1px `border border-slate-200` (`#E2E8F0`).
- **Nền:** canvas `bg-slate-50` (`#F8FAFC`), thẻ và panel `bg-white` (`#FFFFFF`).
- **Chữ:** tiêu đề và chỉ số `text-slate-900`, nội dung `text-slate-700`, ghi chú và placeholder `text-slate-500`.
- **Nút Primary:** `indigo-600` (`#4F46E5`), hover `indigo-700`. Nút phụ dùng `severity="secondary"`, hoặc `outlined`, `text`.
- **Khoảng cách:** chỉ dùng thang bội số 4px/8px. Không dùng số lẻ tùy ý.
  - `gap-1`, `p-1` (4px): icon với text trong button hoặc badge.
  - `gap-2`, `p-2` (8px): label với input, padding compact, khoảng cách giữa các filter chip.
  - `gap-3`, `p-3` (12px): nút trong button group, ô bảng thu gọn.
  - `gap-4`, `p-4` (16px): padding Card và form, gap giữa các form row, grid gap chuẩn.
  - `p-5` (20px): padding modal và panel chính.
  - `p-6` (24px): lề trang, khoảng cách giữa các section lớn.
  - `gap-8`, `p-8` (32px): phân đoạn chính của màn hình.
- **Badge trạng thái:** nền `-50`, chữ `-700`, viền `-200`. Hoạt động hoặc xác nhận `emerald`, chờ xử lý hoặc giữ kho `amber`, thất bại hoặc hết hàng `rose`, nháp hoặc thông tin `sky`.

**Tailwind và PrimeVue:**
- Viết class utility trực tiếp trên `<template>`: `flex`, `grid`, `gap-4`, `p-4`, `text-sm`, `rounded-[6px]`.
- `<style>` chỉ dùng khi cần `:deep(...)` để ghi đè PrimeVue, hoặc khai báo theme token.
- Dùng component PrimeVue 4 cho mọi thành phần:
  - Nút: `<Button>` với `severity` (`primary`, `secondary`, `danger`, `success`, `warn`), kèm `outlined` hoặc `text` khi không phải hành động chính. Không dùng `<button class="...">` thuần.
  - Bảng dữ liệu: `<DataTable>`, `<Column>`, `<Paginator>`. Không dùng `<table>` HTML thuần.
  - Nhập liệu: `<InputText>`, `<InputNumber>`, `<Textarea>`, `<Select>`, `<MultiSelect>`, `<DatePicker>`, `<Checkbox>`, `<RadioButton>`, `<ToggleSwitch>`. Không tự style thẻ `<input>`.
  - Khung và hộp thoại: `<Card>`, `<Panel>`, `<Divider>`, `<Toolbar>`. `<Dialog>` và `<Drawer>` dùng cho form thêm hoặc sửa nhanh. `<ConfirmDialog>` dùng cho thao tác nguy hiểm.
  - Thông báo và trạng thái: `<Toast>` cho thông báo tức thời, `<Message>`, `<Tag>`, `<Badge>` (soft-toned).
  - Điều hướng: `<Breadcrumb>`, `<Tabs>`, `<TabList>`, `<TabPanel>`, `<Menu>`.

**Văn phong, tiêu đề, khoảng cách:**
- **Chữ hiển thị là tiếng Việt nghiệp vụ, trực diện.** ĐÚNG: "Thêm mới sản phẩm", "Lưu thay đổi", "Xuất file Excel", "Đặt lại bộ lọc". SAI: "Trình nộp dữ liệu", "Tiết kiệm", "Bấm vào đây để...".
- **Trạng thái rỗng có hướng dẫn.** ĐÚNG: "Chưa có đơn hàng nào trong phân hệ này. Nhấn 'Tạo đơn mới' để bắt đầu." SAI: "Trống rỗng!", "No data found."
- **Thông báo cụ thể.** ĐÚNG: "Đã cập nhật bảng giá đối tác thành công." SAI: "Thành công!", "Lỗi hệ thống 500!".
- **Cấm văn phong quảng cáo, sáo rỗng** như "Khám phá sức mạnh quản trị tối thượng".
- **Một tiêu đề chính cho mỗi màn hình** (`<h1>`), đặt ở Page Header. Ví dụ: `Danh Mục Sản Phẩm` ở trang danh sách, `Đơn Mua Hàng #PO-2024-001` ở trang chi tiết.
- **Không lặp tiêu đề.** Trong Card hay DataTable bên dưới không viết lại `<h2>` hoặc `<h3>` cùng nội dung. Chỉ dùng Toolbar chứa công cụ: tìm kiếm, bộ lọc trạng thái, nút Thêm mới, nút Xuất dữ liệu.
- **Không slogan.** Dưới tiêu đề chỉ được có tối đa một dòng subtitle ngắn `text-slate-500 text-xs`.
- **Tiêu đề Dialog và Drawer nói rõ hành động:** "Thêm Mới Khách Hàng", "Xác Nhận Hủy Đơn Hàng". Cấm tiêu đề mập mờ như "Thông tin", "Thao tác", "Chi tiết".

## 8. Bảo mật và repo

- Không commit `*.db`, `*.sqlite`, `erp_*_db`, `.backup`, `bin/`, `obj/`, `vendor/`, `node_modules/`, `.venv/`, `.env`.
- `/internal/*` không đi qua gateway.
- Phân quyền kiểm tra tại service owner theo permission trong JWT. Frontend ẩn nút chỉ là UX.
- Cấu hình qua biến môi trường. Không có đường dẫn file trỏ sang thư mục service khác.
- Không sửa thư mục `docs/` trừ khi có quyết định kiến trúc mới.

## 9. Checklist trước khi bàn giao

- [ ] Code nằm đúng vị trí trong luồng SCM.
- [ ] Controller mỏng, logic nằm trong Domain và Application.
- [ ] Không có JOIN, FK hoặc connection chéo DB.
- [ ] Dữ liệu giao dịch đã snapshot đủ tại thời điểm tạo chứng từ.
- [ ] Biến động tồn kho dùng SQL nguyên tử.
- [ ] Event đi qua Outbox, consumer idempotent qua `processed_events`.
- [ ] C# query chỉ đọc có `.AsNoTracking()` và trả DTO.
- [ ] Response đúng envelope, key `snake_case`.
- [ ] Frontend: Tailwind và PrimeVue, không gradient, không glassmorphism, không card lồng card, không shadow tràn lan, bo góc 6px, nút Primary indigo-600, một tiêu đề chính, văn phong tiếng Việt.

## 10. Ngoài phạm vi

- Kho kiến thức của trợ lý AI nằm ở `docs/knowledge/`. Đây là nội dung bot trả lời người dùng cuối, không phải quy tắc viết code.
- Roadmap và báo cáo đồ án không phải quy tắc. Không tự cập nhật chúng khi làm code.
