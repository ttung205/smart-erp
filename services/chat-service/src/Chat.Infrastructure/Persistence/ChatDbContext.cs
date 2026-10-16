using Chat.Application.Common.Interfaces;
using Chat.Domain.Entities;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Microsoft.EntityFrameworkCore.Storage.ValueConversion;

namespace Chat.Infrastructure.Persistence;

public class ChatDbContext(DbContextOptions<ChatDbContext> options) : DbContext(options), IChatDbContext
{
    public DbSet<Conversation> Conversations => Set<Conversation>();
    public DbSet<ConversationMember> ConversationMembers => Set<ConversationMember>();
    public DbSet<ChatMessage> ChatMessages => Set<ChatMessage>();
    public DbSet<MessageAttachment> MessageAttachments => Set<MessageAttachment>();
    public DbSet<MessageReaction> MessageReactions => Set<MessageReaction>();
    public DbSet<MessageHide> MessageHides => Set<MessageHide>();
    public DbSet<ContactAlias> ContactAliases => Set<ContactAlias>();

    public Task<IDbContextTransaction> BeginTransactionAsync(CancellationToken cancellationToken = default) =>
        Database.BeginTransactionAsync(cancellationToken);

    /// <summary>SQLite không lưu Kind: mọi thời điểm đều ghi UTC nên đọc ra gắn lại Kind=Utc để JSON có hậu tố Z.</summary>
    protected override void ConfigureConventions(ModelConfigurationBuilder configurationBuilder)
    {
        configurationBuilder.Properties<DateTime>().HaveConversion<UtcDateTimeConverter>();
        configurationBuilder.Properties<DateTime?>().HaveConversion<NullableUtcDateTimeConverter>();
    }

    private sealed class UtcDateTimeConverter() : ValueConverter<DateTime, DateTime>(
        v => v.ToUniversalTime(), v => DateTime.SpecifyKind(v, DateTimeKind.Utc));

    private sealed class NullableUtcDateTimeConverter() : ValueConverter<DateTime?, DateTime?>(
        v => v.HasValue ? v.Value.ToUniversalTime() : v, v => v.HasValue ? DateTime.SpecifyKind(v.Value, DateTimeKind.Utc) : v);

    protected override void OnModelCreating(ModelBuilder b)
    {
        b.Entity<Conversation>(e =>
        {
            e.ToTable("conversations");
            e.Property(x => x.Type).HasConversion<string>().HasMaxLength(20);
            e.Property(x => x.Title).HasMaxLength(120);
            e.Property(x => x.DirectKey).HasMaxLength(50);
            e.Property(x => x.AvatarPath).HasMaxLength(300);
            e.Property(x => x.AvatarContentType).HasMaxLength(100);
            // Mỗi cặp người dùng chỉ có một hội thoại 1-1 (NULL với nhóm nên không bị trùng)
            e.HasIndex(x => x.DirectKey).IsUnique();
            e.HasIndex(x => x.LastMessageAt);
            e.HasMany(x => x.Members).WithOne(x => x.Conversation).HasForeignKey(x => x.ConversationId).OnDelete(DeleteBehavior.Cascade);
            e.HasMany(x => x.Messages).WithOne(x => x.Conversation).HasForeignKey(x => x.ConversationId).OnDelete(DeleteBehavior.Cascade);
        });

        b.Entity<ConversationMember>(e =>
        {
            e.ToTable("conversation_members");
            e.Property(x => x.Role).HasConversion<string>().HasMaxLength(20);
            e.Property(x => x.UserName).HasMaxLength(200);
            e.HasIndex(x => new { x.ConversationId, x.UserId }).IsUnique();
            e.HasIndex(x => x.UserId);
        });

        b.Entity<ChatMessage>(e =>
        {
            e.ToTable("chat_messages");
            e.Property(x => x.Type).HasConversion<string>().HasMaxLength(20);
            e.Property(x => x.SenderName).HasMaxLength(200);
            e.Property(x => x.Content).HasMaxLength(ChatMessage.MaxContentLength);
            e.Property(x => x.Mentions).HasMaxLength(2000);
            e.Property(x => x.ForwardedFromName).HasMaxLength(200);
            e.Ignore(x => x.MentionIds);
            e.HasIndex(x => new { x.ConversationId, x.Id });
            e.HasIndex(x => new { x.ConversationId, x.PinnedAt });
            e.HasOne(x => x.ReplyTo).WithMany().HasForeignKey(x => x.ReplyToId).OnDelete(DeleteBehavior.SetNull);
            e.HasMany(x => x.Attachments).WithOne(x => x.Message).HasForeignKey(x => x.MessageId).OnDelete(DeleteBehavior.Cascade);
            e.HasMany(x => x.ReactionList).WithOne().HasForeignKey(x => x.MessageId).OnDelete(DeleteBehavior.Cascade);
        });

        b.Entity<MessageAttachment>(e =>
        {
            e.ToTable("message_attachments");
            e.Property(x => x.FileName).HasMaxLength(255);
            e.Property(x => x.ContentType).HasMaxLength(150);
            e.Property(x => x.StoragePath).HasMaxLength(300);
        });

        b.Entity<MessageReaction>(e =>
        {
            e.ToTable("message_reactions");
            e.Property(x => x.Emoji).HasMaxLength(16);
            e.Property(x => x.UserName).HasMaxLength(200);
            e.HasIndex(x => new { x.MessageId, x.UserId }).IsUnique();
        });

        b.Entity<MessageHide>(e =>
        {
            e.ToTable("message_hides");
            e.HasIndex(x => new { x.MessageId, x.UserId }).IsUnique();
            e.HasIndex(x => x.UserId);
        });

        b.Entity<ContactAlias>(e =>
        {
            e.ToTable("contact_aliases");
            e.Property(x => x.Alias).HasMaxLength(60);
            e.HasIndex(x => new { x.OwnerId, x.TargetId }).IsUnique();
        });
    }
}
