using Chat.Domain.Common;

namespace Chat.Domain.Entities;

public class MessageReceipt : BaseEntity
{
    public Guid MessageId { get; set; }
    public ChatMessage Message { get; set; } = null!;

    /// <summary>
    /// ID người dùng đã đọc
    /// </summary>
    public int UserId { get; set; }

    /// <summary>
    /// Thời điểm xem / đọc tin nhắn
    /// </summary>
    public DateTime ReadAt { get; set; } = DateTime.UtcNow;
}
