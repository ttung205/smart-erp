# inventory-service — hướng dẫn cho AI coding agent

Áp dụng toàn bộ quy tắc gốc tại `../../CLAUDE.md`. File này chỉ bổ sung những gì riêng cho service kho.
Chi tiết bất biến và checklist nằm ở `.agents/rules/service-rules.md`.

## Vai trò

- Nguồn sự thật về **tồn kho**: tồn thực tế (`inventory_stocks`) và tồn khả dụng (`inventory_stocks_reservation`).
- Port `8004`, prefix gateway `/api/v1/inventory/`. Sales giữ hàng qua REST (`/reserve/batch`). Nhập kho từ purchase đi qua event `GoodsReceived`. Mọi thay đổi số dư đi qua service này.
- Stack: .NET 10, Clean Architecture 4 project (`Inventory.Api`, `Inventory.Application`, `Inventory.Domain`, `Inventory.Infrastructure`), EF Core + MySQL, RabbitMQ (Outbox + consumer).

## Lệnh thường dùng

```bash
# Chạy test trong container (khuyến nghị, dùng MySQL thật)
scripts/powershell/test.ps1 -Service inventory

# Chạy trực tiếp khi đã có MySQL và cấu hình connection string
dotnet test services/inventory-service/InventoryService.slnx
```

## Quy tắc riêng của service

1. **Không đọc tồn lên bộ nhớ rồi ghi lại.** Mọi biến động dùng `UPDATE ... WHERE quantity >= :qty` (xem `Inventory.Infrastructure/Services/InventoryStockManager.cs`). Số dòng = 0 → `409 INSUFFICIENT_STOCK`.
2. **Chứng từ nhiều dòng** (`reserve/batch`) chạy trong một transaction. Thiếu một dòng thì rollback toàn bộ.
3. **Idempotent theo `(document_type, document_id, warehouse_id, product_id)`.** Cùng chứng từ, khác số lượng → `422 DOCUMENT_ALREADY_POSTED`.
4. **Sổ cái bất biến** (`inventory_stocks_transaction`, `inventory_stocks_reservation_transaction`): chỉ INSERT, không UPDATE/DELETE.
5. **Event đi qua Outbox** (`outbox_events`), consumer ghi `processed_events` cùng transaction.
6. **Query chỉ đọc** có `.AsNoTracking()` và trả DTO trong `Inventory.Application/DTOs`, không trả Entity.
7. **Endpoint `/internal/analytics/*`** chỉ cho ETL của ai-service, không đi qua gateway, không đưa vào API công khai.
8. **Thời gian** dùng `DateTime.UtcNow`. Cấm `DateTime.Now`.

## Việc cần biết trước khi sửa

- Các controller kho dùng `[RequirePermission]` theo mẫu purchase-service. Giữ/nhả hàng cho phép thêm quyền bán hàng (`sale-invoice.create`, `invoice.manage`) vì sales gọi thay người dùng (xem `service-rules.md`).
- Các file `*.db`, `*-wal`, `*-shm` trong `src/Inventory.Api/` là dữ liệu cục bộ, không commit.
