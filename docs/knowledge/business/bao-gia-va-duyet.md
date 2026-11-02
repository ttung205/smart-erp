---
title: Lập và duyệt báo giá
group: business
audience: nhân viên kinh doanh, trưởng phòng kinh doanh
status: draft
source: services/sales-service/app/Support/SalesWorkflow.php; services/sales-service/app/Http/Controllers/QuotationController.php (store, update, submitApproval, approve, reject, destroy); services/sales-service/routes/api.php (quotations); docs/bao_cao/02 kịch bản 1 bước 1–2
verified_by: null
verified_at: null
---

# Lập và duyệt báo giá

## Mục đích

Báo giá là bước đầu của chu trình bán hàng B2B. Nhân viên kinh doanh lập báo giá cho khách đại lý với danh sách sản phẩm, đơn giá và chiết khấu thương mại. Báo giá phải được cấp quản lý duyệt trước khi chuyển thành đơn bán hàng.

Báo giá **chưa giữ hàng** trong kho. Hàng chỉ được giữ khi lập chứng từ bán (xem giữ hàng trong bản nháp tương ứng).

## Ai thực hiện

| Thao tác | Quyền cần có |
|---|---|
| Xem danh sách, xem chi tiết | `quotation.view` |
| Lập báo giá mới | `quotation.create` |
| Sửa báo giá | `quotation.update` |
| Gửi duyệt | `quotation.update` |
| Duyệt, từ chối | `quotation.approve` |
| Xóa báo giá | `quotation.cancel` |
| Chuyển thành đơn bán | `order.create` |
| Gửi báo giá cho khách qua email | `quotation.create`, `quotation.update` hoặc `quotation.manage` |

Vai trò tham khảo trong báo cáo: Nhân viên kinh doanh (`SALE`) lập và gửi duyệt; Trưởng phòng kinh doanh (`SALE_MANAGER`) duyệt. Ánh xạ vai trò sang quyền nằm ở Identity, cần admin xác nhận lại.

## Điều kiện

- Khách hàng phải tồn tại trong danh mục đối tác.
- Mỗi dòng sản phẩm phải có số lượng lớn hơn 0.
- Chỉ báo giá ở trạng thái **Nháp** hoặc **Bị từ chối** mới được sửa hoặc xóa.

## Các bước

1. **Lập báo giá.** Nhân viên chọn khách hàng, thêm sản phẩm, nhập số lượng, đơn giá và tỷ lệ chiết khấu. Báo giá được lưu ở trạng thái **Nháp (`DRAFT`)**.
   - Khi lưu, hệ thống chụp lại (snapshot) tên, mã sản phẩm và thông tin khách hàng vào báo giá. Dữ liệu này không đổi theo danh mục sau khi đã lưu.
   - Mỗi dòng có thể được đánh dấu là dòng **khách chọn (thắng)** khi khách hàng xác nhận. Chỉ các dòng này mới được chuyển sang đơn bán.
2. **Gửi duyệt.** Nhân viên bấm "Gửi duyệt". Báo giá chuyển sang **Chờ duyệt (`PENDING_APPROVAL`)**. Lúc này không sửa được báo giá.
3. **Duyệt hoặc từ chối.** Trưởng phòng kinh doanh xem xét:
   - Bấm "Duyệt": báo giá chuyển sang **Đã duyệt (`APPROVED`)**. Hệ thống ghi tên người duyệt và thời điểm duyệt.
   - Bấm "Từ chối": **bắt buộc nhập lý do**. Nếu bỏ trống, hệ thống báo lỗi và không từ chối. Báo giá chuyển sang **Bị từ chối (`REJECTED`)**, lý do được ghi vào ghi chú của báo giá.
4. **Sửa lại khi bị từ chối.** Báo giá bị từ chối được đưa về **Nháp (`DRAFT`)** để nhân viên sửa và gửi duyệt lại.
5. **Gửi báo giá cho khách qua email (tùy chọn).** Nhân viên nhập người nhận (tối đa 50 địa chỉ gồm cả CC), tiêu đề, nội dung và chọn tệp đính kèm thuộc báo giá (tối đa 10 tệp). Hệ thống không tự đổi trạng thái báo giá.
   - Email được gửi qua hàng đợi nền. Trạng thái gửi được theo dõi bằng mã job (`REQUESTED` khi đã nhận yêu cầu).
   - Tệp đính kèm không thuộc báo giá này sẽ bị từ chối (`ATTACHMENT_NOT_FOUND`).
6. **Chuyển thành đơn bán.** Khi báo giá đã duyệt, người có quyền `order.create` bấm "Tạo đơn bán hàng":
   - Chỉ các dòng được đánh dấu thắng được đưa vào đơn. Nếu không có dòng nào, hệ thống báo lỗi và không tạo đơn.
   - Báo giá chuyển sang **Đã chuyển đổi (`CONVERTED`)**.
   - Nếu báo giá đã được chuyển trước đó, hệ thống không tạo đơn thứ hai mà trả về đơn đã có.

## Trạng thái

| Trạng thái | Ý nghĩa | Chuyển được sang |
|---|---|---|
| `DRAFT` | Nháp, đang soạn | `PENDING_APPROVAL` |
| `PENDING_APPROVAL` | Chờ duyệt | `APPROVED`, `REJECTED` |
| `APPROVED` | Đã duyệt, chưa chuyển đơn | `CONVERTED` (khi tạo đơn bán) |
| `REJECTED` | Bị từ chối | `DRAFT` (sửa và gửi lại) |
| `CONVERTED` | Đã chuyển thành đơn bán | không chuyển tiếp |

## Lỗi thường gặp

- **Không gửi duyệt được, báo không có quyền:** thiếu `quotation.update`. Xem [loi-xac-thuc-va-quyen.md](../faq/loi-xac-thuc-va-quyen.md).
- **Không sửa được báo giá:** báo giá đang chờ duyệt, đã duyệt hoặc đã chuyển đơn. Đây là thiết kế: phải chờ trưởng phòng từ chối, hoặc nhờ người có quyền đưa về nháp.
- **Không từ chối được:** chưa nhập lý do. Nhập lý do rồi thử lại.
- **Không tạo được đơn bán từ báo giá:** báo giá chưa ở trạng thái **Đã duyệt**, hoặc không có dòng nào được đánh dấu thắng.

## Chưa xác nhận lại sau khi cập nhật

- **Gửi email báo giá khi báo giá chưa được duyệt:** code hiện không kiểm tra trạng thái báo giá khi gửi email, nên báo giá ở trạng thái nháp vẫn gửi được. Cần người nghiệp vụ quyết định đây là hành vi mong muốn hay phải thêm điều kiện "chỉ gửi báo giá đã duyệt".

## Chưa triển khai

- **Kiểm tra hạn mức tín dụng và nợ quá hạn 30 ngày khi duyệt báo giá:** báo cáo có nêu, nhưng trong code hiện tại luồng duyệt không kiểm tra điều kiện này. Thông tin hạn mức được lưu ở đối tác nhưng chưa dùng để chặn. Không hướng dẫn người dùng theo điều kiện này cho đến khi được triển khai.
