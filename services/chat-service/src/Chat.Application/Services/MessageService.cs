using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Application.Dtos;
using Chat.Domain.Entities;
using Chat.Domain.Enums;
using Microsoft.EntityFrameworkCore;

namespace Chat.Application.Services;

public class MessageService(
    IChatDbContext db,
    ICurrentUser currentUser,
    IUserDirectory directory,
    IChatNotifier notifier,
    IFileStorage storage) : ChatServiceBase(db, currentUser, directory, notifier)
{
    public const int MaxForwardTargets = 10;

    // ---------- Đọc ----------

    /// <summary>
    /// Phân trang theo id: before_id lấy tin cũ hơn, after_id lấy tin mới hơn (dùng để tải bù khi kết nối lại).
    /// Bỏ các tin người xem đã "xoá phía tôi".
    /// </summary>
    public async Task<MessagePageDto> ListAsync(long conversationId, long? beforeId, long? afterId, int limit, CancellationToken ct)
    {
        await RequireMemberAsync(conversationId, ct);
        limit = Math.Clamp(limit, 1, 100);
        var query = MessagesWithParts().AsNoTracking()
            .Where(m => m.ConversationId == conversationId && !Db.MessageHides.Any(h => h.MessageId == m.Id && h.UserId == Me.Id));

        List<ChatMessage> messages;
        bool hasMore;
        if (afterId is { } after)
        {
            messages = await query.Where(m => m.Id > after).OrderBy(m => m.Id).Take(limit + 1).ToListAsync(ct);
            hasMore = messages.Count > limit;
            messages = messages.Take(limit).ToList();
        }
        else
        {
            if (beforeId is { } before) query = query.Where(m => m.Id < before);
            messages = await query.OrderByDescending(m => m.Id).Take(limit + 1).ToListAsync(ct);
            hasMore = messages.Count > limit;
            messages = messages.Take(limit).OrderBy(m => m.Id).ToList();
        }

        await UsersForDisplayAsync(ct);
        return new MessagePageDto(messages.Select(ToMessageDto).ToList(), hasMore);
    }

    public async Task<IReadOnlyList<MessageDto>> PinsAsync(long conversationId, CancellationToken ct)
    {
        await RequireMemberAsync(conversationId, ct);
        var pins = await MessagesWithParts().AsNoTracking()
            .Where(m => m.ConversationId == conversationId && m.PinnedAt != null)
            .OrderByDescending(m => m.PinnedAt).ThenByDescending(m => m.Id)
            .ToListAsync(ct);
        await UsersForDisplayAsync(ct);
        return pins.Select(ToMessageDto).ToList();
    }

    public async Task<IReadOnlyList<SearchHitDto>> SearchAsync(long conversationId, string? q, CancellationToken ct)
    {
        await RequireMemberAsync(conversationId, ct);
        q = q?.Trim();
        if (string.IsNullOrEmpty(q) || q.Length < 2 || q.Length > 100) throw ChatException.Invalid("Từ khoá tìm kiếm từ 2 đến 100 ký tự.");
        var hits = await Db.ChatMessages.AsNoTracking()
            .Where(m => m.ConversationId == conversationId && m.Type != MessageType.System && m.RecalledAt == null
                        && m.Content.Contains(q)
                        && !Db.MessageHides.Any(h => h.MessageId == m.Id && h.UserId == Me.Id))
            .OrderByDescending(m => m.Id).Take(50)
            .ToListAsync(ct);
        await UsersForDisplayAsync(ct);
        return hits.Select(m => new SearchHitDto(m.Id, m.Content,
            m.SenderId is { } sid ? new SenderDto(sid, NameOf(sid, m.SenderName)) : null, m.CreatedAt)).ToList();
    }

    /// <summary>Dùng cho hub: chỉ thành viên mới phát được tín hiệu đang soạn tin.</summary>
    public async Task<IReadOnlyList<long>?> TypingRecipientsAsync(long conversationId, CancellationToken ct)
    {
        var memberIds = await Db.ConversationMembers.Where(m => m.ConversationId == conversationId).Select(m => m.UserId).ToListAsync(ct);
        return memberIds.Contains(Me.Id) ? memberIds.Where(id => id != Me.Id).ToList() : null;
    }

    // ---------- Gửi ----------

    public async Task<MessageDto> SendAsync(long conversationId, SendMessageInput input, CancellationToken ct)
    {
        var (conversation, _) = await RequireMemberAsync(conversationId, ct);
        var content = input.Content?.Trim() ?? string.Empty;
        if (content.Length == 0 && input.Files.Count == 0) throw ChatException.Invalid("Tin nhắn cần có nội dung hoặc tệp đính kèm.");
        if (content.Length > ChatMessage.MaxContentLength) throw ChatException.Invalid($"Tin nhắn tối đa {ChatMessage.MaxContentLength} ký tự.");
        if (input.Files.Count > FileRules.MaxFilesPerMessage) throw ChatException.Invalid($"Tối đa {FileRules.MaxFilesPerMessage} tệp mỗi tin.");
        var fileRules = input.Files.Select(FileRules.ValidateAttachment).ToList();

        if (input.ReplyToId is { } replyId && !await Db.ChatMessages.AnyAsync(m => m.Id == replyId && m.ConversationId == conversationId, ct))
            throw ChatException.Invalid("Tin nhắn được trả lời không thuộc hội thoại này.");

        var mentions = (input.Mentions ?? []).Distinct().ToList();
        if ((mentions.Count > 0 || input.MentionAll) && conversation.Type != ConversationType.Group)
            throw ChatException.Invalid("Chỉ nhóm chat mới nhắc tên được.");
        var memberIds = conversation.Members.Select(m => m.UserId).ToHashSet();
        if (mentions.Any(id => !memberIds.Contains(id))) throw ChatException.Invalid("Chỉ nhắc tên được thành viên trong nhóm.");
        if (input.MentionAll) mentions = memberIds.Where(id => id != Me.Id).ToList();

        var message = new ChatMessage
        {
            ConversationId = conversationId,
            SenderId = Me.Id,
            SenderName = Me.Name,
            Type = content.Length == 0 ? MessageType.File : MessageType.Text,
            Content = content,
            ReplyToId = input.ReplyToId,
            Mentions = mentions.Count > 0 ? string.Join(',', mentions) : null,
            MentionAll = input.MentionAll,
            CreatedAt = DateTime.UtcNow
        };

        var savedPaths = new List<string>();
        try
        {
            for (var i = 0; i < input.Files.Count; i++)
            {
                var file = input.Files[i];
                await using var stream = file.OpenReadStream();
                var path = await storage.SaveAsync(stream, fileRules[i].Extension, ct);
                savedPaths.Add(path);
                message.Attachments.Add(new MessageAttachment
                {
                    FileName = FileRules.SafeFileName(file.FileName), ContentType = fileRules[i].ContentType, Size = file.Size, StoragePath = path
                });
            }
            Db.ChatMessages.Add(message);
            await Db.SaveChangesAsync(ct);
        }
        catch
        {
            foreach (var path in savedPaths) storage.Delete(path);
            throw;
        }

        // Người gửi coi như đã đọc tới tin của mình
        var me = conversation.Members.First(m => m.UserId == Me.Id);
        me.LastReadMessageId = message.Id;
        me.LastReadAt = DateTime.UtcNow;
        TouchLastMessage(conversation, message);
        await Db.SaveChangesAsync(ct);

        return await PublishAsync(conversation, message.Id, Events.MessageCreated, ct);
    }

    public async Task<IReadOnlyList<long>> ForwardAsync(long messageId, IReadOnlyCollection<long> conversationIds, CancellationToken ct)
    {
        var source = await LoadMessageForMemberAsync(messageId, ct);
        if (source.Type == MessageType.System || source.RecalledAt != null) throw ChatException.Invalid("Không thể chuyển tiếp tin này.");
        var targetIds = conversationIds.Distinct().ToList();
        if (targetIds.Count is < 1 or > MaxForwardTargets) throw ChatException.Invalid($"Chọn từ 1 đến {MaxForwardTargets} hội thoại.");

        var targets = await Db.Conversations.Include(c => c.Members)
            .Where(c => targetIds.Contains(c.Id) && c.Members.Any(m => m.UserId == Me.Id))
            .ToListAsync(ct);
        if (targets.Count != targetIds.Count) throw ChatException.Invalid("Có hội thoại bạn không thể gửi vào.");

        await UsersForDisplayAsync(ct);
        var originalSender = source.SenderId is { } sid ? NameOf(sid, source.SenderName) : source.SenderName;
        var created = new List<(Conversation Conversation, ChatMessage Message)>();
        foreach (var target in targets)
        {
            var copy = new ChatMessage
            {
                ConversationId = target.Id, SenderId = Me.Id, SenderName = Me.Name, Type = source.Type, Content = source.Content,
                ForwardedFromName = source.ForwardedFromName ?? originalSender, CreatedAt = DateTime.UtcNow
            };
            foreach (var a in source.Attachments)
                copy.Attachments.Add(new MessageAttachment
                {
                    FileName = a.FileName, ContentType = a.ContentType, Size = a.Size, StoragePath = await storage.CopyAsync(a.StoragePath, ct)
                });
            Db.ChatMessages.Add(copy);
            created.Add((target, copy));
        }
        await Db.SaveChangesAsync(ct);

        foreach (var (target, copy) in created)
        {
            var me = target.Members.First(m => m.UserId == Me.Id);
            me.LastReadMessageId = copy.Id;
            me.LastReadAt = DateTime.UtcNow;
            TouchLastMessage(target, copy);
        }
        await Db.SaveChangesAsync(ct);
        foreach (var (target, copy) in created) await PublishAsync(target, copy.Id, Events.MessageCreated, ct);
        return created.Select(x => x.Message.Id).ToList();
    }

    // ---------- Thao tác trên tin ----------

    public async Task<MessageDto> EditAsync(long messageId, string? content, CancellationToken ct)
    {
        var message = await LoadMessageForMemberAsync(messageId, ct);
        if (message.SenderId != Me.Id) throw ChatException.Forbidden("Chỉ người gửi được sửa tin nhắn.");
        if (message.RecalledAt != null || message.Type == MessageType.System) throw ChatException.Invalid("Không thể sửa tin này.");
        content = content?.Trim();
        if (string.IsNullOrEmpty(content) || content.Length > ChatMessage.MaxContentLength)
            throw ChatException.Invalid($"Nội dung bắt buộc và tối đa {ChatMessage.MaxContentLength} ký tự.");
        if (content == message.Content) return await PublishAsync(message.Conversation!, message.Id, null, ct);

        message.Content = content;
        message.EditedAt = DateTime.UtcNow;
        if (message.Type == MessageType.File) message.Type = MessageType.Text;
        await Db.SaveChangesAsync(ct);
        return await PublishAsync(message.Conversation!, message.Id, Events.MessageUpdated, ct);
    }

    /// <summary>
    /// Thu hồi với mọi người: người gửi trong 24h; trưởng/phó nhóm thu hồi được tin của thành viên khác bất kỳ lúc nào
    /// (phó nhóm không thu hồi được tin của trưởng nhóm).
    /// </summary>
    public async Task<MessageDto> RecallAsync(long messageId, CancellationToken ct)
    {
        var message = await LoadMessageForMemberAsync(messageId, ct);
        if (message.Type == MessageType.System) throw ChatException.Invalid("Không thể thu hồi tin hệ thống.");
        if (message.RecalledAt != null) return await PublishAsync(message.Conversation!, message.Id, null, ct);

        var conversation = message.Conversation!;
        var me = conversation.Members.First(m => m.UserId == Me.Id);
        if (message.SenderId == Me.Id)
        {
            if (DateTime.UtcNow - message.CreatedAt > ChatMessage.RecallWindow)
                throw ChatException.Invalid("Chỉ thu hồi được tin nhắn trong vòng 24 giờ.");
        }
        else
        {
            var senderRole = conversation.Members.FirstOrDefault(m => m.UserId == message.SenderId)?.Role ?? MemberRole.Member;
            var canModerate = conversation.Type == ConversationType.Group && me.Role >= MemberRole.Deputy && me.Role > senderRole;
            if (!canModerate) throw ChatException.Forbidden("Bạn chỉ thu hồi được tin nhắn của mình.");
        }

        var files = message.Attachments.Select(a => a.StoragePath).ToList();
        message.RecalledAt = DateTime.UtcNow;
        message.Content = string.Empty;
        message.PinnedAt = null;
        message.PinnedById = null;
        Db.MessageAttachments.RemoveRange(message.Attachments);
        Db.MessageReactions.RemoveRange(message.ReactionList);
        await Db.SaveChangesAsync(ct);
        foreach (var file in files) storage.Delete(file);

        var dto = await PublishAsync(conversation, message.Id, Events.MessageUpdated, ct);
        // Tin cuối bị thu hồi: cập nhật dòng xem trước trong danh sách hội thoại
        if (conversation.LastMessageId == message.Id) await NotifyConversationAsync(conversation, ct);
        return dto;
    }

    /// <summary>Xoá phía tôi: chỉ ẩn với người thao tác, đồng bộ sang các tab khác của chính họ.</summary>
    public async Task HideAsync(long messageId, CancellationToken ct)
    {
        var message = await LoadMessageForMemberAsync(messageId, ct);
        if (!await Db.MessageHides.AnyAsync(h => h.MessageId == messageId && h.UserId == Me.Id, ct))
        {
            Db.MessageHides.Add(new MessageHide { MessageId = messageId, UserId = Me.Id });
            await Db.SaveChangesAsync(ct);
        }
        await Notifier.ToUsersAsync([Me.Id], Events.MessageHidden, new { conversation_id = message.ConversationId, message_id = messageId });
    }

    /// <summary>Mỗi người 1 cảm xúc/tin: gửi lại đúng emoji đang có thì gỡ, emoji khác thì thay.</summary>
    public async Task<MessageDto> ReactAsync(long messageId, string? emoji, CancellationToken ct)
    {
        if (emoji == null || !ChatMessage.Reactions.Contains(emoji)) throw ChatException.Invalid("Cảm xúc không hợp lệ.");
        var message = await LoadMessageForMemberAsync(messageId, ct);
        if (message.Type == MessageType.System || message.RecalledAt != null) throw ChatException.Invalid("Không thể thả cảm xúc cho tin này.");

        var existing = message.ReactionList.FirstOrDefault(r => r.UserId == Me.Id);
        if (existing == null) Db.MessageReactions.Add(new MessageReaction { MessageId = messageId, UserId = Me.Id, UserName = Me.Name, Emoji = emoji });
        else if (existing.Emoji == emoji) Db.MessageReactions.Remove(existing);
        else existing.Emoji = emoji;
        await Db.SaveChangesAsync(ct);
        return await PublishAsync(message.Conversation!, messageId, Events.MessageUpdated, ct);
    }

    public async Task<MessageDto> SetPinnedAsync(long messageId, bool pinned, CancellationToken ct)
    {
        var message = await LoadMessageForMemberAsync(messageId, ct);
        if (message.Type == MessageType.System || message.RecalledAt != null) throw ChatException.Invalid("Không thể ghim tin này.");
        var conversation = message.Conversation!;
        if (pinned == (message.PinnedAt != null)) return await PublishAsync(conversation, messageId, null, ct);

        if (pinned)
        {
            var count = await Db.ChatMessages.CountAsync(m => m.ConversationId == conversation.Id && m.PinnedAt != null, ct);
            if (count >= ChatMessage.MaxPinnedPerConversation)
                throw ChatException.Invalid($"Chỉ ghim tối đa {ChatMessage.MaxPinnedPerConversation} tin nhắn trong một hội thoại.");
            message.PinnedAt = DateTime.UtcNow;
            message.PinnedById = Me.Id;
        }
        else
        {
            message.PinnedAt = null;
            message.PinnedById = null;
        }
        var notice = AddSystemMessage(conversation, pinned ? $"{Me.Name} đã ghim một tin nhắn" : $"{Me.Name} đã bỏ ghim một tin nhắn");
        await Db.SaveChangesAsync(ct);
        TouchLastMessage(conversation, notice);
        await Db.SaveChangesAsync(ct);

        var dto = await PublishAsync(conversation, messageId, Events.MessageUpdated, ct);
        await NotifyMessageAsync(conversation, notice, Events.MessageCreated);
        return dto;
    }

    // ---------- Hỗ trợ ----------

    private async Task<ChatMessage> LoadMessageForMemberAsync(long messageId, CancellationToken ct)
    {
        var message = await MessagesWithParts()
            .Include(m => m.Conversation).ThenInclude(c => c!.Members)
            .FirstOrDefaultAsync(m => m.Id == messageId, ct);
        if (message == null || message.Conversation!.Members.All(m => m.UserId != Me.Id))
            throw ChatException.NotFound("Không tìm thấy tin nhắn.");
        return message;
    }

    /// <summary>Đọc lại tin đầy đủ (kèm reply, tệp, cảm xúc), đẩy sự kiện nếu có và trả về payload.</summary>
    private async Task<MessageDto> PublishAsync(Conversation conversation, long messageId, string? eventName, CancellationToken ct)
    {
        var message = await MessagesWithParts().AsNoTracking().FirstAsync(m => m.Id == messageId, ct);
        await UsersForDisplayAsync(ct);
        if (eventName != null) await NotifyMessageAsync(conversation, message, eventName);
        return ToMessageDto(message);
    }
}
