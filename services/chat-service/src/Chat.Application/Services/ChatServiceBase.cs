using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Application.Dtos;
using Chat.Domain.Entities;
using Chat.Domain.Enums;
using Microsoft.EntityFrameworkCore;

namespace Chat.Application.Services;

/// <summary>
/// Phần dùng chung của ConversationService và MessageService: kiểm tra thành viên, dựng payload, đẩy sự kiện.
/// Quy tắc quyền: mọi thao tác trên hội thoại yêu cầu là thành viên; người ngoài nhận 404 để không lộ hội thoại tồn tại.
/// </summary>
public abstract class ChatServiceBase(
    IChatDbContext db,
    ICurrentUser currentUser,
    IUserDirectory directory,
    IChatNotifier notifier)
{
    protected readonly IChatDbContext Db = db;
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
        var conversation = await Db.Conversations.Include(c => c.Members).FirstOrDefaultAsync(c => c.Id == conversationId, ct);
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

    protected MessageDto ToMessageDto(ChatMessage m)
    {
        var recalled = m.RecalledAt != null;
        ReplyDto? reply = m.ReplyTo == null ? null : new ReplyDto(
            m.ReplyTo.Id,
            m.ReplyTo.SenderId is { } rid ? NameOf(rid, m.ReplyTo.SenderName) : m.ReplyTo.SenderName,
            m.ReplyTo.RecalledAt != null ? string.Empty : m.ReplyTo.Content,
            m.ReplyTo.RecalledAt != null);

        var reactions = m.ReactionList
            .OrderBy(r => r.Id)
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

    protected IQueryable<ChatMessage> MessagesWithParts() =>
        Db.ChatMessages.Include(m => m.ReplyTo).Include(m => m.Attachments).Include(m => m.ReactionList);

    /// <summary>Số tin chưa đọc theo hội thoại của một người: tin của người khác, sau con trỏ đọc, chưa thu hồi, chưa bị ẩn.</summary>
    protected async Task<Dictionary<long, int>> UnreadCountsAsync(long userId, IReadOnlyCollection<long>? conversationIds, CancellationToken ct)
    {
        var query =
            from m in Db.ChatMessages
            join mem in Db.ConversationMembers on m.ConversationId equals mem.ConversationId
            where mem.UserId == userId
                  && m.SenderId != null && m.SenderId != userId
                  && m.RecalledAt == null
                  && (mem.LastReadMessageId == null || m.Id > mem.LastReadMessageId)
                  && !Db.MessageHides.Any(h => h.MessageId == m.Id && h.UserId == userId)
            select m.ConversationId;
        if (conversationIds != null) query = query.Where(id => conversationIds.Contains(id));
        return await query.GroupBy(id => id).Select(g => new { g.Key, Count = g.Count() }).ToDictionaryAsync(x => x.Key, x => x.Count, ct);
    }

    // ---------- Tin hệ thống & sự kiện ----------

    protected ChatMessage AddSystemMessage(Conversation conversation, string content)
    {
        var message = new ChatMessage
        {
            ConversationId = conversation.Id,
            Type = MessageType.System,
            Content = content,
            CreatedAt = DateTime.UtcNow
        };
        Db.ChatMessages.Add(message);
        return message;
    }

    /// <summary>Gọi sau SaveChanges để cập nhật con trỏ tin cuối của hội thoại.</summary>
    protected static void TouchLastMessage(Conversation conversation, ChatMessage message)
    {
        conversation.LastMessageId = message.Id;
        conversation.LastMessageAt = message.CreatedAt;
        conversation.UpdatedAt = DateTime.UtcNow;
    }

    protected Task NotifyMessageAsync(Conversation conversation, ChatMessage message, string eventName) =>
        Notifier.ToUsersAsync(conversation.Members.Select(m => m.UserId), eventName, new { message = ToMessageDto(message) });

    /// <summary>Gửi hội thoại đã cập nhật cho từng thành viên (payload riêng từng người vì có unread, mute, ghim).</summary>
    protected async Task NotifyConversationAsync(Conversation conversation, CancellationToken ct)
    {
        var last = conversation.LastMessageId is { } lastId
            ? await Db.ChatMessages.AsNoTracking().FirstOrDefaultAsync(m => m.Id == lastId, ct)
            : null;
        foreach (var member in conversation.Members)
        {
            var unread = (await UnreadCountsAsync(member.UserId, [conversation.Id], ct)).GetValueOrDefault(conversation.Id);
            await Notifier.ToUsersAsync([member.UserId], Events.ConversationUpserted,
                new { conversation = ToConversationDto(conversation, member, last, unread) });
        }
    }

    protected Task NotifyRemovedAsync(IEnumerable<long> userIds, long conversationId, string reason) =>
        Notifier.ToUsersAsync(userIds, Events.ConversationRemoved, new { conversation_id = conversationId, reason });
}
