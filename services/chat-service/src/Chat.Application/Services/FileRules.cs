using Chat.Application.Common.Models;
using Chat.Application.Dtos;

namespace Chat.Application.Services;

/// <summary>
/// Quy tắc tệp: chỉ nhận đuôi trong danh sách, kiểu MIME lấy theo đuôi (không tin client).
/// Không nhận SVG/HTML vì mở trực tiếp cùng origin có thể chạy script.
/// </summary>
public static class FileRules
{
    public const int MaxFilesPerMessage = 10;
    public const long MaxFileBytes = 20 * 1024 * 1024;
    public const long MaxAvatarBytes = 5 * 1024 * 1024;

    private static readonly Dictionary<string, string> ContentTypes = new(StringComparer.OrdinalIgnoreCase)
    {
        [".pdf"] = "application/pdf",
        [".doc"] = "application/msword",
        [".docx"] = "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        [".xls"] = "application/vnd.ms-excel",
        [".xlsx"] = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        [".ppt"] = "application/vnd.ms-powerpoint",
        [".pptx"] = "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        [".txt"] = "text/plain",
        [".csv"] = "text/csv",
        [".jpg"] = "image/jpeg",
        [".jpeg"] = "image/jpeg",
        [".png"] = "image/png",
        [".gif"] = "image/gif",
        [".webp"] = "image/webp",
        [".zip"] = "application/zip",
        [".rar"] = "application/vnd.rar",
        [".7z"] = "application/x-7z-compressed",
        [".step"] = "application/step",
        [".stp"] = "application/step"
    };

    private static readonly HashSet<string> AvatarExtensions = new(StringComparer.OrdinalIgnoreCase) { ".jpg", ".jpeg", ".png", ".webp" };

    public static bool IsImage(string contentType) => contentType.StartsWith("image/", StringComparison.Ordinal);

    /// <summary>Trả về (đuôi, MIME) hoặc ném lỗi nếu tệp không hợp lệ.</summary>
    public static (string Extension, string ContentType) ValidateAttachment(UploadedFile file)
    {
        var extension = Path.GetExtension(file.FileName);
        if (!ContentTypes.TryGetValue(extension, out var contentType))
            throw ChatException.Invalid($"Định dạng tệp \"{file.FileName}\" không được hỗ trợ.");
        if (file.Size <= 0 || file.Size > MaxFileBytes)
            throw ChatException.Invalid($"Tệp \"{file.FileName}\" phải nhỏ hơn 20 MB.");
        return (extension.ToLowerInvariant(), contentType);
    }

    public static (string Extension, string ContentType) ValidateAvatar(UploadedFile file)
    {
        var extension = Path.GetExtension(file.FileName);
        if (!AvatarExtensions.Contains(extension))
            throw ChatException.Invalid("Ảnh nhóm phải là JPG, PNG hoặc WEBP.");
        if (file.Size <= 0 || file.Size > MaxAvatarBytes)
            throw ChatException.Invalid("Ảnh nhóm phải nhỏ hơn 5 MB.");
        return (extension.ToLowerInvariant(), ContentTypes[extension]);
    }

    /// <summary>Tên tệp an toàn để lưu và trả về header (bỏ đường dẫn, ký tự điều khiển).</summary>
    public static string SafeFileName(string fileName)
    {
        var name = Path.GetFileName(fileName);
        name = new string(name.Where(ch => !char.IsControl(ch)).ToArray()).Trim();
        if (name.Length > 200) name = name[^200..];
        return string.IsNullOrEmpty(name) ? "tep-dinh-kem" : name;
    }
}
