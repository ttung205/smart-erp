using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Application.DTOs;
using Chat.Domain.Entities;
using Chat.Domain.Enums;
using Chat.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace Chat.Api.Controllers;

[ApiController]
[Route("api/v1/chat/conversations")]
public class ConversationsController : ControllerBase
{
    private readonly ChatDbContext _context;
    private readonly ICurrentUserService _currentUser;
    private readonly IChatHubService _hubService;
    private readonly ILogger<ConversationsController> _logger;

    public ConversationsController(
        ChatDbContext context,
        ICurrentUserService currentUser,
        IChatHubService hubService,
        ILogger<ConversationsController> logger)
    {
        _context = context;
        _currentUser = currentUser;
        _hubService = hubService;
        _logger = logger;
    }

    /// <summary>
    /// Lấy danh sách các phòng ban và cuộc hội thoại 1-1 của người dùng hiện tại
    /// </summary>
    [HttpGet]
    public async Task<IActionResult> GetConversations(CancellationToken ct)
    {
        var currentUserId = _currentUser.UserId ?? 0;

        // 1. Query danh sách hội thoại mà user có quyền xem
        var conversations = await _context.Conversations
            .AsNoTracking()
            .Where(c => c.Type == ConversationType.Channel || 
                        c.Members.Any(m => m.UserId == currentUserId))
            .Include(c => c.Members)
            .ToListAsync(ct);

        var convIds = conversations.Select(c => c.Id).ToList();

        // 2. Lấy tin nhắn mới nhất của từng hội thoại (tránh SQL APPLY trên SQLite)
        var recentMessages = await _context.ChatMessages
            .AsNoTracking()
            .Where(m => convIds.Contains(m.ConversationId))
            .Include(m => m.Receipts)
            .OrderByDescending(m => m.CreatedAt)
            .ToListAsync(ct);

        var latestMessageByConv = recentMessages
            .GroupBy(m => m.ConversationId)
            .ToDictionary(g => g.Key, g => g.First());

        // 3. Đếm số lượng tin nhắn chưa đọc đối với user hiện tại
        var unreadCounts = new Dictionary<Guid, int>();
        if (currentUserId > 0)
        {
            var unreadGroup = await _context.ChatMessages
                .AsNoTracking()
                .Where(m => convIds.Contains(m.ConversationId) && 
                            m.SenderId != currentUserId && 
                            !m.Receipts.Any(r => r.UserId == currentUserId))
                .GroupBy(m => m.ConversationId)
                .Select(g => new { ConversationId = g.Key, Count = g.Count() })
                .ToListAsync(ct);

            unreadCounts = unreadGroup.ToDictionary(x => x.ConversationId, x => x.Count);
        }

        // 4. Map sang DTO và sắp xếp (kênh phòng ban trước, theo tin nhắn gần nhất)
        var result = conversations
            .OrderBy(c => c.Type == ConversationType.Channel ? 0 : 1)
            .ThenByDescending(c => latestMessageByConv.TryGetValue(c.Id, out var msg) ? msg.CreatedAt : c.CreatedAt)
            .Select(conv =>
            {
                ChatMessageDto? lastMsgDto = null;
                if (latestMessageByConv.TryGetValue(conv.Id, out var lastMsg))
                {
                    lastMsgDto = new ChatMessageDto
                    {
                        Id = lastMsg.Id,
                        ConversationId = lastMsg.ConversationId,
                        SenderId = lastMsg.SenderId,
                        SenderName = lastMsg.SenderName,
                        SenderAvatar = lastMsg.SenderAvatar,
                        MessageType = lastMsg.MessageType.ToString().ToUpper(),
                        Content = lastMsg.Content,
                        AttachmentUrl = lastMsg.AttachmentUrl,
                        CreatedAt = lastMsg.CreatedAt,
                        ReadBy = lastMsg.Receipts.Select(r => r.UserId).ToList()
                    };
                }

                unreadCounts.TryGetValue(conv.Id, out var unreadCount);

                return new ConversationDto
                {
                    Id = conv.Id,
                    Type = conv.Type.ToString(),
                    Code = conv.Code,
                    Name = conv.Name,
                    DepartmentCode = conv.DepartmentCode,
                    Description = conv.Description,
                    CreatedAt = conv.CreatedAt,
                    LastMessage = lastMsgDto,
                    UnreadCount = unreadCount,
                    Members = conv.Members.Select(m => new ConversationMemberDto
                    {
                        Id = m.Id,
                        UserId = m.UserId,
                        UserName = m.UserName,
                        UserEmail = m.UserEmail,
                        UserAvatar = m.UserAvatar,
                        Role = m.Role.ToString(),
                        JoinedAt = m.JoinedAt
                    }).ToList()
                };
            })
            .ToList();

        return Ok(ApiEnvelope<List<ConversationDto>>.Ok(result));
    }

    /// <summary>
    /// Lấy chi tiết một cuộc hội thoại
    /// </summary>
    [HttpGet("{id:guid}")]
    public async Task<IActionResult> GetById(Guid id, CancellationToken ct)
    {
        var conv = await _context.Conversations
            .AsNoTracking()
            .Include(c => c.Members)
            .FirstOrDefaultAsync(c => c.Id == id, ct);

        if (conv == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("CONVERSATION_NOT_FOUND", "Không tìm thấy hội thoại."));
        }

        var dto = new ConversationDto
        {
            Id = conv.Id,
            Type = conv.Type.ToString(),
            Code = conv.Code,
            Name = conv.Name,
            DepartmentCode = conv.DepartmentCode,
            Description = conv.Description,
            CreatedAt = conv.CreatedAt,
            Members = conv.Members.Select(m => new ConversationMemberDto
            {
                Id = m.Id,
                UserId = m.UserId,
                UserName = m.UserName,
                UserEmail = m.UserEmail,
                UserAvatar = m.UserAvatar,
                Role = m.Role.ToString(),
                JoinedAt = m.JoinedAt
            }).ToList()
        };

        return Ok(ApiEnvelope<ConversationDto>.Ok(dto));
    }

    /// <summary>
    /// Phân trang lịch sử tin nhắn trong một cuộc trò chuyện
    /// </summary>
    [HttpGet("{id:guid}/messages")]
    public async Task<IActionResult> GetMessages(
        Guid id,
        [FromQuery] int page = 1,
        [FromQuery] int pageSize = 50,
        [FromQuery] Guid? beforeMessageId = null,
        CancellationToken ct = default)
    {
        if (page < 1) page = 1;
        if (pageSize < 1 || pageSize > 100) pageSize = 50;

        var conversationExists = await _context.Conversations
            .AsNoTracking()
            .AnyAsync(c => c.Id == id, ct);

        if (!conversationExists)
        {
            return NotFound(ApiEnvelope<object>.Fail("CONVERSATION_NOT_FOUND", "Không tìm thấy hội thoại."));
        }

        var query = _context.ChatMessages
            .AsNoTracking()
            .Where(m => m.ConversationId == id);

        // Hỗ trợ cursor-based pagination theo beforeMessageId nếu truyền vào
        if (beforeMessageId.HasValue)
        {
            var targetMessage = await _context.ChatMessages
                .AsNoTracking()
                .FirstOrDefaultAsync(m => m.Id == beforeMessageId.Value, ct);

            if (targetMessage != null)
            {
                query = query.Where(m => m.CreatedAt < targetMessage.CreatedAt);
            }
        }

        var totalItems = await query.CountAsync(ct);
        var totalPages = (int)Math.Ceiling(totalItems / (double)pageSize);

        // Lấy danh sách tin nhắn mới nhất trước rồi sắp xếp lại tăng dần theo thời gian
        var messages = await query
            .OrderByDescending(m => m.CreatedAt)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .Include(m => m.Receipts)
            .ToListAsync(ct);

        // Đảo lại theo thứ tự thời gian tăng dần để render từ trên xuống dưới khung chat
        messages.Reverse();

        var messageDtos = messages.Select(m => new ChatMessageDto
        {
            Id = m.Id,
            ConversationId = m.ConversationId,
            SenderId = m.SenderId,
            SenderName = m.SenderName,
            SenderAvatar = m.SenderAvatar,
            MessageType = m.MessageType.ToString().ToUpper(),
            Content = m.Content,
            AttachmentUrl = m.AttachmentUrl,
            MetadataJson = m.MetadataJson,
            CreatedAt = m.CreatedAt,
            ReadBy = m.Receipts.Select(r => r.UserId).ToList()
        }).ToList();

        var meta = new ApiMeta
        {
            Pagination = new PaginationMeta
            {
                CurrentPage = page,
                PerPage = pageSize,
                TotalItems = totalItems,
                TotalPages = totalPages
            }
        };

        return Ok(ApiEnvelope<List<ChatMessageDto>>.Ok(messageDtos, meta));
    }

    /// <summary>
    /// Gửi tin nhắn qua REST API
    /// </summary>
    [HttpPost("{id:guid}/messages")]
    public async Task<IActionResult> SendMessage(
        Guid id,
        [FromBody] SendChannelMessageRequest request,
        CancellationToken ct)
    {
        var currentUserId = _currentUser.UserId ?? 0;
        var currentUserName = _currentUser.UserName ?? $"User #{currentUserId}";

        if (string.IsNullOrWhiteSpace(request.Content))
        {
            return BadRequest(ApiEnvelope<object>.Fail("VALIDATION_FAILED", "Nội dung tin nhắn không được rỗng."));
        }

        var conversation = await _context.Conversations
            .FirstOrDefaultAsync(c => c.Id == id, ct);

        if (conversation == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("CONVERSATION_NOT_FOUND", "Không tìm thấy hội thoại."));
        }

        var message = new ChatMessage
        {
            Id = Guid.NewGuid(),
            ConversationId = conversation.Id,
            SenderId = currentUserId,
            SenderName = currentUserName,
            MessageType = string.IsNullOrEmpty(request.AttachmentUrl) ? MessageType.Text : MessageType.Attachment,
            Content = request.Content.Trim(),
            AttachmentUrl = request.AttachmentUrl,
            CreatedAt = DateTime.UtcNow
        };

        await _context.ChatMessages.AddAsync(message, ct);
        await _context.SaveChangesAsync(ct);

        var dto = new ChatMessageDto
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
            ReadBy = new List<int> { currentUserId }
        };

        // Bắn realtime SignalR tới các client đang lắng nghe hội thoại / phòng ban
        await _hubService.SendMessageToConversationAsync(conversation.Id, dto);
        if (!string.IsNullOrEmpty(conversation.DepartmentCode))
        {
            await _hubService.SendMessageToChannelAsync(conversation.DepartmentCode, dto);
        }

        return Ok(ApiEnvelope<ChatMessageDto>.Ok(dto));
    }

    /// <summary>
    /// Khởi tạo cuộc trò chuyện 1-1 với một người dùng khác
    /// </summary>
    [HttpPost("direct")]
    public async Task<IActionResult> GetOrCreateDirectConversation(
        [FromBody] SendDirectMessageRequest request,
        CancellationToken ct)
    {
        var currentUserId = _currentUser.UserId ?? 0;
        var currentUserName = _currentUser.UserName ?? $"User #{currentUserId}";

        if (request.RecipientId <= 0)
        {
            return BadRequest(ApiEnvelope<object>.Fail("VALIDATION_FAILED", "ID người nhận không hợp lệ."));
        }

        var minId = Math.Min(currentUserId, request.RecipientId);
        var maxId = Math.Max(currentUserId, request.RecipientId);
        var dmCode = $"dm:{minId}_{maxId}";

        var conversation = await _context.Conversations
            .Include(c => c.Members)
            .FirstOrDefaultAsync(c => c.Code == dmCode, ct);

        if (conversation == null)
        {
            var recipientName = !string.IsNullOrWhiteSpace(request.RecipientName) 
                ? request.RecipientName 
                : $"User #{request.RecipientId}";

            conversation = new Conversation
            {
                Id = Guid.NewGuid(),
                Type = ConversationType.Direct,
                Code = dmCode,
                Name = $"{currentUserName} & {recipientName}",
                CreatedAt = DateTime.UtcNow
            };

            conversation.Members.Add(new ConversationMember
            {
                Id = Guid.NewGuid(),
                ConversationId = conversation.Id,
                UserId = currentUserId,
                UserName = currentUserName,
                Role = MemberRole.Admin,
                JoinedAt = DateTime.UtcNow
            });

            conversation.Members.Add(new ConversationMember
            {
                Id = Guid.NewGuid(),
                ConversationId = conversation.Id,
                UserId = request.RecipientId,
                UserName = recipientName,
                Role = MemberRole.Member,
                JoinedAt = DateTime.UtcNow
            });

            await _context.Conversations.AddAsync(conversation, ct);
            await _context.SaveChangesAsync(ct);
        }

        var dto = new ConversationDto
        {
            Id = conversation.Id,
            Type = conversation.Type.ToString(),
            Code = conversation.Code,
            Name = conversation.Name,
            CreatedAt = conversation.CreatedAt,
            Members = conversation.Members.Select(m => new ConversationMemberDto
            {
                Id = m.Id,
                UserId = m.UserId,
                UserName = m.UserName,
                Role = m.Role.ToString(),
                JoinedAt = m.JoinedAt
            }).ToList()
        };

        return Ok(ApiEnvelope<ConversationDto>.Ok(dto));
    }
}
