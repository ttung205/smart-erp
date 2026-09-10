# Đặc Tả Hợp Đồng Giao Tiếp API (REST API Specification)

Tài liệu này quy định cấu trúc Request/Response, quy tắc truyền định danh và mã lỗi thống nhất cho toàn bộ hệ thống qua API Gateway.

---

## 1. Headers Chuẩn (Standard Headers)

Mọi HTTP request gửi qua API Gateway hoặc gọi liên service (Internal S2S) đều phải có:

| Header | Bắt buộc | Mô tả |
|---|---|---|
| `Content-Type` | Có | Luôn là `application/json` |
| `Accept` | Có | Luôn là `application/json` |
| `Authorization` | Có (trừ login/public) | `Bearer <JWT_TOKEN>` |
| `X-Correlation-ID` | Không bắt buộc ở Client | Mã phiên duy nhất (nếu client không truyền, Gateway sẽ tự sinh và trả về trong response) |

---

## 2. Cấu Trúc Envelope Phản Hồi (API Envelope)

### 2.1. Phản Hồi Thành Công (Success Response)
HTTP Status: `200 OK`, `201 Created`

```json
{
  "success": true,
  "data": {
    "id": 101,
    "code": "SO-202609-0001",
    "status": "CONFIRMED"
  },
  "meta": {
    "correlation_id": "req_corr_987654321",
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

### 2.2. Phản Hồi Thất Bại (Error Response)
HTTP Status: `400 Bad Request`, `401 Unauthorized`, `403 Forbidden`, `404 Not Found`, `409 Conflict`, `422 Unprocessable Entity`, `500 Internal Server Error`

```json
{
  "success": false,
  "error": {
    "code": "INSUFFICIENT_STOCK",
    "message": "Số lượng tồn kho khả dụng không đủ để hoàn thành giao dịch.",
    "details": {
      "product_id": 201,
      "warehouse_id": 1,
      "requested": 50,
      "available": 20
    }
  },
  "meta": {
    "correlation_id": "req_corr_987654321",
    "timestamp": 1788998400
  }
}
```

---

## 3. Bảng Mã Lỗi Chuẩn Toàn Hệ Thống

| Error Code | HTTP Status | Ý nghĩa nghiệp vụ |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Token không hợp lệ hoặc đã hết hạn |
| `PERMISSION_DENIED` | 403 | User thiếu quyền cụ thể (ví dụ: thiếu `sales.approve`) |
| `RESOURCE_NOT_FOUND` | 404 | Không tìm thấy bản ghi theo ID |
| `INSUFFICIENT_STOCK` | 409 | Tồn kho Available không đủ để giữ hàng |
| `CONCURRENT_CONFLICT` | 409 | Tranh chấp dữ liệu đồng thời (Optimistic lock / Atomic check failed) |
| `VALIDATION_FAILED` | 422 | Dữ liệu đầu vào sai định dạng hoặc thiếu trường bắt buộc |
| `OVER_DELIVERY_NOT_ALLOWED` | 422 | Số lượng giao vượt quá số lượng trên đơn bán hàng (SO) |
| `SAGA_TIMEOUT` | 504 | Quá hạn chờ phản hồi từ Inventory Service qua RabbitMQ |

---

## 4. Nguyên Tắc Tham Chiếu Liên Dịch Vụ (Logical Reference)

Vì hệ thống áp dụng **Database-per-Service**, không có bất kỳ Foreign Key ràng buộc giữa các DB:

1. **Khóa liên dịch vụ chỉ là ID logic**:
   - `sales_orders.customer_id` chỉ lưu integer/UUID đại diện cho Customer ở `Catalog Service`.
   - `inventory_stocks.product_id` chỉ lưu integer/UUID đại diện cho Product ở `Catalog Service`.
2. **Không join chéo DB**:
   - Khi cần hiển thị thông tin tổng hợp (ví dụ chi tiết SO kèm tên sản phẩm và tên khách hàng), Frontend hoặc Gateway sẽ gọi tuần tự hoặc Backend sẽ query qua REST API nội bộ / Data Snapshot.
3. **Data Snapshotting**:
   - Tại bảng `sales_order_items`: Lưu cứng snapshot `product_name`, `sku`, `gross_price`, `discount_percent`, `unit_price` tại thời điểm ký kết đơn hàng.
