using Chat.Domain.Common;
using Chat.Domain.Enums;

namespace Chat.Domain.Entities;

public class ConversationMember : BaseEntity
{
    public Guid ConversationId { get; set; }
    public Conversation Conversation { get; set; } = null!;

    /// <summary>
    /// ID nguyên thủy của User từ identity-service
    /// </summary>
    public int UserId { get; set; }

    /// <summary>
    /// Snapshot tên nhân viên
    /// </summary>
    public string? UserName { get; set; }

    /// <summary>
    /// Snapshot email nhân viên
    /// </summary>
    public string? UserEmail { get; set; }

    /// <summary>
    /// Snapshot avatar nhân viên
    /// </summary>
    public string? UserAvatar { get; set; }

    /// <summary>
    /// Vai trò trong nhóm (Member / Admin)
    /// </summary>
    public MemberRole Role { get; set; } = MemberRole.Member;

    /// <summary>
    /// Thời điểm tham gia nhóm
    /// </summary>
    public DateTime JoinedAt { get; set; } = DateTime.UtcNow;
}
