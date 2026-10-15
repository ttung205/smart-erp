# Đặc Tả Hợp Đồng Sự Kiện (Domain Events Specification)

Tài liệu này quy định cấu trúc Envelope và danh mục sự kiện trong hệ thống Smart Distributed ERP.

---

## 1. Cấu Trúc Domain Event Envelope (CloudEvents Compatible)

Mọi message được bắn lên Message Broker (RabbitMQ) **bắt buộc** phải tuân theo cấu trúc Envelope chuẩn sau:

```json
{
  "event_id": "evt_01J8ABCXYZ1234567890",
  "event_type": "SalesOrderCreated",
  "aggregate_type": "SalesOrder",
  "aggregate_id": "so_2026_0001",
  "timestamp": "2026-09-10T13:50:00.000Z",
  "version": "1.0",
  "correlation_id": "req_corr_987654321",
  "producer": "sales-service",
  "payload": {}
}
```

### Giải thích các trường Metadata:
- **`event_id`** *(string, required)*: UUID v4 hoặc ULID duy nhất cho mỗi sự kiện sinh ra. Được các Consumer sử dụng làm **Idempotency Key** để loại bỏ message trùng lặp (de-duplication).
- **`event_type`** *(string, required)*: Tên định danh của sự kiện theo thể quá khứ (Past tense, PascalCase). Ví dụ: `SalesOrderCreated`, `StockReserved`.
- **`aggregate_type`** *(string, required)*: Tên Domain Model hoặc Entity phát sinh sự kiện. Ví dụ: `SalesOrder`, `InventoryStock`, `PurchaseOrder`.
- **`aggregate_id`** *(string, required)*: Mã định danh của bản ghi nghiệp vụ gốc (ví dụ mã SO, mã SKU).
- **`timestamp`** *(ISO 8601 UTC, required)*: Thời điểm sự kiện được cam kết (committed) vào database.
- **`version`** *(string, required)*: Phiên bản của schema payload (mặc định `"1.0"`).
- **`correlation_id`** *(string, required)*: Mã truy vết xuyên suốt luồng HTTP request ban đầu của người dùng từ API Gateway.
- **`producer`** *(string, required)*: Tên của microservice phát sinh sự kiện (ví dụ: `sales-service`).
- **`payload`** *(object, required)*: Dữ liệu nghiệp vụ thuần túy.

---

## 2. Danh Sách Domain Events Chi Tiết

### 2.1. Nhóm Bán Hàng & Giữ Kho (Sales & Inventory Saga)

#### Event: `SalesOrderCreated`
- **Routing Key:** `sales.order.created`
- **Producer:** `sales-service`
- **Consumer chính:** `inventory-service` (Kích hoạt kiểm tra và thực hiện Atomic Reservation).
- **Payload Schema:**
```json
{
  "order_id": "SO-202609-0001",
  "customer_id": 105,
  "order_date": "2026-09-10",
  "total_amount": 35000000.0,
  "items": [
    {
      "product_id": 201,
      "warehouse_id": 1,
      "quantity": 5,
      "gross_price": 7000000.0,
      "discount_percent": 0.0,
      "unit_price": 7000000.0,
      "subtotal": 35000000.0
    }
  ]
}
```

#### Event: `StockReserved`
- **Routing Key:** `inventory.stock.reserved`
- **Producer:** `inventory-service`
- **Consumer chính:** `sales-service` (Cập nhật trạng thái SO từ `PENDING_RESERVATION` sang `CONFIRMED`).
- **Payload Schema:**
```json
{
  "order_id": "SO-202609-0001",
  "reserved_at": "2026-09-10T13:50:01.000Z",
  "reservations": [
    {
      "product_id": 201,
      "warehouse_id": 1,
      "quantity_reserved": 5,
      "remaining_available": 45
    }
  ]
}
```

#### Event: `StockReservationFailed`
- **Routing Key:** `inventory.stock.reservation_failed`
- **Producer:** `inventory-service`
- **Consumer chính:** `sales-service` (Saga bồi hoàn: Cập nhật SO sang `REJECTED_OUT_OF_STOCK`).
- **Payload Schema:**
```json
{
  "order_id": "SO-202609-0001",
  "failed_at": "2026-09-10T13:50:01.000Z",
  "reason": "INSUFFICIENT_STOCK",
  "failed_items": [
    {
      "product_id": 201,
      "warehouse_id": 1,
      "requested_quantity": 50,
      "available_quantity": 20
    }
  ]
}
```

---

### 2.2. Nhóm Mua Hàng & Nhập Kho (Purchase & Goods Receipt)

#### Event: `PurchaseOrderApproved`
- **Routing Key:** `purchase.po.approved`
- **Producer:** `purchase-service`
- **Consumer chính:** `inventory-service`
- **Payload Schema:**
```json
{
  "po_id": "PO-202609-0042",
  "supplier_id": 302,
  "approved_by": 12,
  "approved_at": "2026-09-10T14:00:00.000Z",
  "items": [
    {
      "product_id": 201,
      "warehouse_id": 1,
      "quantity_expected": 100,
      "unit_cost": 5000000.0
    }
  ]
}
```

#### Event: `GoodsReceived`
- **Routing Key:** `purchase.goods.received`
- **Producer:** `purchase-service` (hoặc module Receipt của Kho)
- **Consumer chính:** `inventory-service` (Tăng `quantity_on_hand`, tạo bản ghi `stock_movements` loại `PO_RECEIPT`).
- **Payload Schema:**
```json
{
  "receipt_id": "GR-202609-0089",
  "po_id": "PO-202609-0042",
  "warehouse_id": 1,
  "received_by": 15,
  "received_at": "2026-09-10T15:00:00.000Z",
  "items": [
    {
      "product_id": 201,
      "quantity_received": 100
    }
  ]
}
```

---

### 2.3. Nhóm Giao Vận & Xuất Kho (Delivery & Goods Issue)

#### Event: `GoodsIssued`
- **Routing Key:** `inventory.goods.issued`
- **Producer:** `inventory-service`
- **Consumer chính:** `sales-service` (Cập nhật Delivery & tính toán Fulfillment Rate $\le 100\%$).
- **Payload Schema:**
```json
{
  "issue_id": "GI-202609-0012",
  "order_id": "SO-202609-0001",
  "delivery_id": "DEL-202609-0001",
  "warehouse_id": 1,
  "issued_by": 15,
  "issued_at": "2026-09-10T16:00:00.000Z",
  "items": [
    {
      "product_id": 201,
      "quantity_issued": 5,
      "balance_on_hand_after": 140,
      "balance_reserved_after": 0
    }
  ]
}
```

---

### 2.4. Nhóm Phê Duyệt & Thông Báo (Approval Workflow → notification-service)

Các event dưới đây được ghi qua **Transactional Outbox** (`outbox_events`) cùng transaction với nghiệp vụ, lệnh `php artisan outbox:relay` publish lên `erp.events`. Consumer chính: `notification-service` (queue `erp.notification.events`). Thiết kế chi tiết: [`docs/notification-architecture.md`](../notification-architecture.md).

Mọi payload đều có `actor: { id, name }` — người thực hiện hành động (bị loại khỏi danh sách nhận thông báo).

| Event | Routing Key | Producer | Payload chính |
| :--- | :--- | :--- | :--- |
| `QuotationSubmitted` / `QuotationApproved` / `QuotationRejected` | `sales.quotation.submitted\|approved\|rejected` | sales-service | `quotation_id`, `quotation_number`, `customer_name`, `total_amount`, `currency`, `created_by_id`, `reason` (khi từ chối) |
| `SalesOrderSubmitted` / `SalesOrderApproved` / `SalesOrderRejected` | `sales.order.submitted\|approved\|rejected` | sales-service | `order_id`, `order_number`, `customer_name`, `total_amount`, `currency`, `created_by_id`, `reason` |
| `DeliveryRequested` / `DeliveryApproved` / `DeliveryRejected` | `sales.delivery.requested\|approved\|rejected` | sales-service | `delivery_request_id`, `request_number`, `order_id`, `order_number`, `customer_name`, `warehouse_id`, `warehouse_name`, `expected_delivery_date`, `created_by_id` (người tạo đơn hàng) |
| `WarehouseDispatchRequested` | `sales.dispatch.requested` | sales-service | `dispatch_id`, `invoice_id`, `invoice_number`, `order_number`, `delivery_request_number`, `customer_name`, `warehouse_id`, `warehouse_name` |
| `LeaveRequestSubmitted` | `eoffice.leave.submitted` | eoffice-service | `id`, `code`, `requester_id`, `requester_name`, `title`, `start_date`, `end_date`, `total_days`, `step_id`, `approver: { id, name }` |
| `LeaveRequestApproved` / `LeaveRequestRejected` / `LeaveRequestReturned` | `eoffice.leave.approved\|rejected\|returned` | eoffice-service | như trên + `step_id`, `comment` |
| `LeaveRequestCancelled` | `eoffice.leave.cancelled` | eoffice-service | như trên + `reason` |

**Ví dụ `QuotationSubmitted`:**
```json
{
  "event_id": "1ddb34f0-27d4-40c7-b8eb-fde89726289c",
  "event_type": "QuotationSubmitted",
  "aggregate_type": "Quotation",
  "aggregate_id": "15",
  "timestamp": "2026-10-15T12:27:34.120Z",
  "version": "1.0",
  "correlation_id": "corr_6e8f2874-d9af-43f8-9ed0-09a39136e265",
  "producer": "sales-service",
  "payload": {
    "quotation_id": 15,
    "quotation_number": "QT-KH001-151026",
    "customer_name": "Công ty A",
    "total_amount": 12345000.0,
    "currency": "VND",
    "created_by_id": 6,
    "actor": { "id": 6, "name": "Nguyễn Văn A" }
  }
}
```

> ⚠️ Binding `sales.order.*` của `erp.inventory.events` cũng nhận `sales.order.submitted|approved|rejected`; inventory-service bỏ qua các `event_type` không xử lý.
