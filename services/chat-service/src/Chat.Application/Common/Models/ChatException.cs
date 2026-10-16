namespace Chat.Application.Common.Models;

/// <summary>Lỗi nghiệp vụ trả về client với mã HTTP và thông điệp tiếng Việt.</summary>
public class ChatException(int statusCode, string code, string message) : Exception(message)
{
    public int StatusCode { get; } = statusCode;
    public string Code { get; } = code;

    public static ChatException NotFound(string message = "Không tìm thấy hội thoại.") => new(404, "NOT_FOUND", message);
    public static ChatException Forbidden(string message = "Bạn không có quyền thực hiện thao tác này.") => new(403, "FORBIDDEN", message);
    public static ChatException Invalid(string message) => new(422, "VALIDATION_FAILED", message);
}

public record DirectoryUser(long Id, string Name, string? Email, long? DepartmentId, string? DepartmentName, bool IsActive);
