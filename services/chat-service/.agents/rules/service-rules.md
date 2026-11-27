# Quy tắc chi tiết — chat-service

Đọc cùng `../../../../CLAUDE.md` (gốc repo) và `../../CLAUDE.md` (tổng quan service).

## 1. Mô hình dữ liệu

- `Conversation` (loại: direct, group, work), `ConversationMember` (vai trò, lastRead, mute, pin), `ChatMessage` (+ `MessageParts`: reply, mention, reaction, attachment, forward).
- Lưu trong MongoDB qua `MongoChatContext` / `MongoChatRepository`. Domain không import `MongoDB.Driver`.
- Trường tham chiếu sang service khác (`user_id`, `subject_id`) là ID nguyên thủy, không có liên kết dữ liệu.

## 2. Hợp đồng REST

| Controller | Route | Hành động |
|---|---|---|
| `ConversationsController` | `/api/v1/chat/conversations` | GET list (`page`, `per_page`), GET `/{id}`, GET `/unread-count`, POST `/direct`, `/work`, `/group`, `/read-all`, `/{id}/read`, `/{id}/pin`, `/{id}/mute`, `/{id}/members`, `/{id}/leave`, `/{id}/transfer-owner/{userId}` |
| `ConversationsController` (tiếp) | | PATCH `/{id}` (đổi tên), POST/DELETE/GET `/{id}/avatar`, DELETE `/{id}/members/{userId}`, PUT `/{id}/members/{userId}/role`, DELETE `/{id}` (giải tán nhóm) |
| `ConversationsController` (tin nhắn) | | GET `/{id}/messages` (`before_id`), POST `/{id}/messages` (multipart: `content`, `reply_to_id`, `mentions[]`, `mention_all`, `attachments[]`), GET `/{id}/messages/search?q=`, GET `/{id}/pins` |
| `MessagesController` | `/api/v1/chat/messages/{id}`, `/attachments/{id}` | PATCH sửa, POST `/recall`, `/hide`, `/reactions`, `/pin`, `/forward`; DELETE `/pin`; GET tải tệp đính kèm |
| `UsersController` | `/api/v1/chat/users`, `/contacts`, `/preferences` | GET `/search?q=`, GET `/{id}`, GET `/online`, PUT `/{id}/alias`, GET `/contacts`, GET `/preferences` |
| `HealthController` | `/health`, `/api/health`, `/api/v1/chat/health` | GET |

Hub realtime: `/api/v1/chat/hub` (SignalR). Sự kiện server gửi (tên nằm trong `ChatServiceBase.Events`): `conversation.upserted`, `conversation.removed`, `message.created`, `message.updated`, `message.hidden`, `read.updated`, `typing`, và presence.

Quy tắc chung:
- Envelope `ApiEnvelope`, JSON snake_case. DTO nằm ở `Chat.Application/Dtos/ChatDtos.cs`. Không trả entity Mongo ra ngoài.
- Lỗi nghiệp vụ tạo bằng `ChatException`, mã theo `docs/contracts/api-spec.md`: `RESOURCE_NOT_FOUND` (404), `PERMISSION_DENIED` (403), `VALIDATION_FAILED` (422). Xác thực thất bại trả `UNAUTHENTICATED` (401).

## 3. Quy tắc quyền trong hội thoại

- Chỉ thành viên mới đọc hoặc gửi tin. Người ngoài nhận `RESOURCE_NOT_FOUND` (`ChatServiceBase.RequireMemberAsync`), không lộ sự tồn tại của hội thoại.
- Vai trò trong nhóm: `owner`, `deputy` (phó nhóm), `member`. Owner được chuyển quyền sở hữu và đổi vai trò. Deputy chỉ quản lý được member (`ConversationService.cs`).
- Hội thoại `work` lưu snapshot `SubjectType`, `SubjectId`, `SubjectCode`, `SubjectTitle` của chứng từ. Không đọc database của service chứng từ để lấy tiêu đề.
- Tệp đính kèm: tối đa 10 tệp mỗi tin, mỗi tệp tối đa 20 MB (`FileRules.cs`).

## 4. Kiểm thử

- `Chat.Tests`: `ChatApiTests` (REST), `RealtimeTests` (SignalR), `ChatApiFactory` (khởi tạo service thật trong bộ nhớ).
- `tests/test_chat_service.ps1`: smoke test khi service đang chạy, dùng JWT do script tạo.

## 5. Checklist trước khi bàn giao

- [ ] Thao tác ghi đi qua REST, không đi qua hub.
- [ ] Kiểm tra là thành viên trước khi đọc/ghi tin.
- [ ] `DateTime.UtcNow`, không `DateTime.Now`.
- [ ] Có test cho quyền thành viên (người ngoài bị từ chối).
- [ ] Không thêm secret vào mã nguồn.
