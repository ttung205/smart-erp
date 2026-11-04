---
title: Xuất kho và giao hàng
group: business
audience: điều phối kho vận, thủ kho
status: draft
source: services/sales-service SalesWorkflow.php (delivery); routes/api.php (deliveries, warehouse-post); docs/architecture/ARCHITECTURE.md §8.1, §10.4; bao_cao/02 kịch bản 3
verified_by: null
verified_at: null
---

# Xuất kho và giao hàng

## Mục đích

Xuất kho là bước hàng rời khỏi kho để giao cho khách. Sau khi xuất, tồn thực tế trong kho giảm, và khoản bán được ghi nhận để xuất hóa đơn và công nợ. Chỉ hàng đã được giữ cho chứng từ mới được xuất.

## Ai thực hiện

| Thao tác | Quyền cần có |
|---|---|
| Tạo yêu cầu giao hàng | `delivery.create` |
| Duyệt, cập nhật trạng thái yêu cầu giao | `delivery.manage` |
| Ghi sổ xuất kho | `inventory.issue` hoặc `invoice.manage` |

## Điều kiện

- Chứng từ bán đã được giữ hàng (`RESERVED`), xem [giu-hang-va-chung-tu-ban.md](giu-hang-va-chung-tu-ban.md).
- Số lượng giao không được vượt số lượng còn lại trên đơn bán.

## Các bước

1. **Tạo yêu cầu giao hàng.** Từ đơn bán đã duyệt, nhân viên tạo yêu cầu giao: chọn kho xuất và số lượng từng dòng. Yêu cầu ở trạng thái **Chờ duyệt (`PENDING`)**.
2. **Duyệt yêu cầu.** Người có quyền `delivery.manage` duyệt. Yêu cầu chuyển sang **Đã duyệt (`APPROVED`)**, hoặc **Bị từ chối (`REJECTED`)** kèm lý do.
3. **Gửi sang kho.** Chứng từ bán được gửi sang kho để soạn hàng.
4. **Soạn, đóng gói, xác nhận xuất.** Thủ kho kiểm đếm hàng thực tế rồi xác nhận xuất kho. Hệ thống:
   - giảm **tồn thực tế** của kho;
   - giảm **tồn đã giữ** tương ứng với chứng từ;
   - chuyển trạng thái kho của chứng từ sang **đã giao (`DELIVERED`)**.
5. **Cập nhật tiến độ giao.** Nếu giao một phần, yêu cầu chuyển sang **Giao một phần (`PARTIAL`)**. Khi giao đủ, yêu cầu chuyển sang **Đã giao đủ (`DELIVERED`)**. Một yêu cầu có thể đi qua **Đã xuất hóa đơn (`INVOICED`)** trước khi giao.

## Trạng thái yêu cầu giao

| Trạng thái | Chuyển được sang |
|---|---|
| `PENDING` | `APPROVED`, `REJECTED` |
| `APPROVED` | `INVOICED`, `PARTIAL`, `DELIVERED` |
| `INVOICED` | `PARTIAL`, `DELIVERED` |
| `PARTIAL` | `DELIVERED` |
| `DELIVERED` | không chuyển tiếp |
| `REJECTED` | không chuyển tiếp |
| `CANCELLED` | không chuyển tiếp; đặt bởi thao tác hủy chứng từ |

## Lỗi thường gặp

- **Giao vượt số lượng trên đơn:** mã `OVER_DELIVERY_NOT_ALLOWED` (422) được nêu trong đặc tả API, nhưng chưa thấy trong code sales-service hay inventory-service. Thông báo thực tế khi gặp lỗi này cần được ghi lại trước khi đưa vào FAQ. Kiểm tra số lượng còn lại chưa giao trên đơn.
- **Không xác nhận được xuất kho, báo chứng từ chưa giữ hàng:** chứng từ chưa ở trạng thái `RESERVED`. Quay lại bước giữ hàng.
- **Không xác nhận được xuất kho, báo không có quyền:** thiếu `inventory.issue` hoặc `invoice.manage`.

## Đã đối chiếu với code

- Sau khi xác nhận xuất kho, trạng thái kho của chứng từ chuyển sang `DELIVERED` (`InvoiceController`, dòng 531). inventory-service ghi sự kiện `GoodsIssued` với routing key `inventory.goods.issued` (`StocksController`, dòng 588).
- Báo cáo ghi `inventory.goods.shipped` và `SHIPPED` là không khớp code. Bản nháp dùng tên trong code.

## Chưa xác minh

- **Sự kiện xuất kho có đến được bộ phận khác không:** kiến trúc ghi nhận routing key không khớp với binding đang cấu hình, nên sự kiện có thể không đến Sales hoặc Notification. Đây là lỗi hệ thống, chưa phải quy trình nghiệp vụ. Cần kiểm tra trước khi hứa với người dùng là họ sẽ nhận thông báo.
- Điều kiện xuất kho khi có nhiều kho: chưa xác minh.
