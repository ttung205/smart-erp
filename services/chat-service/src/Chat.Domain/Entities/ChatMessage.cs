using Chat.Domain.Enums;

namespace Chat.Domain.Entities;

public class ChatMessage
{
    /// <summary>6 cảm xúc cố định, mỗi người tối đa 1 cảm xúc trên một tin.</summary>
    public static readonly string[] Reactions = ["❤️", "👍", "😄", "😮", "😢", "😡"];

    /// <summary>Người gửi chỉ thu hồi được tin của mình trong khoảng này.</summary>
    public static readonly TimeSpan RecallWindow = TimeSpan.FromHours(24);

    public const int MaxContentLength = 16000;
    public const int MaxPinnedPerConversation = 5;

    public long Id { get; set; }
    public long ConversationId { get; set; }

    /// <summary>Null = tin hệ thống.</summary>
    public long? SenderId { get; set; }
    public string SenderName { get; set; } = string.Empty;

    public MessageType Type { get; set; } = MessageType.Text;
    public string Content { get; set; } = string.Empty;

    public long? ReplyToId { get; set; }

    /// <summary>Danh sách user id được nhắc tên, lưu dạng "1,5,9".</summary>
    public string? Mentions { get; set; }
    public bool MentionAll { get; set; }

    /// <summary>Tin được chuyển tiếp: tên người gửi gốc.</summary>
    public string? ForwardedFromName { get; set; }

    public DateTime? EditedAt { get; set; }
    public DateTime? PinnedAt { get; set; }
    public long? PinnedById { get; set; }

    /// <summary>Thu hồi với mọi người: giữ dòng tin, xoá nội dung và tệp, client hiện "Tin nhắn đã được thu hồi".</summary>
    public DateTime? RecalledAt { get; set; }

    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public List<MessageAttachment> Attachments { get; set; } = [];
    public List<MessageReaction> ReactionList { get; set; } = [];

    public IReadOnlyList<long> MentionIds =>
        string.IsNullOrEmpty(Mentions) ? [] : Mentions.Split(',').Select(long.Parse).ToList();
}
