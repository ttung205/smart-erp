namespace Chat.Application.Dtos;

// Payload trả về client (REST và realtime). Tên thuộc tính được serialize thành snake_case.

public record MemberDto(long UserId, string Name, string Role, long? LastReadMessageId, bool IsActive);

public record MessagePreviewDto(long Id, long? SenderId, string SenderName, string Type, string Content, bool Recalled, DateTime CreatedAt);

/// <summary>Hội thoại nhìn từ phía một người dùng cụ thể (unread, mute, ghim là của riêng người đó).</summary>
public record ConversationDto(
    long Id,
    string Type,
    string? Title,
    string? AvatarUrl,
    string MyRole,
    IReadOnlyList<MemberDto> Members,
    MessagePreviewDto? LastMessage,
    DateTime? LastMessageAt,
    int UnreadCount,
    bool Muted,
    DateTime? PinnedAt,
    DateTime CreatedAt,
    string? SubjectType = null,
    long? SubjectId = null,
    string? SubjectCode = null,
    string? SubjectTitle = null);

public record SenderDto(long Id, string Name);

public record ReplyDto(long Id, string SenderName, string Content, bool Recalled);

public record AttachmentDto(long Id, string Name, long Size, string ContentType, bool IsImage);

public record ReactionUserDto(long Id, string Name);

public record ReactionDto(string Emoji, IReadOnlyList<ReactionUserDto> Users);

public record MessageDto(
    long Id,
    long ConversationId,
    string Type,
    SenderDto? Sender,
    string Content,
    bool Recalled,
    ReplyDto? ReplyTo,
    IReadOnlyList<long> Mentions,
    bool MentionAll,
    string? ForwardedFromName,
    IReadOnlyList<AttachmentDto> Attachments,
    IReadOnlyList<ReactionDto> Reactions,
    DateTime? EditedAt,
    DateTime? PinnedAt,
    long? PinnedById,
    DateTime CreatedAt);

public record MessagePageDto(IReadOnlyList<MessageDto> Data, bool HasMore);

public record ConversationPageDto(IReadOnlyList<ConversationDto> Data, int Page, int PerPage, int Total);

public record ChatUserDto(long Id, string Name, string? Email, string? DepartmentName, bool Online);

public record PreferencesDto(IReadOnlyDictionary<long, string> Aliases);

public record SearchHitDto(long Id, string Content, SenderDto? Sender, DateTime CreatedAt);

/// <summary>Tệp tải lên kèm tin nhắn (tầng Api chuyển IFormFile sang).</summary>
public record UploadedFile(string FileName, long Size, Func<Stream> OpenReadStream);

public record SendMessageInput(
    string? Content,
    long? ReplyToId,
    IReadOnlyList<long>? Mentions,
    bool MentionAll,
    IReadOnlyList<UploadedFile> Files);
