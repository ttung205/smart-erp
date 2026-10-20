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

    /// <summary>Loại chứng từ ERP (vd: "Sale", "Quotation", "Purchase"). Null với Direct/Group.</summary>
    public string? SubjectType { get; set; }

    /// <summary>ID của chứng từ ERP. Null với Direct/Group.</summary>
    public long? SubjectId { get; set; }

    /// <summary>Mã chứng từ hiển thị (vd: "SO-2026-001").</summary>
    public string? SubjectCode { get; set; }

    /// <summary>Mô tả hoặc tiêu đề tóm tắt của chứng từ.</summary>
    public string? SubjectTitle { get; set; }

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
