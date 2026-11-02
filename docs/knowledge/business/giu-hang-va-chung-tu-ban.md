---
title: Lập chứng từ bán và giữ hàng
group: business
audience: nhân viên kinh doanh, nhân viên kho vận
status: verified
source: services/sales-service InvoiceController (sendToWarehouse, syncReserve, releaseStockForInvoice); docs/architecture/ARCHITECTURE.md §8.3; bao_cao/02 kịch bản 1 bước 5–7
verified_by: DuyAnh
verified_at: 2026-11-02
---

# Lập chứng từ bán và giữ hàng

## Mục đích

Chứng từ bán (hóa đơn bán hàng nội bộ, trong code gọi là `invoice`) là bước gắn đơn bán với hàng thực tế trong kho. Khi lập chứng từ, hệ thống **giữ hàng** cho chứng từ đó: số lượng khả dụng giảm đi, và hàng không thể bán cho chứng từ khác. Giữ hàng là việc đồng bộ, nếu kho không đủ hàng thì chứng từ không được lập.

## Ai thực hiện

| Thao tác | Quyền cần có |
|---|---|
| Lập chứng từ | `sale-invoice.create` |
| Gửi sang kho, đồng bộ giữ hàng, hủy, gán vận chuyển | `invoice.manage` |
| Xem chứng từ | `invoice.view` |

## Điều kiện

- Đơn bán đã ở trạng thái **Đã duyệt** hoặc **Đang xử lý** (xem [tao-don-ban.md](tao-don-ban.md)).
- Chứng từ không ở trạng thái đã hủy.
- Kho còn đủ **tồn khả dụng** cho từng dòng hàng. Tồn khả dụng là số lượng còn được phép bán, không phải tồn thực tế trong kho.

## Các bước

1. **Lập chứng từ.** Từ đơn đã duyệt, nhân viên tạo chứng từ bán. Mỗi chứng từ có số riêng theo quy tắc `INV-{mã khách}-{ddmmyy}`, nếu trùng trong cùng ngày thì thêm hậu tố `-A`, `-B`.
2. **Giữ hàng.** Hệ thống gọi kho để giữ đồng loạt toàn bộ dòng hàng của chứng từ trong một lần:
   - Nếu **đủ hàng cho tất cả dòng**: toàn bộ số lượng được giữ. Tồn khả dụng giảm, chứng từ ghi nhận đã giữ hàng (`RESERVED`).
   - Nếu **thiếu hàng ở bất kỳ dòng nào**: không dòng nào được giữ, chứng từ không được lập. Hệ thống báo lỗi kèm mặt hàng thiếu.
3. **Gửi sang kho.** Sau khi giữ hàng thành công, chứng từ được gửi sang kho để chuẩn bị hàng (`SENT_TO_WAREHOUSE`).
4. **Gán vận chuyển** (nếu có). Điều phối gán đơn vị vận chuyển cho chứng từ.

## Trạng thái

Chứng từ có hai trạng thái song song:

| Trường | Giá trị | Ý nghĩa |
|---|---|---|
| Giữ hàng (`inventory_reservation_status`) | `RESERVED` | Đã giữ hàng cho chứng từ |
| | `RELEASED` | Đã nhả hàng, thường do hủy chứng từ |
| | `FAILED` | Lần giữ hàng gần nhất thất bại, chứng từ chưa được giữ hàng |
| Kho (`warehouse_status`) | `SENT_TO_WAREHOUSE` | Đã gửi sang kho |
| | `DELIVERED` | Đã xuất kho giao hàng |
| | `CANCELLED` | Đã hủy |

Chỉ được xuất kho khi chứng từ đã ở trạng thái `RESERVED`.

## Lỗi thường gặp

- **Lỗi 409 `INSUFFICIENT_STOCK`** — "Số lượng tồn kho khả dụng không đủ để giữ hàng". Kho không đủ hàng khả dụng cho ít nhất một dòng. Cách xử lý: kiểm tra tồn khả dụng của từng sản phẩm, điều chỉnh số lượng trên đơn, hoặc chờ nhập thêm hàng rồi lập lại chứng từ. Không có chứng từ nào được tạo ra trong trường hợp này.
- **Lỗi 409 `INVOICE_CANCELLED`** — chứng từ đã bị hủy, không thể thao tác tiếp. Lập chứng từ mới nếu cần bán.
- **Lỗi 503 `DEPENDENCY_UNAVAILABLE`** — kho không phản hồi. Chứng từ không được lập. Thử lại sau; nếu lỗi kéo dài, báo quản trị viên.
- **Chứng từ báo "lỗi giữ hàng" nhưng hàng vẫn bị khóa:** trường hợp hiếm khi kết nối bị đứt sau khi kho đã giữ. Hệ thống tự động nhả hàng sau 5 phút qua tiến trình đối soát `sales:reconcile-reservations`. Không cần thao tác thủ công.

## Đã đối chiếu với code

- Giữ hàng xảy ra khi tạo chứng từ (`InvoiceController`, dòng 225) và khi gọi đồng bộ giữ hàng (`syncReserve`). Báo cáo nói giữ khi xuất kho là không khớp code.
- Thiếu hàng trả HTTP 409 `INSUFFICIENT_STOCK`, ánh xạ trong `GlobalExceptionHandlingMiddleware` của inventory-service. Báo cáo ghi 422 là không khớp code.

## Chưa xác minh

- Có cho phép lập nhiều chứng từ từ một đơn bán hay không, và giới hạn số lượng trên mỗi chứng từ: chưa xác minh.
