using Chat.Domain.Enums;

namespace Chat.Domain.Entities;

public class Conversation
{
    public long Id { get; set; }
    public ConversationType Type { get; set; }

    /// <summary>Tên nhóm. Hội thoại 1-1 để null, client hiển thị tên người kia.</summary>
    public string? Title { get; set; }

    /// <summary>Khoá chống tạo trùng hội thoại 1-1: "{idNhỏ}_{idLớn}". Null với nhóm.</summary>
    public string? DirectKey { get; set; }

    public string? AvatarPath { get; set; }
    public string? AvatarContentType { get; set; }
    public long AvatarVersion { get; set; }

    public long CreatedBy { get; set; }
    public long? LastMessageId { get; set; }
    public DateTime? LastMessageAt { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    public List<ConversationMember> Members { get; set; } = [];
    public List<ChatMessage> Messages { get; set; } = [];
}
