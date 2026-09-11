# DATABASE GUIDELINES — Distributed Schemas & Data Integrity

> **Mục tiêu**: Quy chuẩn hóa thiết kế cơ sở dữ liệu phân tán, các ràng buộc bất biến (Invariants) của chuỗi cung ứng SCM và mô hình lưu trữ Outbox/Idempotency cho toàn bộ hệ thống.

---

## 1. NGUYÊN TẮC TỐI THƯỢNG: DATABASE-PER-SERVICE

Hệ thống ERP phân tán áp dụng mô hình **Cô lập hoàn toàn Cơ sở dữ liệu**:

```text
┌─────────────────────────┐     ┌─────────────────────────┐     ┌─────────────────────────┐
│     identity-service    │     │     catalog-service     │     │    inventory-service    │
└────────────┬────────────┘     └────────────┬────────────┘     └────────────┬────────────┘
             │                               │                               │
             ▼                               ▼                               ▼
     [erp_identity_db]               [erp_catalog_db]               [erp_inventory_db]
```

### 1.1. Ba Điều Cấm Tuyệt Đối (Zero Tolerance):
1. **CẤM VIẾT QUERY JOIN CHÉO DB**: Không bao giờ viết câu lệnh SQL nối bảng của service này sang database của service khác (ví dụ: cấm `JOIN erp_catalog_db.products`).
2. **CẤM TẠO FOREIGN KEY CHÉO DB**: Khóa ngoại (`FOREIGN KEY ... REFERENCES`) chỉ được tồn tại trong nội bộ cùng một database schema.
3. **CẤM ĐỌC/GHI TRỰC TIẾP DB KHÁC**: Mỗi microservice chỉ có connection string tới DB của chính nó. Không service nào được cấp quyền truy cập vào database của service khác.

### 1.2. Khóa Ngoại Logic (Primitive Logical References)
* Khi cần tham chiếu đến thực thể ở service khác, chỉ lưu **ID nguyên thủy (`BIGINT` hoặc `UUID`)**:
  - Trong `sales_orders`: Cột `customer_id` (`BIGINT`) chỉ lưu ID logic của Customer thuộc `catalog-service`.
  - Trong `inventory_stocks`: Cột `product_id` (`BIGINT`) chỉ lưu ID logic của Product thuộc `catalog-service`.
* Khi cần hiển thị thông tin kèm theo:
  - Hoặc dùng **Data Snapshotting** (lưu cứng dữ liệu lịch sử tại thời điểm lập đơn).
  - Hoặc gọi API Gateway tổng hợp (BFF / Client Aggregation).

---

## 2. QUY TẮC SNAPSHOT DỮ LIỆU LỊCH SỬ (DATA SNAPSHOTTING)

Trong các bảng chứng từ giao dịch thương mại (`sales_order_items`, `purchase_order_items`, `delivery_items`):
* **BẮT BUỘC lưu cứng (Snapshot) các thông tin tại thời điểm chốt chứng từ**:
  - `product_name`: Tên sản phẩm lúc mua/bán.
  - `sku`: Mã SKU.
  - `unit_code`: Đơn vị tính (PCS, BOX, KG...).
  - `unit_price` / `gross_price`: Đơn giá niêm yết tại thời điểm ký kết.
  - `unit_cost`: Giá vốn tại thời điểm lập chứng từ.
  - `discount_percent`: Phần trăm chiết khấu.
* **Lý do bắt buộc**: Khi phòng kinh doanh tăng giá sản phẩm trong `catalog-service`, toàn bộ báo cáo doanh thu, hóa đơn và sổ sách kế toán của các đơn hàng trong quá khứ **không được phép bị thay đổi**.

---

## 3. RÀNG BUỘC TOÀN VẸN TRONG QUẢN TRỊ KHO (SCM INVARIANTS)

Trong bảng `inventory_stocks` của `inventory-service`, các ràng buộc sau là **bất biến**:

```sql
CREATE TABLE inventory_stocks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    warehouse_id BIGINT NOT NULL,
    quantity_on_hand INT NOT NULL DEFAULT 0,
    quantity_reserved INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    version INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- RÀNG BUỘC NGHIỆP VỤ BẤT BIẾN (CHECK CONSTRAINTS)
    CONSTRAINT chk_on_hand_non_negative CHECK (quantity_on_hand >= 0),
    CONSTRAINT chk_reserved_non_negative CHECK (quantity_reserved >= 0),
    CONSTRAINT chk_available_non_negative CHECK ((quantity_on_hand - quantity_reserved) >= 0),
    
    UNIQUE KEY uq_product_warehouse (product_id, warehouse_id)
);
```

### 3.1. Ý Nghĩa 3 Cột Tồn Kho Cốt Lõi:
1. `quantity_on_hand` (Tồn thực tế/vật lý): Số lượng hàng đang nằm thực tế trong kho. Chỉ thay đổi khi **Goods Receipt** (tăng) hoặc **Goods Issue** (giảm).
2. `quantity_reserved` (Tồn giữ chỗ): Số lượng hàng đã cam kết bán cho khách qua Sales Order đang chờ xuất. Tăng khi `StockReserved`, giảm khi `GoodsIssued` hoặc đơn bị `Cancelled`.
3. `Available = (quantity_on_hand - quantity_reserved)`: Tồn khả dụng có thể tiếp tục bán. **Tuyệt đối không bao giờ được phép < 0**.

---

## 4. CẤU TRÚC BẢNG SỰ KIỆN PHÂN TÁN (OUTBOX & IDEMPOTENCY SCHEMAS)

Mỗi service có phát sinh sự kiện đều phải có 2 bảng kỹ thuật sau:

### 4.1. Bảng Transactional Outbox (`outbox_events`)
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
```

### 4.2. Bảng Chống Trùng Lặp Tin Nhắn (`processed_events`)
```sql
CREATE TABLE processed_events (
    event_id VARCHAR(100) NOT NULL,
    consumer_name VARCHAR(100) NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (event_id, consumer_name)
);
```

---

## 5. NGUYÊN TẮC ĐÁNH CHỈ MỤC (INDEXING GUIDELINES)

1. **Khóa logic liên service**: Bắt buộc tạo Index cho các trường tham chiếu logic như `customer_id`, `product_id`, `warehouse_id`.
2. **Mã tìm kiếm nghiệp vụ**: Bắt buộc tạo `UNIQUE INDEX` cho `sku`, `barcode`, `code` (mã đơn hàng, mã phiếu xuất kho).
3. **Trạng thái quy trình**: Đánh index cho các cột trạng thái hay được lọc (`status`, `saga_status`).
4. **Trường sắp xếp**: Đánh index cho `created_at` để tối ưu các truy vấn phân trang danh sách mới nhất.
