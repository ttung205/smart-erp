---
title: Trạng thái các phân hệ
group: technical
audience: developer, admin
source: docs/architecture/ARCHITECTURE.md dòng 272–355 (§10)
verified_by: null
verified_at: null
---

## 10. Các luồng nghiệp vụ chính

### 10.1 Xác thực — IMPLEMENTED

1. Trình duyệt gửi thông tin đăng nhập qua Kong tới Identity `/api/login`.
2. `AuthController.login` kiểm tra người dùng đang hoạt động và mật khẩu đã băm, nạp vai trò/quyền, rồi cấp JWT HS256 chứa các claim định danh và phân quyền.
3. Frontend gửi `Authorization: Bearer`; mọi service tự xác minh bằng secret dùng chung ở local và kiểm tra quyền trong middleware/controller.
4. Các lần đọc danh bạ nội bộ có thể dùng `X-Internal-Service-Key` thay thế; Kong xóa header này khỏi traffic công khai.

Bằng chứng: `identity-service/routes/api.php`; `AuthController.login`; `JwtIssuer.issue`; `JwtVerifier.verify`; `AuthenticateJwt` hoặc `HmacJwtTokenValidator` của từng service; request transformer trong `kong.yml`.

Chưa tìm thấy endpoint thu hồi, làm mới hoặc xoay token. Quyền nhúng trong token vẫn có hiệu lực cho đến khi token hết hạn; `/me` có thể trả dữ liệu mới hơn nhưng không vô hiệu hóa token hiện tại. Chính sách TTL/xoay token ngoài cấu hình của `JwtIssuer` là **UNKNOWN**.

### 10.2 Catalog — IMPLEMENTED

- Có CRUD và import sản phẩm, cùng thương hiệu/danh mục; Catalog cũng sở hữu khách hàng, liên hệ, nhà cung cấp và đối tác.
- Khi tạo/import/upsert sản phẩm, `ProductEvents.created` được gọi trong quá trình xử lý dữ liệu và ghi `ProductCreated` vào outbox.
- Inventory consume sự kiện này để khởi tạo bản chiếu tồn kho cục bộ; Notification cũng tạo thông báo inbox/realtime.
- Không tìm thấy sự kiện outbox thực thi tương ứng cho việc cập nhật hoặc xóa sản phẩm, dù binding wildcard có thể nhận. Vì vậy việc lan truyền cập nhật/xóa là **UNKNOWN/ABSENT**, không được suy đoán.

Bằng chứng: `catalog-service/routes/api.php`; `ProductController` gọi tại dòng 110, 249, 390; `ProductEvents.created`; `RabbitMqConsumerWorker.ProcessMessageAsync`.

### 10.3 Sales — IMPLEMENTED, có ranh giới với service khác rõ ràng

- Báo giá và đơn bán có quy trình nháp, gửi duyệt, duyệt, từ chối và chuyển đổi.
- Khi tạo chứng từ, Sales gọi Catalog/Identity đồng bộ trước khi mở transaction DB cục bộ, rồi lưu snapshot.
- Thay đổi trạng thái phê duyệt và sự kiện outbox tương ứng được commit trong cùng một transaction cục bộ; Notification consume các sự kiện này.
- Giao hàng kiểm tra kho của Inventory đồng bộ. Các thao tác trên hóa đơn giữ/nhả/xuất hàng đồng bộ. Khi phát hành hóa đơn VAT, công nợ được đồng bộ sang Finance đồng bộ.
- Trả hàng gọi Inventory nhận/chuyển hàng đồng bộ.

Bằng chứng: `QuotationController`; `SalesOrderController.store/submitApproval/approve/reject`; `ReferenceSnapshot`; `DeliveryController`; `InvoiceController`; `CustomerReceivableService`; các controller trả hàng.

### 10.4 Inventory — IMPLEMENTED

- Inventory sở hữu kho, tồn thực tế, tồn giữ chỗ và sổ cái.
- `reserve/batch` là đồng bộ và được thiết kế theo kiểu tất cả hoặc không gì cả; Sales nhận ngay lỗi thiếu hàng.
- Sự kiện nhận hàng từ Purchase cập nhật Inventory bất đồng bộ. Sự kiện tạo sản phẩm khởi tạo bản ghi tồn kho trong Inventory.
- Xuất kho được thực hiện đồng bộ từ Sales và phát `GoodsIssued`, nhưng chưa có consumer nghiệp vụ nào nối với routing key này.

Bằng chứng: `StocksController.ReserveBatch/Receive/Issue`; `RabbitMqConsumerWorker.ProcessMessageAsync`; `InventoryStockManager`; lời gọi outbox trong `StocksController`.

### 10.5 Purchase — IMPLEMENTED

- Chuỗi PR → duyệt/từ chối → PO → duyệt/hủy/đóng → xác nhận nhận hàng (GRN) → đối chiếu và ghi nhận hóa đơn mua → trả hàng, tất cả được expose qua REST API.
- Khi xác nhận nhận hàng, `GoodsReceived` được ghi vào outbox của Purchase; Inventory consume bất đồng bộ.
- Hỏi hàng phát thông báo yêu cầu/phản hồi và dùng lời gọi service đồng bộ để kiểm tra người dùng, áp dụng kết quả vào Catalog, và quy trình email thông báo.

Bằng chứng: các controller và application service của Purchase; `GoodsReceiptService`; `PurchaseOrderService`; `PurchaseReturnService`; `ItemInquiriesController`.

### 10.6 Finance — IMPLEMENTED, được Sales cấp dữ liệu đồng bộ

- Finance cung cấp tài khoản, sổ cái, sổ quỹ/ngân hàng, công nợ phải thu, phiếu thu/chi, ghi sổ và phân bổ.
- Sales gửi trực tiếp đồng bộ công nợ phải thu và điều chỉnh hóa đơn sang Finance, dùng bearer token hiện tại.
- Không tìm thấy outbox, package RabbitMQ hay consumer sự kiện nào trong Finance. Mọi tích hợp Finance theo hướng sự kiện đều là **ABSENT/UNKNOWN**.

Bằng chứng: `finance-service/routes/api.php`; migration của Finance; `CustomerReceivableService.sync`; `VatInvoiceAdjustmentService.issue`; `finance-service/composer.json`.

### 10.7 Notification — IMPLEMENTED

- Consumer nghiệp vụ xác định người nhận, ghi thông báo và trạng thái idempotency của sự kiện trong MongoDB (processed_events có TTL), rồi đẩy sự kiện qua Socket.IO.
- Retry dùng hàng đợi TTL 10 giây, tối đa 5 lần; message hết lượt hoặc không hợp lệ được chuyển sang DLX.
- API gửi email đưa job vào `erp.email.send`; consumer email gửi qua Nodemailer/Mailpit và theo dõi trạng thái job.
- Các route email được mount trước các route Notification có JWT và không thấy `requireAuth` trong `app.js`. Chưa xác định được việc phân quyền bên trong `email.routes.js` từ cách mount route. Cần coi việc gửi email công khai là rủi ro bảo mật cần kiểm tra.

Bằng chứng: `NotificationService.handleEvent`; `consumer.js`; `email.queue.js`; `app.js:19-21`.

### 10.8 Chat — REST + SignalR IMPLEMENTED; bot AMQP DOC_ONLY

- REST sở hữu mọi thao tác ghi và phân quyền; SignalR phân phát thay đổi và nhận tín hiệu đang gõ.
- Đã triển khai chat trực tiếp, nhóm và luồng công việc; tin nhắn hỗ trợ file, reaction, ghim, chuyển tiếp, thu hồi/ẩn, trạng thái đã đọc, online và đồng bộ lại khi kết nối lại.
- Chat lấy thông tin người dùng từ Identity qua client REST dùng internal key, có cache 60 giây.
- Mã Chat hiện tại không có dependency, queue hay bot consumer RabbitMQ nào. Roadmap nói giai đoạn 3 đã hoàn thành là sai lệch giữa tài liệu và code.

Bằng chứng: `ConversationsController`; `MessagesController`; `MessageService.SendAsync`; `ChatHub`; `IdentityUserDirectory`; `Chat.Infrastructure.csproj`; `docs/csharp-realtime-chat-roadmap.md:201-205`.

### 10.9 AI/phân tích — API IMPLEMENTED; làm mới production đang TẮT

- FastAPI cung cấp health, chat, lịch sử/xóa hội thoại và đồng bộ thủ công cho quản trị. JWT yêu cầu quyền `ai.use`; đồng bộ thủ công yêu cầu `SUPER_ADMIN`.
- Agent gọi Groq bên ngoài và đọc schema phân tích MySQL `erp_analytics_db` qua các tool đã đăng ký.
- ETL đọc trực tiếp schema MySQL của 6 service khác (phiên chỉ đọc) và dựng read model dạng star trong `erp_analytics_db`. Compose để mặc định tắt (`AI_WAREHOUSE_SYNC_ENABLED=false`) và chỉ dành cho demo. Việc đọc schema của service khác vẫn vượt ranh giới service; đích đến là consumer `erp.analytics.events` hoặc export API (xem docs/architecture/ARCHITECTURE.md §13 mục 2).
- Các endpoint phân tích trong roadmap (`/analytics/overview`, dự báo, RFM, rủi ro tín dụng, tóm tắt) không có trong `main.py`; xếp vào **PLANNED / DOC_ONLY**.

Bằng chứng: `ai-service/app/main.py`; `auth.py`; `agent.py`; `warehouse.py`; `docker-compose.yml`; `docs/data-analytics-ml-bi-roadmap.md:100-112`.
