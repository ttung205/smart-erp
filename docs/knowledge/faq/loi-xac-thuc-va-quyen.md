---
title: Lỗi đăng nhập và phân quyền
group: faq
audience: nhân viên ERP, admin
status: draft
source: docs/contracts/api-spec.md mục 3; docs/knowledge/technical/kien-truc-tong-quan.md mục 10.1
verified_by: null
verified_at: null
---

# Lỗi đăng nhập và phân quyền

## Tại sao tôi bị đăng xuất hoặc báo "chưa xác thực"?

- **Thông báo:** `UNAUTHENTICATED` (HTTP 401) — "Token không hợp lệ hoặc đã hết hạn".
- **Nguyên nhân:** phiên đăng nhập (JWT) đã hết hạn, hoặc token không đi kèm yêu cầu.
- **Cách xử lý:** đăng nhập lại. Nếu lỗi lặp lại ngay sau khi đăng nhập, báo quản trị viên kiểm tra thời gian sống của token và đồng hồ hệ thống.
- **Ghi chú kỹ thuật:** hệ thống hiện chưa có cơ chế làm mới token. Token có thời hạn cố định.

## Tại sao tôi không thực hiện được thao tác (ví dụ: duyệt đơn bán)?

- **Thông báo:** `PERMISSION_DENIED` (HTTP 403) — "Người dùng thiếu quyền cụ thể", ví dụ thiếu `sales.approve`.
- **Nguyên nhân:** vai trò của tài khoản không có quyền cho thao tác đó.
- **Cách xử lý:** quản trị viên kiểm tra và gán quyền cho vai trò hoặc người dùng.
- **Lưu ý:** quyền được lưu trong token khi đăng nhập. Sau khi quyền được thay đổi, người dùng cần **đăng nhập lại** để quyền mới có hiệu lực. Token cũ vẫn giữ quyền cũ cho đến khi hết hạn.

## Tại sao không thấy một số màn hình hoặc nút bấm?

- **Nguyên nhân:** giao diện ẩn các nút mà tài khoản không có quyền. Đây chỉ là hiển thị; việc kiểm tra quyền thật sự diễn ra ở máy chủ.
- **Cách xử lý:** như trên — kiểm tra quyền và đăng nhập lại.

---

**Cần bổ sung:** kiểm tra thực tế thông báo lỗi hiển thị trên giao diện, vì mục này mới dựa trên mã lỗi trong đặc tả API, chưa đối chiếu với màn hình.
