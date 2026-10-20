namespace Chat.Domain.Enums;

public enum ConversationType
{
    Direct = 1,
    Group = 2,
    Work = 3
}

/// <summary>Vai trò trong nhóm: 1 trưởng nhóm, nhiều phó nhóm. Hội thoại 1-1 chỉ có Member.</summary>
public enum MemberRole
{
    Member = 1,
    Deputy = 2,
    Owner = 3
}

public enum MessageType
{
    /// <summary>Tin chữ, có thể kèm tệp.</summary>
    Text = 1,
    /// <summary>Chỉ có tệp, không có chữ.</summary>
    File = 2,
    /// <summary>Tin hệ thống: "A đã thêm B vào nhóm"... không có người gửi.</summary>
    System = 3
}
