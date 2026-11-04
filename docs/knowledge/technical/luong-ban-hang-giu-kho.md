---
title: Luồng bán hàng và giữ kho
group: technical
audience: developer, admin
source: docs/architecture/ARCHITECTURE.md dòng 356–418 (§11)
verified_by: null
verified_at: null
---

## 11. Tuần tự: tạo đơn bán và giữ kho

Sơ đồ này cố ý thể hiện điểm không liền mạch đã triển khai: tạo đơn bán không giữ hàng. Việc giữ hàng được thực hiện sau đó, cho một Hóa đơn.

```mermaid
sequenceDiagram
    autonumber
    actor U as Người dùng
    participant FE as Frontend Vue
    participant GW as Kong
    participant SAL as Service Sales
    participant CAT as Service Catalog
    participant ID as Service Identity
    participant SDB as DB Sales
    participant RMQ as RabbitMQ
    participant NOTI as Service Notification
    participant INV as Service Inventory
    participant IDB as DB Inventory

    U->>FE: Tạo đơn bán
    FE->>GW: POST /api/v1/sales/orders + JWT
    GW->>SAL: POST /api/orders
    SAL->>CAT: GET khách hàng/sản phẩm (JWT hoặc internal key)
    CAT-->>SAL: Snapshot dữ liệu master
    SAL->>ID: GET users khi cần xác định người phụ trách
    ID-->>SAL: Snapshot người dùng
    SAL->>SDB: Transaction: tạo đơn + dòng, trạng thái DRAFT
    SDB-->>SAL: Commit
    SAL-->>FE: 201 Đơn bán

    Note over SAL,INV: Không có lời gọi hay sự kiện giữ hàng nào xảy ra trong SalesOrderController.store

    U->>FE: Gửi duyệt / duyệt đơn
    FE->>GW: POST orders/{id}/submit-approval hoặc approve
    GW->>SAL: Lệnh quy trình
    SAL->>SDB: Transaction: trạng thái + outbox event
    SDB-->>SAL: Commit
    SAL-->>FE: Thành công
    SDB-->>RMQ: Sự kiện đơn bán qua sales-relay
    RMQ-->>NOTI: erp.notification.events
    NOTI-->>FE: Thông báo Socket.IO

    Note over U,INV: Sau đó, khi Hóa đơn đã tồn tại
    U->>FE: Đồng bộ giữ hàng cho Hóa đơn
    FE->>GW: POST /api/v1/sales/invoices/{id}/sync-reserve
    GW->>SAL: POST /api/invoices/{id}/sync-reserve
    SAL->>INV: POST /api/v1/inventory/stocks/reserve/batch + JWT
    INV->>IDB: Transaction giữ hàng theo lô (nguyên tử)
    alt đủ hàng cho tất cả dòng
        IDB-->>INV: Commit giữ hàng
        INV-->>SAL: 2xx
        SAL->>SDB: inventory_reservation_status = RESERVED
        SAL-->>FE: Giữ hàng thành công
    else thiếu hàng / lỗi phụ thuộc
        IDB-->>INV: Rollback / không giữ một phần
        INV-->>SAL: Lỗi (ví dụ INSUFFICIENT_STOCK)
        SAL->>SDB: Lưu kết quả/trạng thái giữ hàng thất bại
        SAL-->>FE: Lỗi ánh xạ kiểu 409/503
    end
```

Bằng chứng: `SalesOrderController.store:56-230`; `SalesOrderController.submitApproval/approve`; `InvoiceController.syncReserve:272-306`; `InvoiceController.reserveStockForInvoice:766-817`; `StocksController.BatchReserveStock`.
