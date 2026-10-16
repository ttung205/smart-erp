# LỘ TRÌNH PHÁT TRIỂN HỆ THỐNG CHAT REALTIME DOANH NGHIỆP VỚI C# (.NET 10)

> **Mục tiêu**: Xây dựng microservice **`chat-service`** độc lập bằng ngôn ngữ **C# (.NET 10)** tuân thủ nguyên tắc **Clean Architecture 4 tầng**, sử dụng **ASP.NET Core SignalR** làm động cơ truyền tải thời gian thực và tích hợp **RabbitMQ** nhận sự kiện tự động từ các phân hệ ERP khác.

---

## 1. TỔNG QUAN KIẾN TRÚC & CÔNG NGHỆ

```text
┌────────────────────────────────────────────────────────────────────────────────┐
│                           FRONTEND (VUE 3 + PINIA)                             │
│                  Thư viện: @microsoft/signalr  |  Pinia: chatStore             │
└──────────────────────────────────────┬─────────────────────────────────────────┘
                                       │ WebSocket / SignalR Hub (Port 8010)
                                       ▼
┌────────────────────────────────────────────────────────────────────────────────┐
│                    CHAT-SERVICE (C# .NET 10 WEB API)                           │
│                                                                                │
│  [Presentation]   ChatHub (SignalR) + ChatController (REST API)               │
│         │                                                                      │
│  [Application]    Commands / Queries (MediatR), DTOs, Hub Context Interfaces   │
│         │                                                                      │
│  [Domain]         Entities: Conversation, ChatMessage, MessageReceipt          │
│         │                                                                      │
│  [Infrastructure] EF Core DbContext (SQLite/PostgreSQL) + RabbitMQ Consumer    │
└──────────────────────────────────────▲─────────────────────────────────────────┘
                                       │
                                       │ AMQP Topic: erp.events
┌──────────────────────────────────────┴─────────────────────────────────────────┐
│                           RABBITMQ MESSAGE BROKER                              │
│         Events từ: Catalog (:8002), Purchase (:8003), Inventory (:8004)        │
└────────────────────────────────────────────────────────────────────────────────┘
```

### Thông số kỹ thuật của `chat-service`:
* **Ngôn ngữ & Runtime:** C# (.NET 10 / ASP.NET Core Web API).
* **Cổng dịch vụ (Port):** `8010` (Map proxy Frontend qua `/api/v1/chat`).
* **Realtime Engine:** **ASP.NET Core SignalR** với cơ chế Groups (Phòng ban) và User Mapping (Chat 1-1).
* **Cơ sở dữ liệu:** **Entity Framework Core (EF Core 10)** + SQLite (`chat.db`) hoặc PostgreSQL.
* **Tích hợp sự kiện:** **RabbitMQ.Client** triển khai dưới dạng .NET `BackgroundService` (`IHostedService`).
* **Bảo mật:** JWT Bearer Authentication (tương thích `JWT_SECRET` của `identity-service`).

---

## 2. THIẾT KẾ CLEAN ARCHITECTURE 4 TẦNG (.NET 10)

Tuân thủ nghiêm ngặt theo [architecture.md](Ai_rule/architecture.md):

```text
services/chat-service/
├── src/
│   ├── Chat.Domain/
│   │   ├── Common/              (BaseEntity, IAggregateRoot, ValueObjects)
│   │   ├── Entities/            (Conversation, ConversationMember, ChatMessage, MessageReceipt)
│   │   └── Enums/               (ConversationType, MessageType, MemberRole)
│   ├── Chat.Application/
│   │   ├── Common/Interfaces/   (IChatDbContext, IChatHubService, ICurrentUserService)
│   │   ├── DTOs/                (ConversationDto, ChatMessageDto, UserStatusDto)
│   │   └── UseCases/            (SendMessage, GetMessages, JoinRooms, MarkAsRead)
│   ├── Chat.Infrastructure/
│   │   ├── Persistence/         (ChatDbContext, Configurations, Migrations)
│   │   ├── Realtime/            (ChatHubService, HubConnectionTracker)
│   │   └── Messaging/           (RabbitMqBotConsumerWorker : BackgroundService)
│   └── Chat.Api/
│       ├── Controllers/         (ConversationsController, MessagesController)
│       ├── Hubs/                (ChatHub : Hub<IChatClient>)
│       ├── Middlewares/         (JwtMiddleware, GlobalExceptionMiddleware)
│       └── Program.cs
└── tests/
    └── Chat.Tests/
```

---

## 3. THIẾT KẾ CƠ SỞ DỮ LIỆU & DOMAIN ENTITIES

### 3.1. Sơ đồ thực thể (ERD)

```text
┌──────────────────────┐        ┌──────────────────────┐
│    Conversations     │1      *│ ConversationMembers  │
│──────────────────────│────────│──────────────────────│
│ Id (Guid, PK)        │        │ ConversationId (FK)  │
│ Type (Enum)          │        │ UserId (int)         │
│ Code (string, Unique)│        │ JoinedAt (DateTime)  │
│ Name (string)        │        │ Role (string)        │
│ DepartmentCode       │        └──────────────────────┘
│ CreatedAt (DateTime) │
└──────────┬───────────┘
           │1
           │*
┌──────────▼───────────┐        ┌──────────────────────┐
│     ChatMessages     │1      *│   MessageReceipts    │
│──────────────────────│────────│──────────────────────│
│ Id (Guid, PK)        │        │ MessageId (Guid, FK) │
│ ConversationId (FK)  │        │ UserId (int)         │
│ SenderId (int)       │        │ ReadAt (DateTime)    │
│ SenderName (string)  │        └──────────────────────┘
│ SenderAvatar (string)│
│ MessageType (Enum)   │ -- TEXT, SYSTEM_BOT, ATTACHMENT
│ Content (string)     │
│ AttachmentUrl        │
│ CreatedAt (DateTime) │
└──────────────────────┘
```

### 3.2. Dữ liệu phòng ban mặc định (Seed Channels)
Khi ứng dụng khởi động, tự động seed các phòng chat công ty:
* `#chung` (`dept:general`): Kênh thông báo toàn bộ nhân viên.
* `#kho-van` (`dept:inventory`): Bộ phận Kho Vận & Điều Vận (`LOGISTIC`).
* `#thu-mua` (`dept:purchase`): Bộ phận Mua Hàng & Cung Ứng (`PURCHASE`).
* `#kinh-doanh` (`dept:sales`): Phòng Kinh Doanh (`SALES`).
* `#tai-chinh` (`dept:finance`): Phòng Tài Chính Kế Toán (`FINANCE`).
* `#ky-thuat` (`dept:technical`): Phòng Kỹ Thuật & Công Nghệ (`TECHNICAL`).

---

## 4. THIẾT KẾ SIGNALR HUB (`ChatHub`)

### 4.1. Strongly-Typed Client Interface (`IChatClient`)

```csharp
public interface IChatClient
{
    Task ReceiveMessage(ChatMessageDto message);
    Task UserTyping(string conversationId, int userId, string userName, bool isTyping);
    Task MessageRead(Guid messageId, int userId, DateTime readAt);
    Task UserOnlineStatus(int userId, bool isOnline);
}
```

### 4.2. SignalR Hub Methods

```csharp
[Authorize]
public class ChatHub : Hub<IChatClient>
{
    // Khi kết nối: Đọc User Claims từ JWT và tự động map vào User Group: user_{userId}
    public override async Task OnConnectedAsync();
    public override async Task OnDisconnectedAsync(Exception? exception);

    // Tham gia kênh phòng ban dựa theo thông tin phòng ban của nhân viên
    public Task JoinDepartmentChannel(string departmentCode);
    
    // Gửi tin nhắn vào kênh nhóm / phòng ban
    public Task SendChannelMessage(SendChannelMessageRequest request);

    // Gửi tin nhắn trực tiếp 1-1 cho đồng nghiệp
    public Task SendDirectMessage(SendDirectMessageRequest request);

    // Phát tín hiệu đang soạn tin
    public Task SendTypingIndicator(string conversationId, bool isTyping);

    // Xác nhận đã xem tin nhắn
    public Task MarkMessageAsRead(Guid messageId);
}
```

---

## 5. TÍCH HỢP RABBITMQ SYSTEM BOT (TỰ ĐỘNG BẮN TIN NHẮN)

Sử dụng `BackgroundService` trong .NET để nghe các sự kiện nghiệp vụ từ `erp.events` và phát tin nhắn bot tự động vào kênh phòng ban qua `IHubContext<ChatHub, IChatClient>`:

```csharp
public class RabbitMqBotConsumerWorker : BackgroundService
{
    private readonly IHubContext<ChatHub, IChatClient> _hubContext;
    private readonly IServiceScopeFactory _scopeFactory;

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        // Lắng nghe:
        // 1. catalog.product.created -> Bắn tin nhắn Bot vào #kho-van: "Sản phẩm mới [SKU] vừa được thêm..."
        // 2. purchase.order.approved -> Bắn tin nhắn Bot vào #thu-mua: "Đơn mua PO-xxx đã được duyệt..."
        // 3. inventory.stock.low     -> Bắn tin nhắn Cảnh báo vào #kho-van: "Cảnh báo Min-Stock sản phẩm [SKU]..."
    }
}
```

---

## 6. LỘ TRÌNH THỰC HIỆN TỪNG BƯỚC (PHASED ROADMAP)

### 🚀 Giai đoạn 1: Khởi tạo Project & CSDL C# (.NET 10)
- [ ] Dùng `dotnet new` khởi tạo Solution `ChatService.sln` gồm 4 Project:
  - `Chat.Domain`, `Chat.Application`, `Chat.Infrastructure`, `Chat.Api`.
- [ ] Khai báo các thực thể `Conversation`, `ChatMessage`, `MessageReceipt`.
- [ ] Cấu hình EF Core DbContext với SQLite (`chat.db`).
- [ ] Tạo Migration ban đầu và viết Seeder tự động nạp 6 kênh phòng ban mặc định.
- [ ] Cấu hình CORS và Authentication JWT Bearer với `JWT_SECRET`.

### 🚀 Giai đoạn 2: Xây dựng SignalR Hub & REST API
- [ ] Cài đặt package `Microsoft.AspNetCore.SignalR`.
- [ ] Xây dựng `ChatHub : Hub<IChatClient>` hỗ trợ Groups phòng ban và tin nhắn riêng tư.
- [ ] Viết REST API:
  - `GET /api/v1/chat/conversations`: Danh sách phòng ban và hội thoại gần nhất.
  - `GET /api/v1/chat/conversations/{id}/messages`: Phân trang lịch sử tin nhắn cũ.
- [ ] Viết test xác thực kết nối SignalR bằng script console/node.

### 🚀 Giai đoạn 3: Tích hợp RabbitMQ Bot Worker
- [ ] Cài đặt package `RabbitMQ.Client` trong `Chat.Infrastructure`.
- [ ] Xây dựng `RabbitMqBotConsumerWorker : BackgroundService`.
- [ ] Đăng ký consumer queue `erp.chat.events` bind tới `erp.events`.
- [ ] Khi có event nghiệp vụ $\rightarrow$ Lưu bản ghi `ChatMessages` (loại `SYSTEM_BOT`) và phát realtime vào nhóm chat tương ứng.

### 🚀 Giai đoạn 4: Xây dựng Giao diện Chat trên Frontend (Vue 3 + Pinia)
- [ ] Cài đặt gói `@microsoft/signalr` trong `frontend`.
- [ ] Xây dựng Pinia store [chatStore.js](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/stores/chatStore.js) quản lý state kết nối Hub, danh sách tin nhắn, trạng thái online/offline.
- [ ] Thêm proxy `/api/v1/chat` và WebSocket proxy trong `vite.config.js` trỏ về port `8010`.
- [ ] Thiết kế Giao diện Khung Chat theo phong cách Clean Flat White Canvas:
  - Cột trái: Danh sách kênh phòng ban (#chung, #kho-van, #thu-mua...) và đồng nghiệp.
  - Khung giữa: Bong bóng chat, avatar, thời gian gửi, phân biệt tin nhắn nhân viên và tin nhắn Bot hệ thống.
  - Hỗ trợ hiển thị trạng thái *"Nguyễn Văn A đang soạn tin..."*.

### 🚀 Giai đoạn 5: Kiểm thử Toàn diện & Nghiệm thu
- [ ] Mở 2 trình duyệt với 2 tài khoản khác nhau (ví dụ: `logistic@erp.local` và `sale@erp.local`):
  - Kiểm thử chat qua lại thời gian thực 1-1.
  - Kiểm thử chat trong phòng ban.
- [ ] Tạo 1 sản phẩm mới ở Catalog Service $\rightarrow$ Kiểm tra Bot tự động thông báo vào kênh `#kho-van`.
- [ ] Kiểm tra lịch sử tin nhắn không bị mất khi tải lại trang.

---

## 7. BẢNG PHÂN CÔNG CỔNG VÀ GIAO TIẾP HỆ THỐNG

| Dịch vụ | Cổng | Công nghệ | Nhiệm vụ đối với Chat |
| :--- | :--- | :--- | :--- |
| **`chat-service`** | **8010** | **C# .NET 10** | Quản lý SignalR ChatHub, lưu trữ tin nhắn, phát realtime |
| **`identity-service`** | **8001** | PHP Laravel | Xác thực JWT và cung cấp danh sách nhân viên |
| **`notification-service`** | **8006** | Node.js | Quản lý Quả chuông thông báo cá nhân (Inbox Bell) |
| **`RabbitMQ`** | **5672** | AMQP | Trung chuyển sự kiện nghiệp vụ sang Chat Bot |
| **`frontend`** | **5175** | Vue 3 | Giao diện Chat client kết nối qua `@microsoft/signalr` |
