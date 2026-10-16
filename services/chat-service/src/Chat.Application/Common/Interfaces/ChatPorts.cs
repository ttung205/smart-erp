using Chat.Application.Common.Models;
using Chat.Domain.Entities;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;

namespace Chat.Application.Common.Interfaces;

public interface IChatDbContext
{
    DbSet<Conversation> Conversations { get; }
    DbSet<ConversationMember> ConversationMembers { get; }
    DbSet<ChatMessage> ChatMessages { get; }
    DbSet<MessageAttachment> MessageAttachments { get; }
    DbSet<MessageReaction> MessageReactions { get; }
    DbSet<MessageHide> MessageHides { get; }
    DbSet<ContactAlias> ContactAliases { get; }

    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
    Task<IDbContextTransaction> BeginTransactionAsync(CancellationToken cancellationToken = default);
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
