---
title: Lỗi tồn kho và chứng từ
group: faq
audience: nhân viên ERP, admin
status: draft
source: docs/contracts/api-spec.md mục 3; docs/architecture/ARCHITECTURE.md mục 8.3 và 10.4; docs/knowledge/technical/luong-ban-hang-giu-kho.md
verified_by: null
verified_at: null
---

# Lỗi tồn kho và chứng từ

## Vì sao tồn kho không đủ để tạo hóa đơn / giữ hàng?

- **Thông báo:** `INSUFFICIENT_STOCK` (HTTP 409) — "Tồn kho khả dụng không đủ để giữ hàng".
- **Nguyên nhân:** hệ thống kiểm tra **tồn khả dụng**, không phải tồn thực tế. Tồn khả dụng = tồn thực tế trong kho − phần đã giữ cho các chứng từ khác. Nên dù kho còn hàng, hàng có thể đã được giữ cho chứng từ khác.
- **Cách xử lý:**
  1. Kiểm tra số lượng đã giữ của sản phẩm (hàng đang giữ = tồn thực tế − tồn khả dụng).
  2. Giảm số lượng trên chứng từ, hoặc chờ hàng nhập về.
  3. Nếu có chứng từ nháp không còn dùng, hủy chứng từ đó để nhả hàng.
- **Lưu ý:** khi giữ hàng cho cả chứng từ, nếu thiếu một dòng thì **toàn bộ** chứng từ không được giữ hàng. Không có giữ một phần.

## Vì sao tôi gặp lỗi "dữ liệu đã bị người khác sửa"?

- **Thông báo:** `CONCURRENT_CONFLICT` (HTTP 409) — "Tranh chấp dữ liệu đồng thời".
- **Nguyên nhân:** có người khác vừa sửa cùng chứng từ, nên phiên bản bạn đang xem đã cũ.
- **Cách xử lý:** tải lại chứng từ, kiểm tra thay đổi rồi thực hiện lại thao tác.

## Vì sao chứng từ báo "đã ghi sổ" khi tôi thử lại?

- **Thông báo:** `DOCUMENT_ALREADY_POSTED` (HTTP 422) — xảy ra khi gửi lại cùng số chứng từ nhưng khác số lượng.
- **Nguyên nhân:** chứng từ đã được ghi nhận với số lượng khác. Hệ thống không cho ghi đè.
- **Cách xử lý:** không sửa số lượng để gửi lại. Nếu cần thay đổi, tạo chứng từ điều chỉnh hoặc hủy chứng từ cũ theo quy trình.
- **Lưu ý:** gửi lại cùng số chứng từ với cùng số lượng là an toàn: hệ thống bỏ qua, không giữ hàng hai lần.

## Vì sao không giao được vượt số lượng đơn bán?

- **Thông báo:** `OVER_DELIVERY_NOT_ALLOWED` (HTTP 422) — "Số lượng giao vượt quá số lượng trên đơn bán hàng".
- **Nguyên nhân:** số lượng xuất/giao lớn hơn số lượng còn lại chưa giao trên đơn bán.
- **Cách xử lý:** giảm số lượng giao, hoặc điều chỉnh đơn bán nếu khách hàng tăng số lượng (theo quy trình duyệt).

---

**Cần bổ sung:**
- `SAGA_TIMEOUT` (504) được mô tả trong đặc tả là lỗi từ Inventory qua RabbitMQ. Tuy nhiên, theo kiến trúc hiện tại, giữ hàng là lời gọi REST đồng bộ. Cần kiểm tra lại nguồn gốc lỗi này trước khi viết FAQ cho nó.
- Mục "tồn khả dụng" cần đối chiếu với màn hình thực tế để chắc chắn tên hiển thị trên giao diện.
