# Quy tắc chi tiết — inventory-service

Đọc cùng `../../../../CLAUDE.md` (gốc repo) và `../../CLAUDE.md` (tổng quan service).

## 1. Bất biến tồn kho

| Bảng | Ý nghĩa | Tăng khi | Giảm khi |
|---|---|---|---|
| `inventory_stocks.quantity` | Tồn thực tế | Nhập kho mua, nhận hàng trả | Ghi sổ xuất chứng từ bán |
| `inventory_stocks_reservation.quantity` | Tồn khả dụng | Nhập kho mua, nhả giữ chỗ | Giữ hàng cho chứng từ bán |
| `inventory_stocks_transaction` | Sổ cái tồn thực tế (bất biến) | — | — |
| `inventory_stocks_reservation_transaction` | Sổ cái tồn khả dụng (bất biến) | — | — |

- Hàng đang giữ = tồn thực tế − tồn khả dụng.
- Cả hai số dư luôn `>= 0`.
- Mỗi cặp (kho, sản phẩm) một dòng.

Mẫu SQL bắt buộc:

```sql
UPDATE inventory_stocks_reservation
SET quantity = quantity - :qty, updated_at = :now
WHERE warehouse_id = :warehouse_id AND product_id = :product_id
  AND quantity >= :qty;
```

## 2. Hợp đồng API

| Controller | Route | Hành động |
|---|---|---|
| `StocksController` | `/api/v1/inventory/stocks` | GET danh sách, GET `/{productId}`, POST `/change`, `/reserve`, `/reserve/batch`, `/release`, `/release/document`, `/receive`, `/issue`; GET `/movements` (alias `/transactions`), `/reservations/orders`, `/reservations/documents` |
| `ReservationsController` | `/api/v1/inventory/reservations` | GET danh sách, POST `/change`, GET `/transactions` |
| `WarehousesController` | `/api/v1/inventory/warehouses` | GET, POST, PUT `/{id}`, DELETE `/{id}` (soft-delete) |
| `SalesReturnsController` | `/api/v1/inventory/sales-returns` | POST `/receive`, POST `/transfer` |
| `InternalAnalyticsController` | `/internal/analytics` | GET `/stock-balances`, `/reservation-balances`, `/stock-moves` (phân trang `after_id`, `limit`) |
| `HealthController` | `/health`, `/api/v1/inventory/health` | GET |

Quy tắc chung:
- Mã lỗi nghiệp vụ dùng bảng chuẩn trong `docs/contracts/api-spec.md`: `INSUFFICIENT_STOCK` (409), `DOCUMENT_ALREADY_POSTED` (422), `VALIDATION_FAILED` (422), `RESOURCE_NOT_FOUND` (404).
- Envelope `ApiEnvelope` (xem `Inventory.Application/Common/Models/ApiEnvelope.cs`), JSON snake_case.
- Số lượng là số nguyên dương ở các thao tác giữ/nhả/nhập/xuất. Chỉ `/change` nhận `quantity_delta` có dấu.

## 3. Giữ hàng cho chứng từ bán

- Sales gọi **một lần** `POST /reserve/batch` với `order_id` là số chứng từ.
- Kết quả không chắc (timeout, 5xx) phía sales → sales gọi `POST /release/document`. Thiếu hàng (409) là kết quả chắc chắn, không cần nhả.
- Hủy chứng từ: `POST /release/document`. Chỉ hủy khi chưa xuất kho.

## 4. Event và Outbox

- Phát: `outbox_events` ghi cùng transaction với nghiệp vụ. Relay (`OutboxPublisherWorker`) quét `PENDING`, publish qua RabbitMQ, đánh `PUBLISHED`.
- Nhận: `RabbitMqConsumerWorker` → `EventConsumerHandler`. Ghi `(event_id, consumer_name)` vào `processed_events` trong cùng transaction; trùng thì ack và bỏ qua.
- Không publish trực tiếp trong Controller hay Action.

## 5. Phân quyền

- Chuẩn: kiểm tra quyền tại service owner theo permission trong JWT (CLAUDE.md gốc §8).
- Attribute `[RequirePermission(...)]` (`Inventory.Api/Authorization`) nhận danh sách quyền, đủ một trong số đó là được. `SUPER_ADMIN` và quyền `*.manage` cùng module được mọi quyền con. Lời gọi nội bộ (`X-Internal-Service-Key`) được bỏ qua.
- Bảng quyền theo action (`Perm` trong `RequirePermissionAttribute.cs`):

| Action | Quyền (một trong các quyền) |
|---|---|
| GET stocks, reservations, warehouses, movements | `inventory.view` |
| POST stocks/change, reservations/change, warehouses (POST/PUT/DELETE) | `inventory.manage` |
| POST stocks/reserve, reserve/batch | `inventory.manage`, `sale-invoice.create`, `invoice.manage` |
| POST stocks/release, release/document | `inventory.manage`, `sale-invoice.create`, `invoice.manage` |
| POST stocks/receive | `inventory.receive` |
| POST stocks/issue | `inventory.issue`, `invoice.manage` |
| POST sales-returns/receive | `inventory.receive`, `sale_return.technical` |
| POST sales-returns/transfer | `inventory.transfer`, `sale_return.post` |

- Lưu ý khi triển khai: vai trò SALE trong identity hiện **không** có `sale-invoice.create`. Nếu chỉ SALE tạo đơn, giữ hàng sẽ bị 403. Cần thống nhất quyền với nghiệp vụ trước khi đưa lên môi trường thật.
- Test: `RequirePermissionAttributeTests.cs` kiểm tra từng action khai báo đúng quyền.

## 6. Kiểm thử

- `Inventory.Domain.UnitTests`: quy tắc miền, `ResourceLockExtensions`.
- `Inventory.Infrastructure.Tests`: đồng thời (`InventoryConcurrencyTests`), phục hồi giữ hàng (`DocumentReservationRecoveryTests`), outbox (`OutboxReliabilityTests`).
- `Inventory.Api.IntegrationTests`: API thật qua `WebApplicationFactory`.
- Khi thêm logic tồn kho, phải có test đồng thời: hai luồng cùng giữ một sản phẩm không được làm tồn âm.

## 7. Checklist trước khi bàn giao

- [ ] Không có `DateTime.Now`, `.Result`, `.Wait()`.
- [ ] Query chỉ đọc có `.AsNoTracking()` và trả DTO.
- [ ] Biến động tồn dùng UPDATE có điều kiện, có test đồng thời.
- [ ] Sổ cái chỉ INSERT.
- [ ] Lỗi mới đã cập nhật `docs/contracts/api-spec.md` (chỉ khi có quyết định kiến trúc).
- [ ] JSON snake_case, envelope đúng.
