using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Application.Dtos;
using Chat.Domain.Entities;
using Chat.Domain.Enums;

namespace Chat.Application.Services;

/// <summary>
/// Phần dùng chung của ConversationService và MessageService: kiểm tra thành viên, dựng payload, đẩy sự kiện.
/// Quy tắc quyền: mọi thao tác trên hội thoại yêu cầu là thành viên; người ngoài nhận 404 để không lộ hội thoại tồn tại.
/// </summary>
public abstract class ChatServiceBase(
    IChatRepository repository,
    ICurrentUser currentUser,
    IUserDirectory directory,
    IChatNotifier notifier)
{
    protected readonly IChatRepository Repo = repository;
    protected readonly ICurrentUser Me = currentUser;
    protected readonly IUserDirectory Directory = directory;
    protected readonly IChatNotifier Notifier = notifier;

    private IReadOnlyDictionary<long, DirectoryUser>? _users;

    public static class Events
    {
        public const string ConversationUpserted = "conversation.upserted";
        public const string ConversationRemoved = "conversation.removed";
        public const string MessageCreated = "message.created";
        public const string MessageUpdated = "message.updated";
        public const string MessageHidden = "message.hidden";
        public const string ReadUpdated = "read.updated";
        public const string Typing = "typing";
        public const string Presence = "presence";
    }

    // ---------- Truy cập ----------

    protected async Task<(Conversation Conversation, ConversationMember Me)> RequireMemberAsync(long conversationId, CancellationToken ct)
    {
        var conversation = await Repo.GetConversationAsync(conversationId, ct);
        var member = conversation?.Members.FirstOrDefault(m => m.UserId == Me.Id);
        if (conversation == null || member == null) throw ChatException.NotFound();
        return (conversation, member);
    }

    protected static void RequireGroup(Conversation conversation)
    {
        if (conversation.Type != ConversationType.Group) throw ChatException.Invalid("Chỉ áp dụng cho nhóm chat.");
    }

    protected static void RequireRole(ConversationMember member, MemberRole minimum, string message)
    {
        if (member.Role < minimum) throw ChatException.Forbidden(message);
    }

    // ---------- Danh bạ ----------

    /// <summary>Danh bạ để hiển thị: lỗi identity-service thì dùng tên đã lưu thay vì làm hỏng request.</summary>
    protected async Task<IReadOnlyDictionary<long, DirectoryUser>> UsersForDisplayAsync(CancellationToken ct)
    {
        if (_users != null) return _users;
        try
        {
            _users = await Directory.GetUsersAsync(ct);
        }
        catch (ChatException)
        {
            _users = new Dictionary<long, DirectoryUser>();
        }
        return _users;
    }

    /// <summary>Danh bạ để kiểm tra dữ liệu (thêm người, tạo hội thoại): lỗi thì chặn thao tác.</summary>
    protected async Task<IReadOnlyDictionary<long, DirectoryUser>> UsersForValidationAsync(CancellationToken ct) =>
        _users = await Directory.GetUsersAsync(ct);

    protected string NameOf(long userId, string fallback) =>
        _users != null && _users.TryGetValue(userId, out var user) ? user.Name : fallback;

    // ---------- Payload ----------

    protected static string RoleName(MemberRole role) => role switch
    {
        MemberRole.Owner => "owner",
        MemberRole.Deputy => "deputy",
        _ => "member"
    };

    protected static string TypeName(MessageType type) => type switch
    {
        MessageType.File => "file",
        MessageType.System => "system",
        _ => "text"
    };

    protected static string? AvatarUrl(Conversation c) =>
        c.AvatarPath == null ? null : $"/api/v1/chat/conversations/{c.Id}/avatar?v={c.AvatarVersion}";

    protected MessagePreviewDto? Preview(ChatMessage? m)
    {
        if (m == null) return null;
        var content = m.RecalledAt != null ? "Tin nhắn đã được thu hồi"
            : m.Type == MessageType.File ? "[Tệp đính kèm]"
            : m.Content.Length > 120 ? m.Content[..120] : m.Content;
        var senderName = m.SenderId is { } sid ? NameOf(sid, m.SenderName) : string.Empty;
        return new MessagePreviewDto(m.Id, m.SenderId, senderName, TypeName(m.Type), content, m.RecalledAt != null, m.CreatedAt);
    }

    protected ConversationDto ToConversationDto(Conversation c, ConversationMember viewer, ChatMessage? last, int unread)
    {
        var members = c.Members
            .OrderByDescending(m => m.Role).ThenBy(m => m.JoinedAt)
            .Select(m => new MemberDto(m.UserId, NameOf(m.UserId, m.UserName), RoleName(m.Role), m.LastReadMessageId,
                _users == null || !_users.TryGetValue(m.UserId, out var u) || u.IsActive))
            .ToList();
        var typeStr = c.Type switch
        {
            ConversationType.Group => "group",
            ConversationType.Work => "work",
            _ => "direct"
        };
        return new ConversationDto(c.Id, typeStr, c.Title, AvatarUrl(c),
            RoleName(viewer.Role), members, Preview(last), c.LastMessageAt, unread, viewer.Muted, viewer.PinnedAt, c.CreatedAt,
            c.SubjectType, c.SubjectId, c.SubjectCode, c.SubjectTitle);
    }

    protected MessageDto ToMessageDto(ChatMessage m, ChatMessage? replyTo)
    {
        var recalled = m.RecalledAt != null;
        ReplyDto? reply = replyTo == null ? null : new ReplyDto(
            replyTo.Id,
            replyTo.SenderId is { } rid ? NameOf(rid, replyTo.SenderName) : replyTo.SenderName,
            replyTo.RecalledAt != null ? string.Empty : replyTo.Content,
            replyTo.RecalledAt != null);

        var reactions = m.ReactionList
            .OrderBy(r => r.CreatedAt)
            .GroupBy(r => r.Emoji)
            .Select(g => new ReactionDto(g.Key, g.Select(r => new ReactionUserDto(r.UserId, NameOf(r.UserId, r.UserName))).ToList()))
            .ToList();

        var attachments = recalled ? [] : m.Attachments
            .Select(a => new AttachmentDto(a.Id, a.FileName, a.Size, a.ContentType, FileRules.IsImage(a.ContentType)))
            .ToList();

        return new MessageDto(
            m.Id, m.ConversationId, TypeName(m.Type),
            m.SenderId is { } sid ? new SenderDto(sid, NameOf(sid, m.SenderName)) : null,
            recalled ? string.Empty : m.Content,
            recalled, reply, m.MentionIds, m.MentionAll, m.ForwardedFromName,
            attachments, reactions, m.EditedAt, m.PinnedAt, m.PinnedById, m.CreatedAt);
    }

    /// <summary>Dựng payload cho nhiều tin; các tin được trả lời được nạp bằng một truy vấn.</summary>
    protected async Task<List<MessageDto>> ToMessageDtosAsync(IReadOnlyCollection<ChatMessage> messages, CancellationToken ct)
    {
        var replyIds = messages.Where(m => m.ReplyToId != null).Select(m => m.ReplyToId!.Value).Distinct().ToList();
        var replies = (await Repo.GetMessagesAsync(replyIds, ct)).ToDictionary(m => m.Id);
        return messages.Select(m => ToMessageDto(m, m.ReplyToId is { } id ? replies.GetValueOrDefault(id) : null)).ToList();
    }

    /// <summary>Số tin chưa đọc theo hội thoại: tin của người khác, sau con trỏ đọc, chưa thu hồi, chưa bị ẩn.</summary>
    protected Task<Dictionary<long, int>> UnreadCountsAsync(long userId, IReadOnlyCollection<ReadCursor> cursors, CancellationToken ct) =>
        Repo.UnreadCountsAsync(userId, cursors, ct);

    protected static ReadCursor CursorOf(Conversation conversation, ConversationMember member) =>
        new(conversation.Id, member.LastReadMessageId, member.Muted);

    // ---------- Tin hệ thống & sự kiện ----------

    /// <summary>Lưu một tin hệ thống và đẩy con trỏ tin cuối của hội thoại tới tin đó.</summary>
    protected async Task<ChatMessage> PostSystemMessageAsync(Conversation conversation, string content, CancellationToken ct)
    {
        var message = new ChatMessage
        {
            ConversationId = conversation.Id,
            Type = MessageType.System,
            Content = content,
            CreatedAt = DateTime.UtcNow
        };
        await Repo.InsertMessagesAsync([message], ct);
        await TouchLastMessageAsync(conversation, message, null, ct);
        return message;
    }

    /// <summary>
    /// Cập nhật tin cuối trong DB và trong đối tượng đang giữ (để payload trả về đúng ngay);
    /// nếu có <paramref name="readByUserId"/> thì người đó coi như đã đọc tới tin này.
    /// </summary>
    protected async Task TouchLastMessageAsync(Conversation conversation, ChatMessage message, long? readByUserId, CancellationToken ct)
    {
        await Repo.TouchLastMessageAsync(conversation.Id, message.Id, message.CreatedAt, readByUserId, ct);
        if (conversation.LastMessageId == null || conversation.LastMessageId < message.Id)
        {
            conversation.LastMessageId = message.Id;
            conversation.LastMessageAt = message.CreatedAt;
        }
        conversation.UpdatedAt = DateTime.UtcNow;
        if (readByUserId is { } userId
            && conversation.Members.FirstOrDefault(m => m.UserId == userId) is { } member
            && (member.LastReadMessageId == null || member.LastReadMessageId < message.Id))
        {
            member.LastReadMessageId = message.Id;
            member.LastReadAt = message.CreatedAt;
        }
    }

    protected async Task NotifyMessageAsync(Conversation conversation, ChatMessage message, string eventName, CancellationToken ct)
    {
        var dto = (await ToMessageDtosAsync([message], ct))[0];
        await Notifier.ToUsersAsync(conversation.Members.Select(m => m.UserId), eventName, new { message = dto });
    }

    /// <summary>Gửi hội thoại đã cập nhật cho từng thành viên (payload riêng từng người vì có unread, mute, ghim).</summary>
    protected async Task NotifyConversationAsync(Conversation conversation, CancellationToken ct)
    {
        var last = conversation.LastMessageId is { } lastId ? await Repo.GetMessageAsync(lastId, ct) : null;
        foreach (var member in conversation.Members)
        {
            var unread = (await UnreadCountsAsync(member.UserId, [CursorOf(conversation, member)], ct)).GetValueOrDefault(conversation.Id);
            await Notifier.ToUsersAsync([member.UserId], Events.ConversationUpserted,
                new { conversation = ToConversationDto(conversation, member, last, unread) });
        }
    }

    protected Task NotifyRemovedAsync(IEnumerable<long> userIds, long conversationId, string reason) =>
        Notifier.ToUsersAsync(userIds, Events.ConversationRemoved, new { conversation_id = conversationId, reason });
}
