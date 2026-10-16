using System.Security.Claims;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Microsoft.Extensions.Configuration;
using Purchase.Application.Common.Models;
using Purchase.Application.Interfaces;

namespace Purchase.Infrastructure.Security;

/// <summary>
/// Kiểm tra JWT HS256 do identity-service cấp, cùng quy tắc với các service PHP/Node:
/// alg = HS256, chữ ký khớp Jwt:Secret, iss = identity-service, có sub và exp chưa quá hạn.
/// </summary>
public sealed class HmacJwtTokenValidator : IAccessTokenValidator
{
    public const string Issuer = "identity-service";

    private readonly byte[] _secret;

    public HmacJwtTokenValidator(IConfiguration configuration)
    {
        var secret = configuration["Jwt:Secret"];
        if (string.IsNullOrWhiteSpace(secret))
        {
            // Không dùng secret mặc định: thiếu cấu hình thì báo lỗi ngay thay vì chấp nhận token ký bằng secret công khai
            throw new InvalidOperationException("Jwt:Secret chưa được cấu hình.");
        }
        _secret = Encoding.UTF8.GetBytes(secret);
    }

    public AccessTokenResult Validate(string token)
    {
        var parts = token.Split('.');
        if (parts.Length != 3)
        {
            return Invalid("Định dạng token không đúng chuẩn JWT.");
        }

        try
        {
            using (var header = JsonDocument.Parse(Base64UrlDecode(parts[0])))
            {
                if (ReadString(header.RootElement, "alg") != "HS256")
                {
                    return Invalid("Thuật toán ký token không được hỗ trợ.");
                }
            }

            // So chữ ký trước khi tin bất kỳ claim nào trong payload
            using var hmac = new HMACSHA256(_secret);
            var expected = hmac.ComputeHash(Encoding.UTF8.GetBytes($"{parts[0]}.{parts[1]}"));
            if (!CryptographicOperations.FixedTimeEquals(expected, Base64UrlDecode(parts[2])))
            {
                return Invalid("Chữ ký token xác thực không hợp lệ.");
            }

            using var payload = JsonDocument.Parse(Base64UrlDecode(parts[1]));
            var root = payload.RootElement;
            if (ReadString(root, "iss") != Issuer || string.IsNullOrEmpty(ReadString(root, "sub")))
            {
                return Invalid("Token không do identity-service cấp.");
            }

            if (!root.TryGetProperty("exp", out var exp) || exp.ValueKind != JsonValueKind.Number
                || exp.GetInt64() <= DateTimeOffset.UtcNow.ToUnixTimeSeconds())
            {
                return AccessTokenResult.Failure("TOKEN_EXPIRED", "Token xác thực đã hết hạn. Vui lòng đăng nhập lại.");
            }

            return AccessTokenResult.Success(BuildPrincipal(root));
        }
        catch (Exception ex) when (ex is FormatException or JsonException or InvalidOperationException)
        {
            return Invalid("Không thể giải mã token xác thực.");
        }
    }

    private static ClaimsPrincipal BuildPrincipal(JsonElement root)
    {
        var claims = new List<Claim> { new(ClaimTypes.NameIdentifier, ReadString(root, "sub")!) };
        if (ReadString(root, "name") is { } name) claims.Add(new Claim(ClaimTypes.Name, name));
        if (ReadString(root, "email") is { } email) claims.Add(new Claim(ClaimTypes.Email, email));
        claims.AddRange(ReadStrings(root, "roles").Select(role => new Claim(ClaimTypes.Role, role)));
        claims.AddRange(ReadStrings(root, "permissions").Select(permission => new Claim("permission", permission)));

        return new ClaimsPrincipal(new ClaimsIdentity(claims, "Bearer"));
    }

    private static AccessTokenResult Invalid(string message) => AccessTokenResult.Failure("INVALID_TOKEN", message);

    private static string? ReadString(JsonElement root, string name) =>
        root.TryGetProperty(name, out var value) && value.ValueKind == JsonValueKind.String ? value.GetString() : null;

    private static IEnumerable<string> ReadStrings(JsonElement root, string name) =>
        root.TryGetProperty(name, out var values) && values.ValueKind == JsonValueKind.Array
            ? values.EnumerateArray().Where(v => v.ValueKind == JsonValueKind.String).Select(v => v.GetString()!).Where(v => v.Length > 0).ToList()
            : [];

    private static byte[] Base64UrlDecode(string input)
    {
        var output = input.Replace('-', '+').Replace('_', '/');
        output += (output.Length % 4) switch { 2 => "==", 3 => "=", _ => string.Empty };
        return Convert.FromBase64String(output);
    }
}
