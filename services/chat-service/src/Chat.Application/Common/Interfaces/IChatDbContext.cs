using Chat.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace Chat.Application.Common.Interfaces;

public interface IChatDbContext
{
    DbSet<Conversation> Conversations { get; }
    DbSet<ConversationMember> ConversationMembers { get; }
    DbSet<ChatMessage> ChatMessages { get; }
    DbSet<MessageReceipt> MessageReceipts { get; }

    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
}
