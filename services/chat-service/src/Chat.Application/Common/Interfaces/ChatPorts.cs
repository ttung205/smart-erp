using Chat.Application.Common.Models;
using Chat.Domain.Entities;
using Chat.Domain.Enums;

namespace Chat.Application.Common.Interfaces;

/// <summary>Con trỏ đọc của một người trong một hội thoại, dùng để đếm tin chưa đọc.</summary>
public record ReadCursor(long ConversationId, long? LastReadMessageId, bool Muted);

/// <summary>
/// Lưu trữ hội thoại và tin nhắn (MongoDB). Mọi thay đổi trên thành viên và tin đều là cập nhật nguyên tử theo trường,
/// không ghi đè cả tài liệu, nên hai người cùng thao tác (đọc, thả cảm xúc, vào nhóm) không làm mất dữ liệu của nhau.
/// </summary>
public interface IChatRepository
{
    // ---- Hội thoại ----
    Task<Conversation?> GetConversationAsync(long id, CancellationToken ct);
    Task<List<Conversation>> ListConversationsOfUserAsync(long userId, CancellationToken ct);
    Task<List<Conversation>> GetConversationsOfUserAsync(long userId, IReadOnlyCollection<long> ids, CancellationToken ct);
    Task<Conversation?> FindDirectAsync(string directKey, CancellationToken ct);
    Task<Conversation?> FindWorkThreadAsync(string subjectType, long subjectId, CancellationToken ct);

    /// <summary>Gán Id và lưu. Trả false nếu DirectKey đã tồn tại (hai request tạo hội thoại 1-1 cùng lúc).</summary>
    Task<bool> TryInsertConversationAsync(Conversation conversation, CancellationToken ct);

    /// <summary>Xoá hội thoại cùng toàn bộ tin nhắn và bản ghi ẩn tin.</summary>
    Task DeleteConversationAsync(long conversationId, CancellationToken ct);
    Task UpdateGroupInfoAsync(Conversation conversation, CancellationToken ct);
    Task AddMembersAsync(long conversationId, IReadOnlyCollection<ConversationMember> members, CancellationToken ct);
    Task RemoveMemberAsync(long conversationId, long userId, CancellationToken ct);
    Task SetMemberRoleAsync(long conversationId, long userId, MemberRole role, CancellationToken ct);
    Task SetMemberPinnedAsync(long conversationId, long userId, DateTime? pinnedAt, CancellationToken ct);
    Task SetMemberMutedAsync(long conversationId, long userId, bool muted, CancellationToken ct);

    /// <summary>Con trỏ chỉ tiến. Trả true nếu có thay đổi.</summary>
    Task<bool> AdvanceReadAsync(long conversationId, long userId, long messageId, DateTime at, CancellationToken ct);
    Task MarkAllReadAsync(long userId, DateTime at, CancellationToken ct);

    /// <summary>Cập nhật tin cuối của hội thoại (chỉ tiến) và, nếu có, đánh dấu người gửi đã đọc tới tin đó.</summary>
    Task TouchLastMessageAsync(long conversationId, long messageId, DateTime at, long? readByUserId, CancellationToken ct);
    Task<List<ReadCursor>> GetReadCursorsAsync(long userId, CancellationToken ct);
    Task<List<long>> GetDirectPartnerIdsAsync(long userId, CancellationToken ct);

    // ---- Tin nhắn ----
    /// <summary>Gán Id (kể cả Id tệp đính kèm) rồi lưu.</summary>
    Task InsertMessagesAsync(IReadOnlyCollection<ChatMessage> messages, CancellationToken ct);
    Task<ChatMessage?> GetMessageAsync(long id, CancellationToken ct);
    Task<List<ChatMessage>> GetMessagesAsync(IReadOnlyCollection<long> ids, CancellationToken ct);
    Task<bool> MessageExistsAsync(long conversationId, long messageId, CancellationToken ct);

    /// <summary>Đường dẫn lưu của mọi tệp đính kèm trong hội thoại (để xoá tệp khi giải tán nhóm).</summary>
    Task<List<string>> GetAttachmentPathsAsync(long conversationId, CancellationToken ct);

    /// <summary>Tin của hội thoại mà người xem chưa ẩn. ascending=true lấy tin mới hơn afterId, ngược lại lấy tin cũ hơn beforeId (mới nhất trước).</summary>
    Task<List<ChatMessage>> ListMessagesAsync(long conversationId, long viewerId, long? beforeId, long? afterId, int take, bool ascending, CancellationToken ct);
    Task<List<ChatMessage>> ListPinnedAsync(long conversationId, CancellationToken ct);
    Task<int> CountPinnedAsync(long conversationId, CancellationToken ct);
    Task<List<ChatMessage>> SearchMessagesAsync(long conversationId, long viewerId, string keyword, int take, CancellationToken ct);
    Task<Dictionary<long, int>> UnreadCountsAsync(long userId, IReadOnlyCollection<ReadCursor> cursors, CancellationToken ct);

    Task EditMessageAsync(long messageId, string content, DateTime editedAt, MessageType type, CancellationToken ct);

    /// <summary>Thu hồi: xoá nội dung, tệp, cảm xúc và bỏ ghim.</summary>
    Task RecallMessageAsync(long messageId, DateTime recalledAt, CancellationToken ct);
    Task SetMessagePinnedAsync(long messageId, DateTime? pinnedAt, long? pinnedById, CancellationToken ct);

    /// <summary>emoji = null là gỡ cảm xúc của người này.</summary>
    Task SetReactionAsync(long messageId, long userId, string userName, string? emoji, CancellationToken ct);
    Task HideMessageAsync(long messageId, long conversationId, long userId, CancellationToken ct);
    Task<bool> IsHiddenAsync(long messageId, long userId, CancellationToken ct);

    /// <summary>Tệp đính kèm của tin chưa thu hồi, chỉ khi người hỏi là thành viên hội thoại.</summary>
    Task<MessageAttachment?> FindAttachmentForMemberAsync(long attachmentId, long userId, CancellationToken ct);

    // ---- Tên gợi nhớ ----
    Task<Dictionary<long, string>> GetAliasesAsync(long ownerId, CancellationToken ct);
    Task SetAliasAsync(long ownerId, long targetId, string? alias, CancellationToken ct);
}

/// <summary>Người dùng của request hiện tại (lấy từ JWT).</summary>
public interface ICurrentUser
{
    long Id { get; }
    string Name { get; }
}

/// <summary>Danh bạ nhân viên từ identity-service.</summary>
public interface IUserDirectory
{
    /// <summary>Toàn bộ nhân viên. Ném ChatException 503 nếu identity-service không phản hồi.</summary>
    Task<IReadOnlyDictionary<long, DirectoryUser>> GetUsersAsync(CancellationToken ct);
}

public interface IFileStorage
{
    Task<string> SaveAsync(Stream content, string extension, CancellationToken ct);
    Task<string> CopyAsync(string storagePath, CancellationToken ct);
    Stream OpenRead(string storagePath);
    void Delete(string storagePath);
}

/// <summary>Đẩy sự kiện realtime xuống client; chỉ gửi theo user, không theo hội thoại.</summary>
public interface IChatNotifier
{
    Task ToUsersAsync(IEnumerable<long> userIds, string eventName, object payload);
    Task ToAllAsync(string eventName, object payload);
}

/// <summary>Theo dõi kết nối SignalR để biết ai đang online.</summary>
public interface IPresenceTracker
{
    bool IsOnline(long userId);
    IReadOnlyCollection<long> OnlineUserIds();
}
