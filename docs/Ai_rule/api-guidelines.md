# API GUIDELINES — REST Standard & Communication Contracts

> **Mục tiêu**: Chuẩn hóa toàn bộ giao tiếp HTTP REST API giữa Frontend, API Gateway và các Microservices nội bộ (viết bằng PHP Laravel hoặc C# .NET 8).

---

## 1. NGUYÊN TẮC THIẾT KẾ ĐƯỜNG DẪN (URL & ROUTING CONVENTIONS)

### 1.1. Cấu Trúc Tuyến Đường Qua API Gateway (:8000)
Mọi request từ Frontend đều đi qua Gateway theo quy chuẩn:
```text
http://localhost:8000/api/v1/{service-prefix}/{resources}
```

| Service Prefix | Microservice Đích | Cổng Nội Bộ | Ví Dụ Endpoint |
|---|---|---|---|
| `/api/v1/auth/` | `identity-service` (PHP) | :8001 | `POST /api/v1/auth/login` |
| `/api/v1/catalog/` | `catalog-service` (PHP) | :8002 | `GET /api/v1/catalog/products` |
| `/api/v1/purchase/` | `purchase-service` (PHP/C#) | :8003 | `POST /api/v1/purchase/orders` |
| `/api/v1/inventory/` | `inventory-service` (C#) | :8004 | `POST /api/v1/inventory/stocks/reserve` |
| `/api/v1/sales/` | `sales-service` (C#) | :8005 | `POST /api/v1/sales/orders` |
| `/api/v1/eoffice/` | `eoffice-service` (PHP) | :8006 | `POST /api/v1/eoffice/requests` |
| `/api/v1/ai/` | `ai-service` (Python) | :8007 | `POST /api/v1/ai/copilot/query` |

### 1.2. Quy Tắc Đặt Tên Endpoint (RESTful Naming)
* Sử dụng **danh từ số nhiều** cho tài nguyên: `/products`, `/orders`, `/stocks`.
* Sử dụng **kebab-case** cho URL nếu có nhiều từ: `/stock-movements`, `/delivery-requests`.
* Sử dụng HTTP Verbs chuẩn:
  - `GET`: Lấy danh sách hoặc chi tiết.
  - `POST`: Tạo mới tài nguyên hoặc kích hoạt hành động đặc thù (Action Endpoint, ví dụ `/orders/{id}/confirm`).
  - `PUT`: Cập nhật toàn bộ bản ghi.
  - `PATCH`: Cập nhật một phần thuộc tính.
  - `DELETE`: Xóa tài nguyên (Soft-delete).

---

## 2. HEADERS CHUẨN (STANDARD HEADERS)

Mọi HTTP request gửi qua hệ thống bắt buộc phải thiết lập:

| Header | Bắt Buộc | Mô Tả |
|---|---|---|
| `Content-Type` | Có | Luôn là `application/json` |
| `Accept` | Có | Luôn là `application/json` |
| `Authorization` | Có (trừ /login, /health) | `Bearer <JWT_TOKEN>` |
| `X-Correlation-ID` | Khuyên dùng | UUID v4 theo dõi phiên (nếu thiếu, Gateway sẽ tự động sinh và trả về trong Response) |

---

## 3. CẤU TRÚC PHẢN HỒI CHUẨN TOÀN HỆ THỐNG (API ENVELOPE)

Cả service viết bằng **PHP** và **C# (.NET)** bắt buộc phải bọc phản hồi trong Envelope:

### 3.1. Phản Hồi Thành Công (HTTP 200, 201)
```json
{
  "success": true,
  "data": {
    "id": 105,
    "code": "SO-202609-0042",
    "status": "CONFIRMED",
    "total_amount": 45000000.0
  },
  "meta": {
    "correlation_id": "req_corr_01J8ABC123456789",
    "timestamp": 1788998400,
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total_items": 120,
      "total_pages": 8
    }
  }
}
```

### 3.2. Phản Hồi Thất Bại (HTTP 400, 401, 403, 404, 409, 422, 500)
```json
{
  "success": false,
  "error": {
    "code": "INSUFFICIENT_STOCK",
    "message": "Số lượng tồn kho khả dụng không đủ để giữ hàng.",
    "details": {
      "product_id": 201,
      "warehouse_id": 1,
      "requested_quantity": 50,
      "available_quantity": 18
    }
  },
  "meta": {
    "correlation_id": "req_corr_01J8ABC123456789",
    "timestamp": 1788998400
  }
}
```

---

## 4. BẢNG MÃ LỖI NGHIỆP VỤ CHUẨN (STANDARD ERROR CODES)

| Error Code | HTTP Status | Ý Nghĩa Nghiệp Vụ |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Token không hợp lệ, thiếu header hoặc đã hết hạn |
| `PERMISSION_DENIED` | 403 | User thiếu quyền cụ thể (ví dụ thiếu `catalog.create`) |
| `RESOURCE_NOT_FOUND` | 404 | Không tìm thấy bản ghi theo ID |
| `INSUFFICIENT_STOCK` | 409 | Tồn kho khả dụng không đủ |
| `CONCURRENT_CONFLICT` | 409 | Tranh chấp dữ liệu đồng thời (Optimistic Concurrency failed) |
| `VALIDATION_FAILED` | 422 | Dữ liệu đầu vào sai định dạng hoặc thiếu trường bắt buộc |
| `OVER_DELIVERY_NOT_ALLOWED` | 422 | Số lượng xuất vượt quá số lượng trên đơn bán hàng |
| `SAGA_TIMEOUT` | 504 | Quá thời gian chờ phản hồi từ Inventory Service |

---

## 5. QUY CHUẨN FORMAT DỮ LIỆU ĐẦU RA / ĐẦU VÀO (WIRE FORMAT)

* **JSON Keys trên đường truyền (Wire Format)**: Thống nhất **luôn luôn dùng `snake_case`** cho toàn bộ JSON Body (Request & Response) ở cả PHP và C# .NET.
  - Ví dụ: `product_id`, `gross_price`, `total_amount`, `customer_id`.
  - Trong C# .NET: Cấu hình `JsonNamingPolicy.SnakeCaseLower` hoặc sử dụng thuộc tính `[JsonPropertyName("product_id")]`.
* **Định dạng thời gian**: Luôn là chuỗi ISO 8601 UTC: `2026-09-10T14:30:00.000Z`.
* **Định dạng số tiền & số lượng**:
  - Số tiền (Money): Số thực hoặc số nguyên VNĐ (ví dụ: `75000000.00`), không format dấu phẩy `.` hay `,` trên đường truyền API (việc format chuỗi hiển thị thuộc về Frontend).
  - Số lượng (Quantity): Số nguyên không âm (`int >= 0`).
