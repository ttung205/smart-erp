using System.Security.Claims;
using Chat.Application.Common.Interfaces;
using Chat.Application.DTOs;
using Chat.Domain.Entities;
using Chat.Domain.Enums;
using Chat.Infrastructure.Persistence;
using Chat.Infrastructure.Realtime;
using Microsoft.AspNetCore.SignalR;
using Microsoft.EntityFrameworkCore;

namespace Chat.Api.Hubs;

public class ChatHub : Hub<IChatClient>
{
    private readonly ChatDbContext _context;
    private readonly HubConnectionTracker _tracker;
    private readonly ILogger<ChatHub> _logger;

    public ChatHub(
        ChatDbContext context,
        HubConnectionTracker tracker,
        ILogger<ChatHub> logger)
    {
        _context = context;
        _tracker = tracker;
        _logger = logger;
    }

    public override async Task OnConnectedAsync()
    {
        var (userId, userName, deptCode) = GetCurrentUser();

        if (userId > 0)
        {
            // 1. Gia nhập User Group cá nhân để nhận tin nhắn 1-1 và thông báo cá nhân
            await Groups.AddToGroupAsync(Context.ConnectionId, $"user_{userId}");

            // 2. Tự động gia nhập kênh chung toàn công ty
            await Groups.AddToGroupAsync(Context.ConnectionId, "dept_general");

            // 3. Nếu nhân viên có phòng ban, tự động gia nhập kênh phòng ban đó
            if (!string.IsNullOrEmpty(deptCode))
            {
                await Groups.AddToGroupAsync(Context.ConnectionId, $"dept_{deptCode.ToLower()}");
            }

            // 4. Cập nhật Tracker trạng thái Online
            var isFirst = _tracker.AddConnection(userId, Context.ConnectionId);
            if (isFirst)
            {
                await Clients.Others.UserOnlineStatus(userId, true);
                _logger.LogInformation("User {UserId} ({UserName}) is now ONLINE", userId, userName);
            }
        }

        await base.OnConnectedAsync();
    }

    public override async Task OnDisconnectedAsync(Exception? exception)
    {
        var (userId, userName, _) = GetCurrentUser();

        if (userId > 0)
        {
            var isLast = _tracker.RemoveConnection(userId, Context.ConnectionId);
            if (isLast)
            {
                await Clients.Others.UserOnlineStatus(userId, false);
                _logger.LogInformation("User {UserId} ({UserName}) is now OFFLINE", userId, userName);
            }
        }

        await base.OnDisconnectedAsync(exception);
    }

    /// <summary>
    /// Tham gia kênh phòng ban cụ thể (ví dụ: inventory, purchase, sales...)
    /// </summary>
    public async Task JoinDepartmentChannel(string departmentCode)
    {
        if (string.IsNullOrWhiteSpace(departmentCode)) return;
        await Groups.AddToGroupAsync(Context.ConnectionId, $"dept_{departmentCode.ToLower()}");
    }

    /// <summary>
    /// Tham gia một cuộc hội thoại cụ thể theo ID
    /// </summary>
    public async Task JoinConversation(string conversationId)
    {
        if (string.IsNullOrWhiteSpace(conversationId)) return;
        await Groups.AddToGroupAsync(Context.ConnectionId, $"conv_{conversationId}");
    }

    /// <summary>
    /// Gửi tin nhắn vào kênh nhóm / phòng ban
    /// </summary>
    public async Task SendChannelMessage(SendChannelMessageRequest request)
    {
        var (senderId, senderName, _) = GetCurrentUser();
        if (senderId <= 0)
        {
            throw new HubException("Chưa xác thực danh tính người dùng.");
        }

        if (string.IsNullOrWhiteSpace(request.Content))
        {
            throw new HubException("Nội dung tin nhắn không được để trống.");
        }

        Conversation? conversation = null;

        if (request.ConversationId.HasValue && request.ConversationId.Value != Guid.Empty)
        {
            conversation = await _context.Conversations
                .FirstOrDefaultAsync(c => c.Id == request.ConversationId.Value);
        }
        else if (!string.IsNullOrWhiteSpace(request.DepartmentCode))
        {
            var deptCode = request.DepartmentCode.ToLower();
            conversation = await _context.Conversations
                .FirstOrDefaultAsync(c => c.DepartmentCode == deptCode || c.Code == $"dept:{deptCode}");
        }

        if (conversation == null)
        {
            throw new HubException("Không tìm thấy kênh trò chuyện phù hợp.");
        }

        var message = new ChatMessage
        {
            Id = Guid.NewGuid(),
            ConversationId = conversation.Id,
            SenderId = senderId,
            SenderName = string.IsNullOrWhiteSpace(senderName) ? $"User #{senderId}" : senderName,
            MessageType = string.IsNullOrEmpty(request.AttachmentUrl) ? MessageType.Text : MessageType.Attachment,
            Content = request.Content.Trim(),
            AttachmentUrl = request.AttachmentUrl,
            CreatedAt = DateTime.UtcNow
        };

        await _context.ChatMessages.AddAsync(message);
        await _context.SaveChangesAsync();

        var messageDto = new ChatMessageDto
        {
            Id = message.Id,
            ConversationId = message.ConversationId,
            SenderId = message.SenderId,
            SenderName = message.SenderName,
            SenderAvatar = message.SenderAvatar,
            MessageType = message.MessageType.ToString().ToUpper(),
            Content = message.Content,
            AttachmentUrl = message.AttachmentUrl,
            CreatedAt = message.CreatedAt,
            ReadBy = new List<int> { senderId }
        };

        // Đảm bảo caller đã gia nhập nhóm hội thoại
        await Groups.AddToGroupAsync(Context.ConnectionId, $"conv_{conversation.Id}");

        // Phát tới nhóm cuộc hội thoại
        await Clients.Group($"conv_{conversation.Id}").ReceiveMessage(messageDto);

        // Phát tới nhóm phòng ban nếu là kênh phòng ban
        if (!string.IsNullOrEmpty(conversation.DepartmentCode))
        {
            await Clients.Group($"dept_{conversation.DepartmentCode.ToLower()}").ReceiveMessage(messageDto);
        }

        // Đảm bảo người gửi luôn nhận được phản hồi tức thì
        await Clients.Caller.ReceiveMessage(messageDto);
    }

    /// <summary>
    /// Gửi tin nhắn trực tiếp 1-1 cho đồng nghiệp
    /// </summary>
    public async Task SendDirectMessage(SendDirectMessageRequest request)
    {
        var (senderId, senderName, _) = GetCurrentUser();
        if (senderId <= 0)
        {
            throw new HubException("Chưa xác thực danh tính người dùng.");
        }

        if (request.RecipientId <= 0)
        {
            throw new HubException("ID người nhận không hợp lệ.");
        }

        if (string.IsNullOrWhiteSpace(request.Content))
        {
            throw new HubException("Nội dung tin nhắn không được để trống.");
        }

        // Tìm hoặc tạo cuộc hội thoại 1-1 với Code chuẩn duy nhất
        var minId = Math.Min(senderId, request.RecipientId);
        var maxId = Math.Max(senderId, request.RecipientId);
        var dmCode = $"dm:{minId}_{maxId}";

        var conversation = await _context.Conversations
            .Include(c => c.Members)
            .FirstOrDefaultAsync(c => c.Code == dmCode);

        if (conversation == null)
        {
            var recipientDisplayName = !string.IsNullOrWhiteSpace(request.RecipientName) 
                ? request.RecipientName 
                : $"User #{request.RecipientId}";

            conversation = new Conversation
            {
                Id = Guid.NewGuid(),
                Type = ConversationType.Direct,
                Code = dmCode,
                Name = $"{senderName} & {recipientDisplayName}",
                CreatedAt = DateTime.UtcNow
            };

            conversation.Members.Add(new ConversationMember
            {
                Id = Guid.NewGuid(),
                ConversationId = conversation.Id,
                UserId = senderId,
                UserName = senderName,
                Role = MemberRole.Admin,
                JoinedAt = DateTime.UtcNow
            });

            conversation.Members.Add(new ConversationMember
            {
                Id = Guid.NewGuid(),
                ConversationId = conversation.Id,
                UserId = request.RecipientId,
                UserName = recipientDisplayName,
                Role = MemberRole.Member,
                JoinedAt = DateTime.UtcNow
            });

            await _context.Conversations.AddAsync(conversation);
            await _context.SaveChangesAsync();
        }

        var message = new ChatMessage
        {
            Id = Guid.NewGuid(),
            ConversationId = conversation.Id,
            SenderId = senderId,
            SenderName = string.IsNullOrWhiteSpace(senderName) ? $"User #{senderId}" : senderName,
            MessageType = string.IsNullOrEmpty(request.AttachmentUrl) ? MessageType.Text : MessageType.Attachment,
            Content = request.Content.Trim(),
            AttachmentUrl = request.AttachmentUrl,
            CreatedAt = DateTime.UtcNow
        };

        await _context.ChatMessages.AddAsync(message);
        await _context.SaveChangesAsync();

        var messageDto = new ChatMessageDto
        {
            Id = message.Id,
            ConversationId = message.ConversationId,
            SenderId = message.SenderId,
            SenderName = message.SenderName,
            SenderAvatar = message.SenderAvatar,
            MessageType = message.MessageType.ToString().ToUpper(),
            Content = message.Content,
            AttachmentUrl = message.AttachmentUrl,
            CreatedAt = message.CreatedAt,
            ReadBy = new List<int> { senderId }
        };

        // Gửi realtime cho cả người nhận và người gửi qua User Group, nhóm conv và Caller
        await Groups.AddToGroupAsync(Context.ConnectionId, $"conv_{conversation.Id}");
        await Clients.Group($"conv_{conversation.Id}").ReceiveMessage(messageDto);
        await Clients.Group($"user_{request.RecipientId}").ReceiveMessage(messageDto);
        await Clients.Group($"user_{senderId}").ReceiveMessage(messageDto);
        await Clients.Caller.ReceiveMessage(messageDto);
    }

    /// <summary>
    /// Phát tín hiệu đang soạn tin ("typing indicator")
    /// </summary>
    public async Task SendTypingIndicator(string conversationId, bool isTyping)
    {
        var (senderId, senderName, _) = GetCurrentUser();
        if (senderId <= 0 || string.IsNullOrWhiteSpace(conversationId)) return;

        await Clients.OthersInGroup($"conv_{conversationId}")
            .UserTyping(conversationId, senderId, senderName, isTyping);
    }

    /// <summary>
    /// Xác nhận đã xem tin nhắn
    /// </summary>
    public async Task MarkMessageAsRead(Guid messageId)
    {
        var (userId, _, _) = GetCurrentUser();
        if (userId <= 0 || messageId == Guid.Empty) return;

        var message = await _context.ChatMessages
            .Include(m => m.Receipts)
            .FirstOrDefaultAsync(m => m.Id == messageId);

        if (message == null) return;

        var alreadyRead = message.Receipts.Any(r => r.UserId == userId);
        if (!alreadyRead)
        {
            var receipt = new MessageReceipt
            {
                Id = Guid.NewGuid(),
                MessageId = message.Id,
                UserId = userId,
                ReadAt = DateTime.UtcNow
            };

            await _context.MessageReceipts.AddAsync(receipt);
            await _context.SaveChangesAsync();

            // Thông báo cho người gửi tin nhắn biết là đối phương đã xem
            await Clients.Group($"user_{message.SenderId}")
                .MessageRead(messageId, userId, receipt.ReadAt);
        }
    }

    private (int UserId, string UserName, string DepartmentCode) GetCurrentUser()
    {
        var httpContext = Context.GetHttpContext();
        var user = Context.User;

        var subStr = user?.FindFirst(ClaimTypes.NameIdentifier)?.Value
                     ?? httpContext?.Items["UserId"]?.ToString();

        int.TryParse(subStr, out var userId);

        var userName = user?.FindFirst(ClaimTypes.Name)?.Value
                       ?? httpContext?.Items["UserName"]?.ToString()
                       ?? $"User #{userId}";

        var deptCode = user?.FindFirst("dept")?.Value
                       ?? httpContext?.Items["DepartmentCode"]?.ToString()
                       ?? string.Empty;

        return (userId, userName, deptCode);
    }
}
