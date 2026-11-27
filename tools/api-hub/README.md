# api-hub — tài liệu API tổng hợp

Một trang Swagger UI gộp API công khai của 4 service: **inventory**, **purchase**, **chat**, **ai** (125 endpoint, qua gateway `:8000`).

## Dùng

```bash
python tools/api-hub/build_openapi.py   # sinh lại openapi.json và index.html
```

Mở `tools/api-hub/index.html` bằng trình duyệt. Trang nhúng sẵn đặc tả nên không cần chạy server. Swagger UI tải từ CDN (unpkg), cần internet lần đầu.

- `openapi.json`: đặc tả OpenAPI 3.0.3 để import vào Postman hoặc Insomnia.
- Nhấn **Authorize** và nhập JWT (`Bearer <token>`) để thử endpoint.

## Nguồn sự thật và cách cập nhật

Đặc tả được viết tay trong bảng `OPERATIONS` của `build_openapi.py`, đối chiếu với route attribute và tên trường JSON trong source tại thời điểm viết. Khi thêm hoặc đổi route trong service:

1. Sửa dòng tương ứng trong `OPERATIONS` (và schema trong `SCHEMAS` nếu đổi body).
2. Chạy lại script.

Các phần còn đơn giản hóa:
- Body của nhiều endpoint purchase và chat dùng `GenericPurchaseDto` hoặc tham số form. Cấu trúc chi tiết xem trong DTO của service.
- Phần `data` của envelope mô tả chung, không liệt kê từng DTO trả về.
- Không đưa endpoint `/internal/*` (ETL giữa các service) vào tài liệu công khai.

Để đối chiếu với service đang chạy, có thể so với `GET /swagger/v1/swagger.json` (inventory, purchase, chat, bật ở môi trường Development) và `GET /openapi.json` (ai-service).
