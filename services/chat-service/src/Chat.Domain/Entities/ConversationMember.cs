using Chat.Domain.Enums;

namespace Chat.Domain.Entities;

/// <summary>Thành viên hội thoại (nhúng trong Conversation) kèm trạng thái riêng của từng người (đã đọc, tắt thông báo, ghim).</summary>
public class ConversationMember
{
    public long UserId { get; set; }

    /// <summary>Tên lúc được thêm vào, dùng khi không lấy được danh bạ từ identity-service.</summary>
    public string UserName { get; set; } = string.Empty;

    public MemberRole Role { get; set; } = MemberRole.Member;

    /// <summary>Con trỏ đã đọc: mọi tin có id &lt;= giá trị này coi như đã đọc. Chỉ tiến, không lùi.</summary>
    public long? LastReadMessageId { get; set; }
    public DateTime? LastReadAt { get; set; }

    public bool Muted { get; set; }
    public DateTime? PinnedAt { get; set; }
    public DateTime JoinedAt { get; set; } = DateTime.UtcNow;
}
