using Chat.Domain.Common;
using Chat.Domain.Enums;

namespace Chat.Domain.Entities;

public class ChatMessage : BaseEntity
{
    public Guid ConversationId { get; set; }
    public Conversation Conversation { get; set; } = null!;

    /// <summary>
    /// ID người gửi (UserId từ identity-service, hoặc 0 nếu là SystemBot)
    /// </summary>
    public int SenderId { get; set; }

    /// <summary>
    /// Tên người gửi snapshot (hoặc "ERP System Bot")
    /// </summary>
    public string SenderName { get; set; } = string.Empty;

    /// <summary>
    /// Avatar người gửi snapshot
    /// </summary>
    public string? SenderAvatar { get; set; }

    /// <summary>
    /// Loại tin nhắn: Text, SystemBot, Attachment
    /// </summary>
    public MessageType MessageType { get; set; } = MessageType.Text;

    /// <summary>
    /// Nội dung tin nhắn
    /// </summary>
    public string Content { get; set; } = string.Empty;

    /// <summary>
    /// Đường dẫn file đính kèm nếu có
    /// </summary>
    public string? AttachmentUrl { get; set; }

    /// <summary>
    /// Dữ liệu metadata bổ sung (ví dụ: json payload từ rabbitmq event: sku, po_code, v.v.)
    /// </summary>
    public string? MetadataJson { get; set; }

    /// <summary>
    /// Danh sách xác nhận đọc tin nhắn
    /// </summary>
    public ICollection<MessageReceipt> Receipts { get; set; } = new List<MessageReceipt>();
}
