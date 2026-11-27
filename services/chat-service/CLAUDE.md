# chat-service — hướng dẫn cho AI coding agent

Áp dụng toàn bộ quy tắc gốc tại `../../CLAUDE.md`. File này chỉ bổ sung những gì riêng cho service chat.
Chi tiết hợp đồng API, realtime và checklist nằm ở `.agents/rules/service-rules.md`.

## Vai trò

- Nhắn tin nội bộ: hội thoại trực tiếp, nhóm, và "luồng công việc" gắn với một chứng từ (`SubjectType`, `SubjectId`).
- Port `8009`, prefix gateway `/api/v1/chat/`.
- Stack: .NET 10, Clean Architecture (`Chat.Api`, `Chat.Application`, `Chat.Domain`, `Chat.Infrastructure`), **MongoDB** (không phải MySQL), SignalR realtime, lưu tệp cục bộ (`LocalFileStorage`), Swashbuckle.

## Lệnh thường dùng

```bash
dotnet test services/chat-service/ChatService.slnx
# Smoke test REST và SignalR khi service đang chạy ở :8009
pwsh services/chat-service/tests/test_chat_service.ps1
```

## Quy tắc riêng của service

0. **Quyền `chat.use`.** Mọi endpoint (trừ health) và hub đều yêu cầu quyền `chat.use` trong JWT. Quyền này do identity-service cấp cho mọi vai trò (migration `add_chat_use_permission`).
1. **Hub SignalR chỉ server → client.** Mọi thao tác ghi (gửi, sửa, thu hồi, ghim, thêm thành viên) đi qua REST để kiểm tra quyền ở một chỗ. Hub chỉ nhận `Typing`.
2. **Client không tự vào nhóm hội thoại.** Hub chỉ thêm kết nối vào nhóm `user_{id}`. Thành viên bị xóa khỏi hội thoại ngừng nhận tin ngay.
3. **Unread, mute, ghim là của từng người dùng**, lưu theo `ConversationMember`, không theo hội thoại chung.
4. **Gửi tin có giới hạn**: rate limit policy `send`, `RequestSizeLimit` và `MultipartBodyLengthLimit` (`MaxMessageRequestBytes`).
5. **Tên người dùng** lấy từ JWT hoặc qua `IdentityUserDirectory` (gọi identity-service qua REST), không tra cứu database của identity.
6. **Thời gian** dùng `DateTime.UtcNow`. Cấm `DateTime.Now`.

## Việc cần biết trước khi sửa

- Thư mục `src/Chat.Api/` có các file `chat.db`, `chat-messenger.db` (dữ liệu cục bộ, không commit). Hiện service dùng MongoDB, nên các file này nằm lại từ giai đoạn trước.
- `tests/test_chat_service.ps1` đang chứa JWT secret mẫu trong mã nguồn. Chuyển sang biến môi trường trước khi dùng cho môi trường chung.
- Tệp đính kèm lưu cục bộ, không phù hợp khi chạy nhiều bản sao. Cần đưa lên object storage hoặc EFS khi scale.
