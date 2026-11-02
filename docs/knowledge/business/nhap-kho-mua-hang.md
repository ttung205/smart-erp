---
title: Mua hàng và nhập kho
group: business
audience: nhân viên thu mua, thủ kho, kế toán kho
status: verified
source: services/purchase-service/src (PurchaseOrderService.cs, GoodsReceiptService.cs, enums); bao_cao/02 kịch bản 2
verified_by: DuyAnh
verified_at: 2026-11-02
---

# Mua hàng và nhập kho

## Mục đích

Mua hàng là cách công ty bổ sung hàng vào kho. Quy trình gồm: lập đơn mua, duyệt, nhận hàng thực tế qua phiếu nhập kho, rồi tăng tồn kho. Phần đối chiếu hóa đơn nhà cung cấp là bước riêng.

## Ai thực hiện

Báo cáo nêu vai trò: nhân viên thu mua (`PURCHASE`) lập và gửi đơn; giám đốc thu mua duyệt; thủ kho (`LOGISTIC`) lập phiếu nhập; kế toán kho (`FINANCE`) đối chiếu. Ánh xạ sang quyền cụ thể của purchase-service chưa được liệt kê trong bản nháp này và cần xác nhận với admin.

## Đơn mua hàng (PO)

### Điều kiện lập

- Phải chọn nhà cung cấp hợp lệ và kho nhận hàng hợp lệ.
- Phải có ít nhất 1 dòng hàng.
- Mỗi dòng có số lượng đặt lớn hơn 0, đơn giá không âm.

### Các bước

1. **Lập đơn.** Thu mua tạo đơn cho nhà cung cấp, chọn kho nhận và các dòng hàng. Đơn ở trạng thái **Nháp (`Draft`)**. Đơn có thể lập từ yêu cầu mua (PR) đã duyệt; số lượng trên đơn không được vượt số lượng còn lại của PR.
2. **Gửi duyệt.** Đơn chuyển sang **Chờ duyệt (`PendingApproval`)**.
3. **Duyệt.** Người có thẩm quyền duyệt, đơn chuyển sang **Đã duyệt (`Approved`)**. Chỉ đơn đã duyệt mới được nhận hàng.
4. **Nhận hàng.** Khi xe giao hàng tới, thủ kho lập phiếu nhập (xem phần dưới). Khi đã nhận đủ, đơn chuyển sang **Đã nhận đủ (`Received`)**; nếu mới nhận một phần, đơn chuyển sang **Nhận một phần (`PartiallyReceived`)**.
5. **Hoàn tất hoặc hủy.** Đơn có thể được đóng (`Completed`) hoặc hủy (`Cancelled`).

## Phiếu nhập kho (GRN)

### Điều kiện lập

- Đơn mua phải đang ở trạng thái **Đã duyệt** hoặc **Nhận một phần**.
- Mỗi dòng phải nhận hoặc trả lại số lượng lớn hơn 0.
- Tổng số lượng thực nhận và trả lại của một dòng **không được vượt** số lượng còn lại chưa nhận của dòng đó trên đơn.

### Các bước

1. **Lập phiếu nháp.** Thủ kho chọn đơn, nhập số lượng thực nhận và số lượng trả lại từng dòng (hàng hỏng, sai quy cách). Phiếu ở trạng thái **Nháp (`Draft`)**.
2. **Xác nhận phiếu.** Khi thủ kho xác nhận:
   - số lượng thực nhận được cộng vào đơn mua; đơn chuyển sang "đã nhận đủ" hoặc "nhận một phần" tùy kết quả;
   - nếu có dòng trả lại, hệ thống **tự tạo phiếu trả hàng mua ở trạng thái nháp** cho số lượng đó;
   - phiếu được gửi sang kho để tăng tồn kho.
3. **Tăng tồn kho.** Kho ghi nhận hàng nhập và tăng tồn thực tế cùng tồn khả dụng. Việc này được thực hiện qua sự kiện riêng nên có độ trễ ngắn; trạng thái đồng bộ của phiếu là **Chờ đồng bộ (`PendingSync`)**, **Đã đồng bộ (`Synced`)** hoặc **Lỗi (`Failed`)**.

### Trạng thái phiếu nhập

| Trạng thái | Ý nghĩa |
|---|---|
| `Draft` | Đang soạn, có thể sửa |
| `Confirmed` | Đã xác nhận nhập |
| `Rejected` | Bị từ chối |

## Lỗi thường gặp

- **`INVALID_PO_STATUS`** — không lập được phiếu nhập vì đơn chưa duyệt hoặc đã nhận đủ. Kiểm tra trạng thái đơn.
- **`OVER_RECEIPT_NOT_ALLOWED`** — số lượng nhận vượt số còn lại trên đơn. Kiểm tra số đã nhận trước đó; điều chỉnh số lượng, hoặc nhờ thu mua tăng số lượng đặt nếu nhà cung cấp giao thừa có chủ đích.
- **`EMPTY_ITEMS`**, **`INVALID_QUANTITY`** — thiếu dòng hàng hoặc số lượng không hợp lệ.
- **`NO_GOODS_RECEIVED`** — phiếu không có hàng thực nhận để xác nhận.
- **Tồn kho chưa tăng sau khi xác nhận phiếu:** trạng thái đồng bộ có thể còn "Chờ đồng bộ". Đợi và kiểm tra lại; nếu "Lỗi", báo quản trị viên.

## Đối chiếu ba bên (chưa đầy đủ)

Báo cáo mô tả đối chiếu ba bên: số lượng trên đơn, số thực nhận trên phiếu và đơn giá trên hóa đơn nhà cung cấp. Nếu lệch, hệ thống tạo biên bản sai lệch và chỉ nhập số đã xác nhận. Code có trạng thái đối chiếu (`PENDING`, `MATCHED`) nhưng bản nháp này **chưa xác minh** luồng biên bản sai lệch và cách xử lý khi lệch.

## Chưa xác minh

- Ánh xạ vai trò nghiệp vụ sang quyền cụ thể của purchase-service.
- Luồng biên bản sai lệch và người có quyền xử lý.
- Quy trình duyệt đơn mua có nhiều cấp hay không.
