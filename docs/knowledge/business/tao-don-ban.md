---
title: Tạo và duyệt đơn bán hàng
group: business
audience: nhân viên kinh doanh, trưởng phòng kinh doanh
status: verified
source: services/sales-service/app/Support/SalesWorkflow.php; routes/api.php (orders); SalesOrderController; bao_cao/02 kịch bản 1 bước 3–4
verified_by: DuyAnh
verified_at: 2026-11-02
---

# Tạo và duyệt đơn bán hàng

## Mục đích

Đơn bán hàng (SO) là cam kết bán giữa công ty và khách đại lý, được tạo từ một báo giá đã duyệt. Đơn phải qua bước duyệt riêng trước khi được xử lý tiếp.

Đơn bán **chưa giữ hàng**. Giữ hàng xảy ra khi lập chứng từ bán hoặc xuất kho, không phải khi tạo đơn.

## Ai thực hiện

| Thao tác | Quyền cần có |
|---|---|
| Xem đơn | `order.view` |
| Tạo đơn từ báo giá | `order.create` |
| Gửi duyệt đơn | `order.update` |
| Duyệt, từ chối đơn | `order.approve` |
| Hủy đơn | `order.cancel` |

## Điều kiện

- Báo giá nguồn phải ở trạng thái **Đã duyệt (`APPROVED`)**.
- Đơn chỉ được sửa hoặc xóa khi ở trạng thái `DRAFT` hoặc `REJECTED`.

## Các bước

1. **Tạo đơn từ báo giá.** Nhân viên bấm "Tạo đơn bán hàng" trên báo giá đã duyệt. Đơn được tạo ở trạng thái **Nháp (`DRAFT`)**, và báo giá chuyển sang **Đã chuyển đổi (`CONVERTED`)**.
   - Khi tạo đơn, hệ thống chụp lại (snapshot) tên, mã, đơn giá, mã số thuế của khách hàng và sản phẩm vào đơn. Các thông tin này không đổi theo danh mục sau khi đã lưu. Đây là lý do dữ liệu đơn cũ không bị ảnh hưởng khi giá sản phẩm thay đổi.
2. **Gửi duyệt.** Nhân viên bấm "Gửi duyệt". Đơn chuyển sang **Chờ duyệt (`PENDING_APPROVAL`)**.
3. **Duyệt hoặc từ chối.** Trưởng phòng kinh doanh kiểm tra đơn và:
   - Bấm "Phê duyệt": đơn chuyển sang **Đã duyệt (`APPROVED`)**.
   - Bấm "Từ chối": đơn chuyển sang **Bị từ chối (`REJECTED`)**, kèm lý do. Nếu báo giá nguồn đã chuyển đổi, báo giá được đưa về trạng thái nháp để sửa.
4. **Đưa vào xử lý.** Sau khi đã duyệt, đơn chuyển sang **Đang xử lý (`PROCESSING`)** khi bắt đầu lập chứng từ bán và giao hàng. Đơn chuyển sang **Hoàn tất (`COMPLETED`)** khi đã giao đủ.

## Trạng thái

| Trạng thái | Ý nghĩa | Chuyển được sang |
|---|---|---|
| `DRAFT` | Nháp | `PENDING_APPROVAL` |
| `PENDING_APPROVAL` | Chờ duyệt | `APPROVED`, `REJECTED` |
| `APPROVED` | Đã duyệt | `PROCESSING` |
| `REJECTED` | Bị từ chối | `DRAFT` |
| `PROCESSING` | Đang xử lý giao hàng | `COMPLETED` |
| `COMPLETED` | Hoàn tất | không chuyển tiếp |
| `CANCELLED` | Đã hủy | không chuyển tiếp |

## Lỗi thường gặp

- Không tạo được đơn từ báo giá: báo giá chưa ở trạng thái đã duyệt.
- Không sửa được đơn đang chờ duyệt: thiết kế hệ thống. Cần từ chối hoặc để trưởng phòng đưa về nháp.
- Duyệt đơn báo không có quyền: thiếu `order.approve`.

## Chưa xác minh

- Hệ thống có kiểm tra hạn mức tín dụng và nợ quá hạn khi duyệt đơn hay không. Báo cáo nêu điều kiện này, nhưng chưa thấy trong code duyệt đơn.
- Trạng thái `PROCESSING` và `COMPLETED` được chuyển khi nào, bằng thao tác nào trên giao diện: cần xác nhận với người vận hành.
