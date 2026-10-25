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

> "Chỉ đọc" cũng bị cấm: một model Eloquent có `$connection = 'catalog'` trong sales-service, hay một đường dẫn SQLite trỏ sang `../catalog-service/database/...`, đều là vi phạm.

### 1.2. Khóa Ngoại Logic (Primitive Logical References)
* Khi cần tham chiếu đến thực thể ở service khác, chỉ lưu **ID nguyên thủy (`BIGINT` hoặc `UUID`)**, có index, không có FK:
  - Trong `sales_orders`: Cột `customer_id` chỉ lưu ID logic của Customer thuộc `partner-service` (hiện tạm ở `catalog-service`).
  - Trong `inventory_stocks`: Cột `product_id` chỉ lưu ID logic của Product thuộc `catalog-service`.
* Khi cần thông tin kèm theo, chọn theo thứ tự ưu tiên (chi tiết: [architecture.md §5](architecture.md#5-chia-sẻ-dữ-liệu-qua-ranh-giới-service)):
  1. **Snapshot** vào chứng từ (§2 bên dưới) — mặc định.
  2. **Gọi REST lúc thực thi lệnh** để validate/lấy dữ liệu snapshot.
  3. **API Composition** ở frontend/BFF cho dữ liệu cần "sống".
  4. **Bảng replica `ref_*`** đồng bộ qua event — chỉ khi cần lọc/báo cáo khối lượng lớn theo dữ liệu hiện tại hoặc phải chạy được khi owner sập. Bảng `ref_*` chỉ đọc, chỉ chứa cột cần dùng, giữ nguyên ID gốc, có lệnh backfill.

---

## 2. QUY TẮC SNAPSHOT DỮ LIỆU LỊCH SỬ (DATA SNAPSHOTTING)

Trong các bảng chứng từ giao dịch thương mại (`sales_order_items`, `purchase_order_items`, `delivery_items`):
* **BẮT BUỘC lưu cứng (Snapshot) các thông tin tại thời điểm chốt chứng từ**:
  - `product_name`: Tên sản phẩm lúc mua/bán.
  - `sku`: Mã SKU.
  - `unit_code`: Đơn vị tính (PCS, BOX, KG...).
  - `brand`, `designation`, `com` (xuất xứ) nếu chứng từ in ra các trường này.
  - Ở cấp header chứng từ: `customer_code`, `customer_name`, `tax_code`, `contact_name`, và tên người thực hiện (`created_by_name`, `approved_by_name`, `picker_name`…) — không tra ngược identity để lấy tên.
  - `unit_price` / `gross_price`: Đơn giá niêm yết tại thời điểm ký kết.
  - `unit_cost`: Giá vốn tại thời điểm lập chứng từ.
  - `discount_percent`: Phần trăm chiết khấu.
* **Lý do bắt buộc**: Khi phòng kinh doanh tăng giá sản phẩm trong `catalog-service`, toàn bộ báo cáo doanh thu, hóa đơn và sổ sách kế toán của các đơn hàng trong quá khứ **không được phép bị thay đổi**.

---

## 3. RÀNG BUỘC TOÀN VẸN TRONG QUẢN TRỊ KHO (SCM INVARIANTS)

`inventory-service` lưu tồn kho theo **2 số dư + 2 sổ cái**, mỗi cặp (kho, sản phẩm) một dòng:

| Bảng | Ý nghĩa | Tăng khi | Giảm khi |
|---|---|---|---|
| `inventory_stocks.quantity` | **Tồn thực tế** — hàng đang nằm trong kho | Nhập kho mua hàng, nhận hàng trả | Kho ghi sổ xuất chứng từ bán hàng |
| `inventory_stocks_reservation.quantity` | **Tồn khả dụng** — còn được phép bán | Nhập kho mua hàng, nhả giữ chỗ | Tạo chứng từ bán hàng (giữ hàng) |
| `inventory_stocks_transaction` | Sổ cái tồn thực tế (bất biến) | — | — |
| `inventory_stocks_reservation_transaction` | Sổ cái tồn khả dụng (bất biến) | — | — |

* Hàng đang giữ (đã cam kết, chưa xuất) = tồn thực tế − tồn khả dụng.
* **Bất biến**: cả hai số dư luôn `>= 0`. Thực thi bằng UPDATE có điều kiện (`WHERE quantity >= :qty`), không đọc-rồi-ghi ([architecture.md §10.1](architecture.md#101-zero-overselling--atomic-sql-guard)).
* **Idempotency**: mỗi sổ cái có `UNIQUE (document_type, document_id, warehouse_id, product_id)` — một chứng từ chỉ ghi sổ một lần cho mỗi sản phẩm/kho.

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
