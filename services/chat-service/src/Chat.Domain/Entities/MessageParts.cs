namespace Chat.Domain.Entities;

public class MessageAttachment
{
    public long Id { get; set; }
    public long MessageId { get; set; }
    public string FileName { get; set; } = string.Empty;
    public string ContentType { get; set; } = "application/octet-stream";
    public long Size { get; set; }
    public string StoragePath { get; set; } = string.Empty;

    public ChatMessage? Message { get; set; }
}

public class MessageReaction
{
    public long Id { get; set; }
    public long MessageId { get; set; }
    public long UserId { get; set; }
    public string UserName { get; set; } = string.Empty;
    public string Emoji { get; set; } = string.Empty;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
}

/// <summary>"Xoá phía tôi": tin vẫn còn với người khác, chỉ ẩn với người này.</summary>
public class MessageHide
{
    public long Id { get; set; }
    public long MessageId { get; set; }
    public long UserId { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
}

/// <summary>Tên gợi nhớ người này đặt cho người kia, chỉ người đặt thấy.</summary>
public class ContactAlias
{
    public long Id { get; set; }
    public long OwnerId { get; set; }
    public long TargetId { get; set; }
    public string Alias { get; set; } = string.Empty;
}
