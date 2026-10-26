using System.Text.RegularExpressions;
using Chat.Application.Common.Interfaces;
using Chat.Domain.Entities;
using Chat.Domain.Enums;
using MongoDB.Bson;
using MongoDB.Driver;
using MongoDB.Driver.Linq;

namespace Chat.Infrastructure.Persistence;

public sealed class MongoChatRepository(MongoChatContext context) : IChatRepository
{
    private static readonly FilterDefinitionBuilder<Conversation> C = Builders<Conversation>.Filter;
    private static readonly UpdateDefinitionBuilder<Conversation> CU = Builders<Conversation>.Update;
    private static readonly FilterDefinitionBuilder<ChatMessage> M = Builders<ChatMessage>.Filter;
    private static readonly UpdateDefinitionBuilder<ChatMessage> MU = Builders<ChatMessage>.Update;

    private static FilterDefinition<Conversation> HasMember(long userId) =>
        C.ElemMatch(c => c.Members, m => m.UserId == userId);

    private static FilterDefinition<Conversation> ById(long id) => C.Eq(c => c.Id, id);

    // ---------- Hội thoại ----------

    public async Task<Conversation?> GetConversationAsync(long id, CancellationToken ct) =>
        await context.Conversations.Find(ById(id)).FirstOrDefaultAsync(ct);

    public Task<List<Conversation>> ListConversationsOfUserAsync(long userId, CancellationToken ct) =>
        context.Conversations.Find(HasMember(userId)).ToListAsync(ct);

    public Task<List<Conversation>> GetConversationsOfUserAsync(long userId, IReadOnlyCollection<long> ids, CancellationToken ct) =>
        context.Conversations.Find(C.And(C.In(c => c.Id, ids), HasMember(userId))).ToListAsync(ct);

    public async Task<Conversation?> FindDirectAsync(string directKey, CancellationToken ct) =>
        await context.Conversations.Find(C.Eq(c => c.DirectKey, directKey)).FirstOrDefaultAsync(ct);

    public async Task<Conversation?> FindWorkThreadAsync(string subjectType, long subjectId, CancellationToken ct) =>
        await context.Conversations.Find(C.And(
            C.Eq(c => c.Type, ConversationType.Work), C.Eq(c => c.SubjectType, subjectType), C.Eq(c => c.SubjectId, subjectId)))
            .FirstOrDefaultAsync(ct);

    public async Task<bool> TryInsertConversationAsync(Conversation conversation, CancellationToken ct)
    {
        conversation.Id = await context.NextIdsAsync("conversations", 1, ct);
        try
        {
            await context.Conversations.InsertOneAsync(conversation, cancellationToken: ct);
            return true;
        }
        catch (MongoWriteException ex) when (ex.WriteError.Category == ServerErrorCategory.DuplicateKey)
        {
            return false;
        }
    }

    public async Task DeleteConversationAsync(long conversationId, CancellationToken ct)
    {
        await context.Messages.DeleteManyAsync(M.Eq(m => m.ConversationId, conversationId), ct);
        await context.Hides.DeleteManyAsync(Builders<MessageHide>.Filter.Eq(h => h.ConversationId, conversationId), ct);
        await context.Conversations.DeleteOneAsync(ById(conversationId), ct);
    }

    public Task UpdateGroupInfoAsync(Conversation conversation, CancellationToken ct) =>
        context.Conversations.UpdateOneAsync(ById(conversation.Id), CU
            .Set(c => c.Title, conversation.Title)
            .Set(c => c.AvatarPath, conversation.AvatarPath)
            .Set(c => c.AvatarContentType, conversation.AvatarContentType)
            .Set(c => c.AvatarVersion, conversation.AvatarVersion)
            .Set(c => c.UpdatedAt, DateTime.UtcNow), cancellationToken: ct);

    public Task AddMembersAsync(long conversationId, IReadOnlyCollection<ConversationMember> members, CancellationToken ct) =>
        context.Conversations.UpdateOneAsync(
            // Không thêm người đã có trong nhóm (hai request vào nhóm cùng lúc)
            C.And(ById(conversationId), C.Nin("Members.UserId", members.Select(m => m.UserId))),
            CU.PushEach(c => c.Members, members).Set(c => c.UpdatedAt, DateTime.UtcNow), cancellationToken: ct);

    public Task RemoveMemberAsync(long conversationId, long userId, CancellationToken ct) =>
        context.Conversations.UpdateOneAsync(ById(conversationId),
            CU.PullFilter(c => c.Members, m => m.UserId == userId).Set(c => c.UpdatedAt, DateTime.UtcNow), cancellationToken: ct);

    public Task SetMemberRoleAsync(long conversationId, long userId, MemberRole role, CancellationToken ct) =>
        context.Conversations.UpdateOneAsync(C.And(ById(conversationId), HasMember(userId)),
            CU.Set(c => c.Members.FirstMatchingElement().Role, role), cancellationToken: ct);

    public Task SetMemberPinnedAsync(long conversationId, long userId, DateTime? pinnedAt, CancellationToken ct) =>
        context.Conversations.UpdateOneAsync(C.And(ById(conversationId), HasMember(userId)),
            CU.Set(c => c.Members.FirstMatchingElement().PinnedAt, pinnedAt), cancellationToken: ct);

    public Task SetMemberMutedAsync(long conversationId, long userId, bool muted, CancellationToken ct) =>
        context.Conversations.UpdateOneAsync(C.And(ById(conversationId), HasMember(userId)),
            CU.Set(c => c.Members.FirstMatchingElement().Muted, muted), cancellationToken: ct);

    public async Task<bool> AdvanceReadAsync(long conversationId, long userId, long messageId, DateTime at, CancellationToken ct)
    {
        var filter = C.And(ById(conversationId),
            C.ElemMatch(c => c.Members, m => m.UserId == userId && (m.LastReadMessageId == null || m.LastReadMessageId < messageId)));
        var result = await context.Conversations.UpdateOneAsync(filter, CU
            .Set(c => c.Members.FirstMatchingElement().LastReadMessageId, messageId)
            .Set(c => c.Members.FirstMatchingElement().LastReadAt, at), cancellationToken: ct);
        return result.ModifiedCount > 0;
    }

    public async Task MarkAllReadAsync(long userId, DateTime at, CancellationToken ct)
    {
        var conversations = await context.Conversations
            .Find(C.And(HasMember(userId), C.Ne(c => c.LastMessageId, null))).ToListAsync(ct);
        foreach (var conversation in conversations)
            await AdvanceReadAsync(conversation.Id, userId, conversation.LastMessageId!.Value, at, ct);
    }

    public async Task TouchLastMessageAsync(long conversationId, long messageId, DateTime at, long? readByUserId, CancellationToken ct)
    {
        // Chỉ tiến: hai tin gửi gần nhau mà tin cũ ghi sau thì không kéo tin cuối lùi lại
        await context.Conversations.UpdateOneAsync(
            C.And(ById(conversationId), C.Or(C.Eq(c => c.LastMessageId, null), C.Lt(c => c.LastMessageId, messageId))),
            CU.Set(c => c.LastMessageId, messageId).Set(c => c.LastMessageAt, at).Set(c => c.UpdatedAt, at), cancellationToken: ct);
        if (readByUserId is { } userId)
            await AdvanceReadAsync(conversationId, userId, messageId, at, ct);
    }

    public async Task<List<ReadCursor>> GetReadCursorsAsync(long userId, CancellationToken ct)
    {
        var conversations = await context.Conversations.Find(HasMember(userId))
            .Project(c => new { c.Id, c.Members }).ToListAsync(ct);
        return conversations.Select(c =>
        {
            var member = c.Members.First(m => m.UserId == userId);
            return new ReadCursor(c.Id, member.LastReadMessageId, member.Muted);
        }).ToList();
    }

    public async Task<List<long>> GetDirectPartnerIdsAsync(long userId, CancellationToken ct)
    {
        var members = await context.Conversations.Find(C.And(C.Eq(c => c.Type, ConversationType.Direct), HasMember(userId)))
            .Project(c => c.Members).ToListAsync(ct);
        return members.SelectMany(list => list).Where(m => m.UserId != userId).Select(m => m.UserId).Distinct().ToList();
    }

    // ---------- Tin nhắn ----------

    public async Task InsertMessagesAsync(IReadOnlyCollection<ChatMessage> messages, CancellationToken ct)
    {
        if (messages.Count == 0) return;
        var nextMessageId = await context.NextIdsAsync("messages", messages.Count, ct);
        var attachmentCount = messages.Sum(m => m.Attachments.Count);
        var nextAttachmentId = attachmentCount > 0 ? await context.NextIdsAsync("attachments", attachmentCount, ct) : 0;
        foreach (var message in messages)
        {
            message.Id = nextMessageId++;
            foreach (var attachment in message.Attachments) attachment.Id = nextAttachmentId++;
        }
        await context.Messages.InsertManyAsync(messages, cancellationToken: ct);
    }

    public async Task<ChatMessage?> GetMessageAsync(long id, CancellationToken ct) =>
        await context.Messages.Find(M.Eq(m => m.Id, id)).FirstOrDefaultAsync(ct);

    public Task<List<ChatMessage>> GetMessagesAsync(IReadOnlyCollection<long> ids, CancellationToken ct) =>
        ids.Count == 0 ? Task.FromResult(new List<ChatMessage>()) : context.Messages.Find(M.In(m => m.Id, ids)).ToListAsync(ct);

    public async Task<bool> MessageExistsAsync(long conversationId, long messageId, CancellationToken ct) =>
        await context.Messages.CountDocumentsAsync(M.And(M.Eq(m => m.Id, messageId), M.Eq(m => m.ConversationId, conversationId)),
            new CountOptions { Limit = 1 }, ct) > 0;

    public async Task<List<string>> GetAttachmentPathsAsync(long conversationId, CancellationToken ct)
    {
        var attachments = await context.Messages.Find(M.Eq(m => m.ConversationId, conversationId))
            .Project(m => m.Attachments).ToListAsync(ct);
        return attachments.SelectMany(list => list).Select(a => a.StoragePath).ToList();
    }

    /// <summary>Id các tin người này đã "xoá phía tôi" trong một hội thoại (hoặc mọi hội thoại nếu để null).</summary>
    private async Task<List<long>> HiddenIdsAsync(long userId, long? conversationId, CancellationToken ct)
    {
        var filter = conversationId is { } id
            ? Builders<MessageHide>.Filter.And(Builders<MessageHide>.Filter.Eq(h => h.UserId, userId), Builders<MessageHide>.Filter.Eq(h => h.ConversationId, id))
            : Builders<MessageHide>.Filter.Eq(h => h.UserId, userId);
        return await context.Hides.Find(filter).Project(h => h.MessageId).ToListAsync(ct);
    }

    public async Task<List<ChatMessage>> ListMessagesAsync(
        long conversationId, long viewerId, long? beforeId, long? afterId, int take, bool ascending, CancellationToken ct)
    {
        var filter = M.Eq(m => m.ConversationId, conversationId);
        var hidden = await HiddenIdsAsync(viewerId, conversationId, ct);
        if (hidden.Count > 0) filter &= M.Nin(m => m.Id, hidden);
        if (afterId is { } after) filter &= M.Gt(m => m.Id, after);
        if (beforeId is { } before) filter &= M.Lt(m => m.Id, before);
        var query = context.Messages.Find(filter);
        query = ascending ? query.SortBy(m => m.Id) : query.SortByDescending(m => m.Id);
        return await query.Limit(take).ToListAsync(ct);
    }

    public Task<List<ChatMessage>> ListPinnedAsync(long conversationId, CancellationToken ct) =>
        context.Messages.Find(M.And(M.Eq(m => m.ConversationId, conversationId), M.Ne(m => m.PinnedAt, null)))
            .SortByDescending(m => m.PinnedAt).ThenByDescending(m => m.Id).ToListAsync(ct);

    public async Task<int> CountPinnedAsync(long conversationId, CancellationToken ct) =>
        (int)await context.Messages.CountDocumentsAsync(M.And(M.Eq(m => m.ConversationId, conversationId), M.Ne(m => m.PinnedAt, null)), cancellationToken: ct);

    public async Task<List<ChatMessage>> SearchMessagesAsync(long conversationId, long viewerId, string keyword, int take, CancellationToken ct)
    {
        var filter = M.And(
            M.Eq(m => m.ConversationId, conversationId),
            M.Ne(m => m.Type, MessageType.System),
            M.Eq(m => m.RecalledAt, null),
            M.Regex(m => m.Content, new BsonRegularExpression(Regex.Escape(keyword), "i")));
        var hidden = await HiddenIdsAsync(viewerId, conversationId, ct);
        if (hidden.Count > 0) filter &= M.Nin(m => m.Id, hidden);
        return await context.Messages.Find(filter).SortByDescending(m => m.Id).Limit(take).ToListAsync(ct);
    }

    /// <summary>Tin của người khác, sau con trỏ đọc, chưa thu hồi, chưa bị ẩn.</summary>
    public async Task<Dictionary<long, int>> UnreadCountsAsync(long userId, IReadOnlyCollection<ReadCursor> cursors, CancellationToken ct)
    {
        if (cursors.Count == 0) return [];
        var perConversation = cursors.Select(c => c.LastReadMessageId is { } read
            ? M.And(M.Eq(m => m.ConversationId, c.ConversationId), M.Gt(m => m.Id, read))
            : M.Eq(m => m.ConversationId, c.ConversationId));
        var filter = M.And(
            M.Or(perConversation),
            M.Ne(m => m.SenderId, null),
            M.Ne(m => m.SenderId, userId),
            M.Eq(m => m.RecalledAt, null));
        var hidden = await HiddenIdsAsync(userId, null, ct);
        if (hidden.Count > 0) filter &= M.Nin(m => m.Id, hidden);

        var groups = await context.Messages.Aggregate().Match(filter)
            .Group(m => m.ConversationId, g => new { g.Key, Count = g.Count() }).ToListAsync(ct);
        return groups.ToDictionary(g => g.Key, g => g.Count);
    }

    public Task EditMessageAsync(long messageId, string content, DateTime editedAt, MessageType type, CancellationToken ct) =>
        context.Messages.UpdateOneAsync(M.Eq(m => m.Id, messageId),
            MU.Set(m => m.Content, content).Set(m => m.EditedAt, editedAt).Set(m => m.Type, type), cancellationToken: ct);

    public Task RecallMessageAsync(long messageId, DateTime recalledAt, CancellationToken ct) =>
        context.Messages.UpdateOneAsync(M.Eq(m => m.Id, messageId), MU
            .Set(m => m.RecalledAt, recalledAt)
            .Set(m => m.Content, string.Empty)
            .Set(m => m.PinnedAt, null)
            .Set(m => m.PinnedById, null)
            .Set(m => m.Attachments, new List<MessageAttachment>())
            .Set(m => m.ReactionList, new List<MessageReaction>()), cancellationToken: ct);

    public Task SetMessagePinnedAsync(long messageId, DateTime? pinnedAt, long? pinnedById, CancellationToken ct) =>
        context.Messages.UpdateOneAsync(M.Eq(m => m.Id, messageId),
            MU.Set(m => m.PinnedAt, pinnedAt).Set(m => m.PinnedById, pinnedById), cancellationToken: ct);

    public async Task SetReactionAsync(long messageId, long userId, string userName, string? emoji, CancellationToken ct)
    {
        await context.Messages.UpdateOneAsync(M.Eq(m => m.Id, messageId),
            MU.PullFilter(m => m.ReactionList, r => r.UserId == userId), cancellationToken: ct);
        if (emoji == null) return;
        // Điều kiện "chưa có cảm xúc của người này" chặn trường hợp một người gửi hai request cùng lúc
        await context.Messages.UpdateOneAsync(
            M.And(M.Eq(m => m.Id, messageId), M.Not(M.ElemMatch(m => m.ReactionList, r => r.UserId == userId))),
            MU.Push(m => m.ReactionList, new MessageReaction { UserId = userId, UserName = userName, Emoji = emoji, CreatedAt = DateTime.UtcNow }),
            cancellationToken: ct);
    }

    public Task HideMessageAsync(long messageId, long conversationId, long userId, CancellationToken ct) =>
        context.Hides.ReplaceOneAsync(h => h.Id == $"{messageId}:{userId}",
            new MessageHide { Id = $"{messageId}:{userId}", MessageId = messageId, ConversationId = conversationId, UserId = userId },
            new ReplaceOptions { IsUpsert = true }, ct);

    public async Task<bool> IsHiddenAsync(long messageId, long userId, CancellationToken ct) =>
        await context.Hides.CountDocumentsAsync(h => h.Id == $"{messageId}:{userId}", new CountOptions { Limit = 1 }, ct) > 0;

    public async Task<MessageAttachment?> FindAttachmentForMemberAsync(long attachmentId, long userId, CancellationToken ct)
    {
        var message = await context.Messages
            .Find(M.And(M.Eq(m => m.RecalledAt, null), M.ElemMatch(m => m.Attachments, a => a.Id == attachmentId)))
            .FirstOrDefaultAsync(ct);
        if (message == null) return null;
        var isMember = await context.Conversations.CountDocumentsAsync(
            C.And(ById(message.ConversationId), HasMember(userId)), new CountOptions { Limit = 1 }, ct) > 0;
        return isMember ? message.Attachments.First(a => a.Id == attachmentId) : null;
    }

    // ---------- Tên gợi nhớ ----------

    public async Task<Dictionary<long, string>> GetAliasesAsync(long ownerId, CancellationToken ct)
    {
        var aliases = await context.Aliases.Find(a => a.OwnerId == ownerId).ToListAsync(ct);
        return aliases.ToDictionary(a => a.TargetId, a => a.Alias);
    }

    public async Task SetAliasAsync(long ownerId, long targetId, string? alias, CancellationToken ct)
    {
        var id = $"{ownerId}:{targetId}";
        if (string.IsNullOrEmpty(alias))
        {
            await context.Aliases.DeleteOneAsync(a => a.Id == id, ct);
            return;
        }
        await context.Aliases.ReplaceOneAsync(a => a.Id == id,
            new ContactAlias { Id = id, OwnerId = ownerId, TargetId = targetId, Alias = alias },
            new ReplaceOptions { IsUpsert = true }, ct);
    }
}
