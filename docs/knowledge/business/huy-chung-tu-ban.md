---
title: Hủy chứng từ bán
group: business
audience: nhân viên kinh doanh, điều phối kho vận, kế toán bán hàng
status: verified
source: services/sales-service InvoiceController::cancel; SalesWorkflow.php; docs/architecture/ARCHITECTURE.md §8.4
verified_by: DuyAnh
verified_at: 2026-11-02
---

# Hủy chứng từ bán

## Mục đích

Hủy chứng từ bán khi giao dịch không còn hiệu lực, ví dụ khách đổi ý trước khi nhận hàng hoặc lập sai chứng từ. Hủy giải phóng hàng đã giữ để bán cho chứng từ khác.

Hủy chỉ được làm **trước khi xuất kho**. Sau khi đã xuất, phải dùng nghiệp vụ trả hàng (quy trình hàng trả đang soạn, chưa có trong business).

## Ai thực hiện

| Thao tác | Quyền cần có |
|---|---|
| Hủy chứng từ | `invoice.manage` |

## Điều kiện

- Chứng từ **chưa xuất kho**: không có xác nhận xuất kho, không ghi sổ kế toán.
- Chứng từ **chưa ở trạng thái đã hủy**. Nếu đã hủy trước đó, hệ thống trả về thông báo đã hủy và không làm gì thêm.
- **Bắt buộc có lý do hủy**, tối đa 1000 ký tự.

## Các bước

1. Người có quyền mở chứng từ và chọn "Hủy chứng từ", nhập lý do.
2. Hệ thống kiểm tra chứng từ có đã xuất kho hay đã ghi sổ chưa. Nếu đã xuất, dừng và báo lỗi (xem bên dưới).
3. Hệ thống **nhả toàn bộ hàng đã giữ** cho chứng từ, trả lại tồn khả dụng cho kho.
4. Chứng từ chuyển sang trạng thái đã hủy, ghi nhận người hủy, thời điểm và lý do. Lệnh điều phối của chứng từ cũng chuyển sang đã hủy.
5. Yêu cầu giao hàng liên quan được đóng:
   - phần chưa giao được đưa về 0;
   - nếu đã giao một phần trước đó, yêu cầu chuyển sang **Đã giao (`DELIVERED`)**;
   - nếu chưa giao gì, yêu cầu chuyển sang **Đã hủy (`CANCELLED`)**.
   - Số lượng chưa giao trên đơn bán được trả lại, nên có thể lập yêu cầu giao mới.

Nếu bước nhả hàng với kho không thành công, chứng từ **không** bị hủy, và có thể thử lại.

## Lỗi thường gặp

- **Lỗi 409 `INVOICE_ALREADY_ISSUED`** — "Chứng từ đã xuất kho, không thể hủy. Hãy lập yêu cầu trả hàng." Chuyển sang quy trình trả hàng.
- **Lỗi 409 `INVOICE_CANCELLED`** — chứng từ đã bị hủy trước đó.
- **Hủy xong nhưng hàng vẫn hiển thị là đã giữ:** chưa xác minh. Kiểm tra lại tồn khả dụng; nếu vẫn sai, báo quản trị viên và chạy đối soát giữ hàng.

## Chưa xác minh

- Nếu việc nhả hàng với kho lỗi, hệ thống báo lỗi nào cho người dùng: chưa xác minh.
- Người dùng có thể hủy chứng từ đã gửi sang kho hay không: code cho phép hủy khi chưa xuất, nhưng cần xác nhận quy trình thực tế với thủ kho.
