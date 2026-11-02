---
title: Lỗi hệ thống và dữ liệu nhập
group: faq
audience: nhân viên ERP, admin
status: draft
source: docs/contracts/api-spec.md mục 3; docs/knowledge/technical/deployment.md (mục Chống chịu lỗi); quy tắc gọi giữa các service trong CLAUDE.md
verified_by: null
verified_at: null
---

# Lỗi hệ thống và dữ liệu nhập

## Vì sao không lưu được vì dữ liệu nhập sai?

- **Thông báo:** `VALIDATION_FAILED` (HTTP 422) — "Dữ liệu đầu vào sai định dạng hoặc thiếu trường bắt buộc".
- **Nguyên nhân:** thiếu trường bắt buộc, hoặc giá trị không đúng định dạng (ví dụ số lượng âm, ngày sai).
- **Cách xử lý:** đọc thông báo chi tiết của lỗi để biết trường nào sai, sửa và lưu lại.

## Vì sao không tạo được đơn bán dù còn hàng?

- **Thông báo:** `DEPENDENCY_UNAVAILABLE` (HTTP 503) — "Service phụ thuộc không phản hồi".
- **Nguyên nhân:** khi tạo đơn, hệ thống cần lấy thông tin khách hàng và sản phẩm từ service Catalog (và đôi khi Identity). Nếu các service này không phản hồi, đơn không được tạo. Hệ thống không dùng dữ liệu rỗng thay thế.
- **Cách xử lý:** thử lại sau vài phút. Nếu lỗi kéo dài, báo quản trị viên kiểm tra trạng thái các service.
- **Ghi chú cho admin:** kiểm tra health check của `catalog-service` và `identity-service`; lệnh `./scripts/smoke-test.ps1` và `docker compose ps` là điểm bắt đầu (xem `deployment.md`).

## Vì sao lỗi "hết thời gian chờ" khi xử lý đơn?

- **Thông báo:** `SAGA_TIMEOUT` (HTTP 504) — "Quá hạn chờ phản hồi từ Inventory".
- **Nguyên nhân:** service Inventory không trả lời kịp.
- **Cách xử lý:** thử lại sau. Hệ thống đã được thiết kế để nhả hàng nếu kết quả giữ hàng không chắc chắn, nên thử lại an toàn.
- **Cần kiểm tra:** tài liệu đặc tả ghi lỗi này đến từ RabbitMQ, nhưng luồng giữ hàng hiện là REST đồng bộ. Cần xác nhận lại trước khi phát hành FAQ.

---

## Chưa có nguồn — cần bổ sung khi có lỗi thực tế

Các câu hỏi sau thường gặp nhưng chưa có bằng chứng từ tài liệu hoặc code. Cần ghi lại lỗi thực tế (thông báo nguyên văn, bước thao tác, kết quả) rồi mới viết:

- Tại sao báo giá không được duyệt?
- Tại sao tôi không nhận được thông báo?
- Tại sao không tạo được chứng từ thanh toán / phiếu thu?

Không viết nguyên nhân cho các câu này khi chưa có lỗi thực tế đã được kiểm chứng.
