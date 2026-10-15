# Kiến Trúc Thông Báo Hệ Thống (Notification via Message Queue)

> **Dự án:** Smart Distributed ERP · **Ngày lập:** 15/10/2026 · **Trạng thái:** Giai đoạn 1 đã triển khai

Thông báo nghiệp vụ (cần duyệt báo giá, đơn hàng, giao hàng, xuất kho, nghỉ phép...) đi qua RabbitMQ theo hướng sự kiện. Service nghiệp vụ chỉ phát **domain event**; việc ai nhận, nội dung gì, kênh nào do `notification-service` quyết định.

## 1. Luồng tổng thể

```
 sales-service / eoffice-service
 ┌──────────────────────────────────────────┐
 │ DB::transaction {                        │
 │   cập nhật nghiệp vụ                     │
 │   Outbox::record(...) → outbox_events    │  ← cùng 1 transaction
 │ }                                        │
 │ php artisan outbox:relay (worker nền)    │──publish (confirm)──┐
 └──────────────────────────────────────────┘                     ▼
                                  erp.events (topic exchange)
                                         │ sales.quotation.*, sales.order.submitted|approved|rejected,
                                         │ sales.delivery.*, sales.dispatch.*, eoffice.leave.*
                                         ▼
                              erp.notification.events ──quá 5 lần──► erp.dlx → erp.dead_letters
                                   ▲          │ lỗi tạm thời
                     TTL 10s quay lại         ▼
                              erp.notification.retry
                                         │
 ┌──────────────────── notification-service (:8006) ────────────────────┐
 │ consumer → NotificationService.handleEvent                           │
 │   1. processed_events: bỏ qua event_id đã xử lý                      │
 │   2. rules.js: event_type → người nhận, tiêu đề, link, action_key    │
 │   3. RecipientResolver: theo user_id trong payload hoặc theo quyền   │
 │      (GET identity /api/users/by-permission, cache 5 phút)           │
 │   4. 1 transaction: đóng việc cần làm cũ + ghi notifications + mark  │
 │   5. ACK message, đẩy Socket.IO tới room user:{id}                   │
 │ REST: GET /api/v1/notifications, /unread-count, POST /:id/read,      │
 │       POST /read-all  (JWT HS256 dùng chung identity-service)        │
 └──────────────────────────────────────────────────────────────────────┘
                                         │ socket.io (auth.token = JWT)
                                         ▼
 Frontend: NotificationBell (topbar) + useNotifications (state dùng chung, toast khi có mới)
```

## 2. Luật thông báo

| Event | Người nhận | Loại | Đóng việc cần làm |
| :--- | :--- | :--- | :--- |
| `QuotationSubmitted` | có quyền `quotation.approve` | Cần xử lý | — |
| `QuotationApproved` / `Rejected` | người tạo báo giá | Thông tin | `quotation:{id}:approve` |
| `SalesOrderSubmitted` | có quyền `order.approve` | Cần xử lý | — |
| `SalesOrderApproved` / `Rejected` | người tạo đơn | Thông tin | `order:{id}:approve` |
| `DeliveryRequested` | có quyền duyệt giao hàng `delivery.manage` | Cần xử lý | — |
| `DeliveryApproved` / `Rejected` | người tạo đơn hàng | Thông tin | `delivery:{id}:approve` |
| `WarehouseDispatchRequested` | có quyền kho `inventory.manage` / `inventory.issue` | Cần xử lý | — |
| `LeaveRequestSubmitted` | người duyệt của bước (line manager) | Cần xử lý | — |
| `LeaveRequestApproved` / `Rejected` / `Returned` | người làm đơn | Thông tin | `leave:{id}:approve` |
| `LeaveRequestCancelled` | — | — | `leave:{id}:approve` |

- Quyền `<module>.manage` bao hàm mọi quyền cùng module (giống middleware `RequirePermission`).
- Người thực hiện hành động (`payload.actor`) luôn bị loại khỏi danh sách nhận.
- **Action key**: báo giá gửi cho 3 người có quyền duyệt; 1 người duyệt xong thì 2 thông báo còn lại chuyển `RESOLVED` (hiển thị "Đã xử lý", không tính chưa đọc) và được đẩy realtime.

## 3. Đảm bảo tin cậy

| Rủi ro | Cách xử lý |
| :--- | :--- |
| DB commit nhưng broker lỗi → mất event | Transactional Outbox; relay retry tối đa 10 lần, dừng lượt khi lỗi để giữ thứ tự |
| Message trùng (at-least-once) | `processed_events` + `UNIQUE(event_id, recipient_id)` |
| identity-service tạm lỗi | Ném lỗi → retry queue (TTL 10s, tối đa 5 lần) → dead letter |
| Routing key không có queue nhận | Publish `mandatory` + publisher confirm → coi là lỗi, event giữ lại để retry |
| Hai relay chạy cùng lúc | `flock` trên `storage/framework/outbox-relay.lock` |
| User offline / mất socket | DB là nguồn sự thật; mỗi lần (re)connect frontend gọi lại REST |

## 4. Vận hành (dev)

```powershell
docker compose up -d rabbitmq          # Management UI: http://localhost:15672 (erp_rabbit / rabbit_secret)
cd services\sales-service;   php artisan migrate; php artisan outbox:relay
cd services\eoffice-service; php artisan migrate; php artisan outbox:relay
cd services\notification-service; npm install; npm run dev   # cần JWT_SECRET trùng identity-service
```

`start_dev.ps1` tự mở thêm 2 tab **Sales Outbox Relay** và **EOffice Outbox Relay** (bỏ qua nếu đang chạy).

Cấu hình `notification-service/.env`: `JWT_SECRET`, `IDENTITY_SERVICE_URL`, `RABBITMQ_URL`, `NOTIFICATION_DB_PATH` (mặc định `database/notifications.sqlite`, dùng `node:sqlite` có sẵn của Node 24).

## 5. Thêm loại thông báo mới

1. Service nghiệp vụ: `Outbox::record('XxxHappened', '<service>.<aggregate>.<action>', ...)` bên trong `DB::transaction`.
2. Nếu routing key chưa được bind: thêm vào `BINDINGS` trong `notification-service/src/messaging/consumer.js` và `infrastructure/rabbitmq/definitions.json`.
3. Thêm rule vào `notification-service/src/notifications/rules.js` + test trong `test/notification.test.js`.

## 6. Hướng mở rộng

- Email cho việc cần làm quá hạn (nodemailer đã có sẵn trong service).
- Cài đặt nhận thông báo theo user (tắt/bật từng loại, từng kênh).
- Outbox + event cho purchase (PR/PO cần duyệt), finance, trả hàng bán.
- Chuyển DB notification sang MySQL khi chạy production (chỉ cần thay `db.js` + repository).
