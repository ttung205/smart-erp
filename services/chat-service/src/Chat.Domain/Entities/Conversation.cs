using Chat.Domain.Common;
using Chat.Domain.Enums;

namespace Chat.Domain.Entities;

public class Conversation : BaseEntity
{
    public ConversationType Type { get; set; } = ConversationType.Channel;
    
    /// <summary>
    /// Unique code, ví dụ: "dept:general", "dept:inventory", hoặc "dm:1_2" cho Direct Message
    /// </summary>
    public string Code { get; set; } = string.Empty;
    
    /// <summary>
    /// Tên hiển thị của kênh / cuộc hội thoại, ví dụ: "#chung", "#kho-van"
    /// </summary>
    public string Name { get; set; } = string.Empty;
    
    /// <summary>
    /// Mã phòng ban nếu là Channel: "general", "inventory", "purchase", "sales", "finance", "technical"
    /// </summary>
    public string? DepartmentCode { get; set; }
    
    /// <summary>
    /// Mô tả kênh chat
    /// </summary>
    public string? Description { get; set; }

    /// <summary>
    /// Danh sách thành viên tham gia hội thoại
    /// </summary>
    public ICollection<ConversationMember> Members { get; set; } = new List<ConversationMember>();

    /// <summary>
    /// Danh sách tin nhắn trong hội thoại
    /// </summary>
    public ICollection<ChatMessage> Messages { get; set; } = new List<ChatMessage>();
}
