# purchase-service — hướng dẫn cho AI coding agent

Áp dụng toàn bộ quy tắc gốc tại `../../CLAUDE.md`. File này chỉ bổ sung những gì riêng cho service mua hàng.
Chi tiết luồng nghiệp vụ, quyền và checklist nằm ở `.agents/rules/service-rules.md`.

## Vai trò

- Quản lý mua hàng từ đầu đến cuối: yêu cầu mua (PR) → đơn mua (PO) → nhận hàng (GRN) → hóa đơn NCC (đối chiếu 3 bên) → trả hàng NCC, cùng với yêu cầu báo giá (inquiry) gửi nhà cung cấp.
- Port `8003`, prefix gateway `/api/v1/purchase/`.
- Stack: .NET 10, Clean Architecture (`Purchase.Api`, `Purchase.Application`, `Purchase.Domain`, `Purchase.Infrastructure`), EF Core + MySQL, RabbitMQ (Outbox relay), Swashbuckle.

## Lệnh thường dùng

```bash
scripts/powershell/test.ps1 -Service purchase        # test trong container, MySQL thật
dotnet test services/purchase-service/PurchaseService.slnx   # khi đã có MySQL
```

## Quy tắc riêng của service

1. **Quyền theo endpoint.** Mỗi action dùng `[RequirePermission("purchase.view" | "purchase.create" | "purchase.approve")]`. `SUPER_ADMIN` được bỏ qua kiểm tra quyền.
2. **Người tạo lấy từ JWT**, không nhận từ body (`CreatedBy` là `[JsonIgnore]`).
3. **Snapshot dòng hàng và NCC** vào `purchase_order_items`, `goods_receipt_items`, `purchase_invoice_items` (tên, SKU, đơn vị, giá, thuế, chiết khấu).
4. **Đối chiếu 3 bên** (PO – GRN – Invoice) nằm ở `ThreeWayMatchingService`, không nằm trong Controller.
5. **Sự kiện nhập kho** `GoodsReceived` đi qua Outbox (`OutboxService` → `OutboxRelayBackgroundService`). Cấm publish trực tiếp trong Controller.
6. **Thời gian** dùng `DateTime.UtcNow`. Cấm `DateTime.Now`.

## Việc cần biết trước khi sửa

- Có route trùng không prefix (`/orders`, `/receipts`, `/invoices`, `/returns`, `/requisitions`, `/inquiries`, `/api/...`) để tương thích ngược. Không thêm route mới theo kiểu này.
- Tệp đính kèm yêu cầu báo giá nằm trên đĩa cục bộ (`IWebHostEnvironment`), phục vụ nội bộ qua `/internal/inquiry-attachments/{id}`. Ở chế độ HA trên AWS, thư mục này được mount từ EFS (xem `docker-compose.ha.yml`).
- Chuỗi `DateTime` khi thay đổi cần kiểm tra lại serializer snake_case trong `Program.cs`.
