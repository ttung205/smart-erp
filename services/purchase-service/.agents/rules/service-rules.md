# Quy tắc chi tiết — purchase-service

Đọc cùng `../../../../CLAUDE.md` (gốc repo) và `../../CLAUDE.md` (tổng quan service).

## 1. Luồng nghiệp vụ mua hàng

```text
Yêu cầu mua (PR, requisitions)
  → Đơn mua (PO, orders): submit → approve | cancel | close
  → Nhận hàng (GRN, receipts): tạo → confirm → phát GoodsReceived
       → inventory ghi nhận tồn (nhập kho)
  → Hóa đơn NCC (invoices): tạo → match (đối chiếu PO/GRN/Invoice) → post (duyệt)
  → Trả hàng NCC (returns): tạo → submit → approve → dispatch | cancel
Yêu cầu báo giá NCC (inquiries): tạo yêu cầu → gửi NCC → đồng bộ email → hoàn tất → chọn kết quả cho từng dòng
```

## 2. Hợp đồng API

| Controller | Route chính | Hành động | Quyền |
|---|---|---|---|
| `PurchaseRequisitionsController` | `/api/v1/purchase/requisitions` | GET list, GET `/available-for-order`, GET `/{id}`, POST, POST `/{id}/submit`, `/{id}/approve`, `/{id}/reject` | view / create / approve |
| `PurchaseOrdersController` | `/api/v1/purchase/orders` | GET list, GET `/{id}`, POST, POST `/{id}/submit`, `/{id}/approve`, `/{id}/cancel`, `/{id}/close` | view / create / approve |
| `GoodsReceiptsController` | `/api/v1/purchase/receipts` | GET list, GET `/{id}`, POST, POST `/{id}/confirm` | view / create |
| `PurchaseInvoicesController` | `/api/v1/purchase/invoices` | GET list, GET `/{id}`, POST, POST `/{id}/match`, `/{id}/post` | view / create / approve |
| `PurchaseReturnsController` | `/api/v1/purchase/returns` | GET list, GET `/{id}`, POST, PUT `/{id}`, POST `/{id}/submit`, `/approve`, `/dispatch`, `/cancel` | view / create / approve |
| `ItemInquiriesController` | `/api/v1/purchase/inquiries` | GET/POST/PUT yêu cầu và báo giá NCC, POST `/send`, `/sync-email`, `/complete`, `/requests/{id}/items/{itemId}/select-result` | view / create |
| `HealthCheckController` | `/health`, `/api/v1/purchase/health` | GET | — |
| `InternalAnalyticsController` | `/internal/analytics/po-lines` | GET phân trang `after_id`, `limit` (ETL của ai-service) | nội bộ, không qua gateway |
| `InternalAttachmentsController` | `/internal/inquiry-attachments/{id}` | GET tải tệp | nội bộ |

Quy tắc chung:
- Mã lỗi theo `docs/contracts/api-spec.md`: `VALIDATION_FAILED` (422), `RESOURCE_NOT_FOUND` (404), `PERMISSION_DENIED` (403), `CONCURRENT_CONFLICT` (409).
- Envelope `ApiEnvelope`, JSON snake_case.
- Mọi thao tác thay đổi trạng thái chứng từ (approve, confirm, post...) phải kiểm tra trạng thái hiện tại trong Domain, không tin trạng thái từ client.

## 3. Event và Outbox

- Phát: `GoodsReceived` và các event khác được ghi vào `outbox_events` cùng transaction nghiệp vụ. `OutboxRelayBackgroundService` quét `PENDING` và publish qua RabbitMQ.
- Payload nằm ở `Purchase.Application/Common/Events/PurchaseEventPayloads.cs`. Thêm trường thì giữ tương thích (tolerant reader). Phá vỡ thì tăng `version`.
- Consumer ở phía inventory bắt buộc idempotent qua `processed_events`.

## 4. Kiểm thử

- `Purchase.Tests` gồm: `ThreeWayMatchingTests`, `GoodsReceiptTests`, `PurchaseOrderAllocationTests`, `PurchaseRequisitionTests`, `OutboxDispatcherTests`, `OutboxIntegrationTests`, `EndToEndProcurementPipelineTests`, `JwtAuthMiddlewareTests`, `RequirePermissionAttributeTests`, `ApiControllerTests`.
- Thêm nghiệp vụ mới thì thêm test ở đúng tầng: Domain/Application không cần DB; Controller dùng `TestDbContextFactory`.

## 5. Checklist trước khi bàn giao

- [ ] Mỗi action mới có `[RequirePermission]` đúng quyền.
- [ ] Người tạo lấy từ JWT, không nhận từ body.
- [ ] Dòng chứng từ có snapshot tên, SKU, đơn vị, giá.
- [ ] Event đi qua Outbox.
- [ ] Query chỉ đọc có `.AsNoTracking()` và trả DTO.
- [ ] Không có `DateTime.Now`, `.Result`, `.Wait()`.
