# ARCHITECTURE GUIDELINES — Smart Distributed ERP

> **Mục tiêu**: Định nghĩa cấu trúc kiến trúc tổng thể, nguyên tắc phân lớp Clean Architecture và các mẫu thiết kế phân tán (Distributed Patterns) bắt buộc cho toàn bộ các microservices (PHP Laravel & C# .NET 10).

---

## 1. NGUYÊN TẮC CLEAN ARCHITECTURE TRONG MỖI SERVICE

Dù viết bằng **C# (.NET 8)** hay **PHP (Laravel)**, mọi microservice đều phải tuân theo 4 tầng phân lập rõ ràng:

```text
    ┌─────────────────────────────────────────────────────────┐
    │  Presentation Layer (Controllers, Middlewares, API Resp) │
    └────────────────────────────┬────────────────────────────┘
                                 │ Phụ thuộc vào
    ┌────────────────────────────▼────────────────────────────┐
    │  Application Layer (Commands, Queries, DTOs, Interfaces) │
    └────────────────────────────┬────────────────────────────┘
                                 │ Phụ thuộc vào
    ┌────────────────────────────▼────────────────────────────┐
    │  Domain Layer (Entities, Value Objects, Domain Events)   │
    │  * TUYỆT ĐỐI KHÔNG PHỤ THUỘC VÀO BẤT KỲ TẦNG NÀO *      │
    └────────────────────────────▲────────────────────────────┘
                                 │ Triển khai Interface của
    ┌────────────────────────────┴────────────────────────────┐
    │  Infrastructure Layer (EF Core / Eloquent, RabbitMQ)    │
    └─────────────────────────────────────────────────────────┘
```

### 1.1. Domain Layer (Trái tim của hệ thống)
* Chứa các Entity, Aggregate Root, Value Object và Domain Event.
* Chứa quy tắc bất biến nghiệp vụ SCM (ví dụ: `Available = OnHand - Reserved`, không cho phép số âm).
* **Tuyệt đối không phụ thuộc vào Framework**: Không chứa annotation của EF Core / Eloquent, không import thư viện HTTP, không gọi RabbitMQ.

### 1.2. Application Layer (Điều phối nghiệp vụ)
* Chứa Use Cases, Command/Query Handlers, DTOs (Data Transfer Objects), Service Interfaces.
* Điều phối luồng nghiệp vụ: Nhận DTO từ Controller $\rightarrow$ Gọi Domain thực thi $\rightarrow$ Lưu qua Repository Interface $\rightarrow$ Ghi Outbox Event $\rightarrow$ Trả về Response DTO.

### 1.3. Infrastructure Layer (Công nghệ cụ thể)
* Triển khai các Interface định nghĩa ở Application/Domain:
  - C# (.NET): DbContext (EF Core), Dapper, RabbitMqPublisher, RabbitMqConsumerBackgroundService.
  - PHP (Laravel): Eloquent Repositories, RabbitMQ AMQP Publisher, Outbox Relay Command.
* Đọc/ghi cơ sở dữ liệu và giao tiếp qua mạng.

### 1.4. Presentation Layer (Giao diện API)
* Controllers, API Middlewares, Correlation ID Filter.
* Nhận HTTP request, validate định dạng đầu vào (Validation Filter), chuyển tới Application Layer và bọc dữ liệu trả về theo chuẩn **API Envelope**.

---

## 2. BẢN ĐỒ MICROSERVICES & PHÂN ĐỊNH RANH GIỚI (BOUNDED CONTEXTS)

| Microservice | Công nghệ | Cổng (Port) | Bounded Context & Trách nhiệm chính |
|---|---|---|---|
| **`identity-service`** | PHP 8.2+ (Laravel) | **8001** | Auth Provider, quản lý Users, Roles, Dynamic RBAC, cấp phát Stateless JWT Token. |
| **`catalog-service`** | PHP 8.2+ (Laravel) | **8002** | Master Data: Sản phẩm, Đơn vị tính, Danh mục kho bãi, Đối tác (Khách hàng/NCC), ghi Outbox Event. |
| **`purchase-service`** | PHP / C# | **8003** | Quản lý Đơn mua hàng (PO), Phê duyệt PO, Xác nhận Nhập kho (Goods Receipt flow). |
| **`inventory-service`** | **C# (.NET 8 Web API)** | **8004** | High-performance Stock Engine, Quản lý On-Hand/Reserved/Available, Atomic Concurrency Lock, Thẻ kho. |
| **`sales-service`** | **C# (.NET 8 Web API)** | **8005** | Quản lý Đơn bán hàng (SO), Lập Delivery Request, Điều phối Saga Orchestrator. |
| **`notification-service`** | Node.js | **8006** | Gửi Email / thông báo realtime cho người dùng. |
| **`eoffice-service`** | PHP Laravel | **8007** | Văn phòng số nội bộ: Xin nghỉ phép, Đề nghị mua sắm, Tạm ứng, Lịch công tác và luồng duyệt dùng chung. |
| **`finance-service`** | PHP Laravel | **8008** | Thu chi, sổ quỹ, công nợ phải thu, sổ cái. |
| **`ai-service`** | Python (FastAPI) | **8009** | Permission-Aware Copilot, Dự báo Min-Stock, Phân tích luân chuyển kho. |

---

## 3. KIẾN TRÚC HƯỚNG SỰ KIỆN (EVENT-DRIVEN ARCHITECTURE)

```text
[ Client Request ] 
       │
       ▼
[ Controller ] ──> [ Service/Handler ]
                         │
                         ▼  (CÙNG 1 LOCAL DATABASE TRANSACTION)
       ┌─────────────────┴──────────────────┐
       │                                    │
[ Business Record (e.g. Products/Orders) ]  │
       │                                    │
       ▼                                    ▼
[ Database Table ]                 [ outbox_events Table ] (status = PENDING)
                                            │
                                            ▼ (Quét định kỳ)
                                   [ Outbox Relay Worker ]
                                            │
                                            ▼ (AMQP)
                                   [ RabbitMQ Topic Exchange: 'erp.events' ]
                                            │
                                            ▼ (At-Least-Once Delivery)
                                   [ Consumer (.NET / PHP) ]
                                            │
                                            ▼ (Kiểm tra processed_events)
                                   [ Idempotent Execution ]
```

### 3.1. Chuẩn Hóa CloudEvents Envelope
Mọi message bắn lên RabbitMQ Topic Exchange `erp.events` bắt buộc tuân theo:
```json
{
  "event_id": "evt_01J8ABCXYZ1234567890",
  "event_type": "SalesOrderCreated",
  "aggregate_type": "SalesOrder",
  "aggregate_id": "SO-202609-0001",
  "timestamp": "2026-09-10T14:00:00.000Z",
  "version": "1.0",
  "correlation_id": "req_corr_987654321",
  "producer": "sales-service",
  "payload": { ... }
}
```

### 3.2. Transactional Outbox Pattern
* Giải quyết triệt để bài toán **Dual-Write** (không thể đảm bảo cả DB và RabbitMQ cùng commit thành công).
* Dữ liệu nghiệp vụ và sự kiện Domain Event được lưu vào bảng `outbox_events` **trong cùng 1 local SQL transaction**.
* Outbox Relay Worker chịu trách nhiệm quét và gửi sang RabbitMQ, cập nhật `PUBLISHED`.

### 3.3. Idempotent Consumer Pattern
* Tránh xử lý trùng lặp tin nhắn do cơ chế At-Least-Once của RabbitMQ.
* Consumer **bắt buộc** kiểm tra `event_id` trong bảng `processed_events` trước khi xử lý.

### 3.4. Saga Pattern Cho Luồng Bán Hàng & Giữ Kho
1. `sales-service` tạo đơn ở trạng thái `PENDING_RESERVATION`, ghi Outbox `SalesOrderCreated`.
2. `inventory-service` lắng nghe event, thực hiện khóa giữ chỗ nguyên tử:
   - **Thành công**: Phát sinh `StockReserved` $\rightarrow$ `sales-service` cập nhật đơn sang `CONFIRMED`.
   - **Thất bại**: Phát sinh `StockReservationFailed` $\rightarrow$ `sales-service` thực thi giao dịch bồi hoàn cập nhật đơn sang `REJECTED_OUT_OF_STOCK`.

---

## 4. KIỂM SOÁT TRANH CHẤP ĐỒNG THỜI (CONCURRENCY CONTROL)

### 4.1. Zero Overselling Bằng Atomic SQL Guard
Nghiêm cấm đọc số lượng tồn lên memory rồi tính toán trừ sau (Race condition). Khi thực hiện giữ chỗ hàng (Reserve Stock), phải dùng câu lệnh cập nhật nguyên tử có điều kiện chặn:
```sql
UPDATE inventory_stocks 
SET quantity_reserved = quantity_reserved + :requested_qty,
    updated_at = UTC_TIMESTAMP()
WHERE product_id = :product_id 
  AND warehouse_id = :warehouse_id 
  AND (quantity_on_hand - quantity_reserved) >= :requested_qty;
```
* Nếu số dòng bị ảnh hưởng = 0 $\rightarrow$ Lập tức kết luận không đủ tồn khả dụng (`INSUFFICIENT_STOCK`).

### 4.2. Optimistic Concurrency Control (OCC)
* Mọi bảng chứng từ (Sales Order, Purchase Order) đều có cột `version` (`INT`) hoặc `RowVersion`.
* Khi cập nhật trạng thái: `WHERE id = :id AND version = :current_version`. Nếu số dòng = 0 $\rightarrow$ Báo lỗi `409 Conflict` (`CONCURRENT_CONFLICT`).

---

## 5. TÍCH HỢP TRỢ LÝ AI THEO NGỮ CẢNH PHÂN QUYỀN (PERMISSION-AWARE COPILOT)

* AI Copilot (`ai-service`) không được phép có "quyền tối cao" truy cập mọi dữ liệu.
* Khi nhận request từ Client, AI Service trích xuất Token JWT của người dùng:
  - Kiểm tra `roles` và `permissions`.
  - Giới hạn System Prompt và phạm vi Tool Calling tương ứng (Sales Rep chỉ xem đơn của mình; Warehouse Keeper chỉ xem dữ liệu kho; chỉ BOD mới xem báo cáo tài chính).
