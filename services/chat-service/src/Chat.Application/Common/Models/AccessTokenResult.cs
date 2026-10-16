using System.Security.Claims;

namespace Chat.Application.Common.Models;

/// <summary>Kết quả kiểm tra access token: Principal khi hợp lệ, hoặc mã lỗi và thông điệp trả về client.</summary>
public sealed record AccessTokenResult(ClaimsPrincipal? Principal, string? ErrorCode, string? ErrorMessage)
{
    public bool IsValid => Principal is not null;

    public static AccessTokenResult Success(ClaimsPrincipal principal) => new(principal, null, null);

    public static AccessTokenResult Failure(string errorCode, string errorMessage) => new(null, errorCode, errorMessage);
}
