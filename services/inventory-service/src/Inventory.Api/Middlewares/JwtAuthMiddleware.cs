using System.Net;
using System.Security.Claims;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Inventory.Application.Common.Models;

namespace Inventory.Api.Middlewares;

public class JwtAuthMiddleware
{
    private readonly RequestDelegate _next;
    private readonly ILogger<JwtAuthMiddleware> _logger;
    private readonly string _jwtSecret;
    private readonly bool _isEnabled;

    private static readonly string[] AnonymousPaths = new[]
    {
        "/health",
        "/api/health",
        "/api/v1/inventory/health",
        "/swagger"
    };

    public JwtAuthMiddleware(RequestDelegate next, ILogger<JwtAuthMiddleware> logger, IConfiguration configuration)
    {
        _next = next;
        _logger = logger;
        _jwtSecret = configuration["Jwt:Secret"] ?? "smart_erp_super_secure_jwt_secret_key_2026";
        _isEnabled = configuration.GetValue<bool?>("Jwt:Enabled") ?? true;
    }

    public async Task InvokeAsync(HttpContext context)
    {
        // 0. Bỏ qua xác thực nếu cấu hình tắt (phục vụ Testing / Local bypass)
        if (!_isEnabled)
        {
            await _next(context);
            return;
        }

        // 1. Cho phép CORS Preflight đi qua
        if (HttpMethods.IsOptions(context.Request.Method))
        {
            await _next(context);
            return;
        }

        // 2. Cho phép các route công khai (Health Check, Swagger UI)
        var path = context.Request.Path.Value ?? string.Empty;
        if (AnonymousPaths.Any(p => path.StartsWith(p, StringComparison.OrdinalIgnoreCase)))
        {
            await _next(context);
            return;
        }

        var correlationId = context.Items["CorrelationId"]?.ToString()
            ?? context.Request.Headers["X-Correlation-ID"].FirstOrDefault()
            ?? $"req_corr_{Guid.NewGuid():N}";

        // 3. Trích xuất Authorization header
        var authHeader = context.Request.Headers["Authorization"].FirstOrDefault();
        if (string.IsNullOrWhiteSpace(authHeader) || !authHeader.StartsWith("Bearer ", StringComparison.OrdinalIgnoreCase))
        {
            _logger.LogWarning("[{CorrelationId}] Unauthorized access attempt to {Path}: Missing or malformed Bearer token", correlationId, path);
            await WriteErrorResponseAsync(context, HttpStatusCode.Unauthorized, "UNAUTHORIZED", "Yêu cầu cần token xác thực hợp lệ (Bearer Token).", correlationId);
            return;
        }

        var token = authHeader["Bearer ".Length..].Trim();

        // 4. Kiểm tra cấu trúc JWT (3 phần phân tách bởi dấu chấm)
        var parts = token.Split('.');
        if (parts.Length != 3)
        {
            _logger.LogWarning("[{CorrelationId}] Invalid JWT format: Token must contain 3 segments", correlationId);
            await WriteErrorResponseAsync(context, HttpStatusCode.Unauthorized, "INVALID_TOKEN", "Định dạng token không đúng chuẩn JWT.", correlationId);
            return;
        }

        try
        {
            var headerBytes = Base64UrlDecode(parts[0]);
            var payloadBytes = Base64UrlDecode(parts[1]);

            // 5. Xác minh chữ ký HMAC-SHA256 với secret key chung của hệ thống
            using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(_jwtSecret));
            var rawData = Encoding.UTF8.GetBytes($"{parts[0]}.{parts[1]}");
            var computedSignature = Base64UrlEncode(hmac.ComputeHash(rawData));

            if (!CryptographicOperations.FixedTimeEquals(
                Encoding.UTF8.GetBytes(computedSignature),
                Encoding.UTF8.GetBytes(parts[2])))
            {
                _logger.LogWarning("[{CorrelationId}] Invalid JWT signature for path {Path}", correlationId, path);
                await WriteErrorResponseAsync(context, HttpStatusCode.Unauthorized, "INVALID_TOKEN", "Chữ ký token xác thực không hợp lệ.", correlationId);
                return;
            }

            // 6. Kiểm tra thời hạn hết hạn (exp)
            using var doc = JsonDocument.Parse(payloadBytes);
            var root = doc.RootElement;

            if (root.TryGetProperty("exp", out var expElement))
            {
                var expSeconds = expElement.GetInt64();
                var currentSeconds = DateTimeOffset.UtcNow.ToUnixTimeSeconds();
                if (currentSeconds > expSeconds)
                {
                    _logger.LogWarning("[{CorrelationId}] Expired JWT token for path {Path}", correlationId, path);
                    await WriteErrorResponseAsync(context, HttpStatusCode.Unauthorized, "TOKEN_EXPIRED", "Token xác thực đã hết hạn. Vui lòng đăng nhập lại.", correlationId);
                    return;
                }
            }

            // 7. Gán User Claims vào HttpContext để Controllers có thể sử dụng
            var claims = new List<Claim>();
            string userId = string.Empty;

            if (root.TryGetProperty("sub", out var subElement))
            {
                userId = subElement.GetString() ?? string.Empty;
                claims.Add(new Claim(ClaimTypes.NameIdentifier, userId));
            }

            if (root.TryGetProperty("name", out var nameElement))
            {
                claims.Add(new Claim(ClaimTypes.Name, nameElement.GetString() ?? string.Empty));
            }

            if (root.TryGetProperty("email", out var emailElement))
            {
                claims.Add(new Claim(ClaimTypes.Email, emailElement.GetString() ?? string.Empty));
            }

            if (root.TryGetProperty("roles", out var rolesElement) && rolesElement.ValueKind == JsonValueKind.Array)
            {
                foreach (var role in rolesElement.EnumerateArray())
                {
                    var roleStr = role.GetString();
                    if (!string.IsNullOrEmpty(roleStr))
                    {
                        claims.Add(new Claim(ClaimTypes.Role, roleStr));
                    }
                }
            }

            if (root.TryGetProperty("permissions", out var permsElement) && permsElement.ValueKind == JsonValueKind.Array)
            {
                foreach (var perm in permsElement.EnumerateArray())
                {
                    var permStr = perm.GetString();
                    if (!string.IsNullOrEmpty(permStr))
                    {
                        claims.Add(new Claim("permission", permStr));
                    }
                }
            }

            var identity = new ClaimsIdentity(claims, "Bearer");
            context.User = new ClaimsPrincipal(identity);
            context.Items["UserId"] = userId;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "[{CorrelationId}] Error decoding or parsing JWT token", correlationId);
            await WriteErrorResponseAsync(context, HttpStatusCode.Unauthorized, "INVALID_TOKEN", "Không thể giải mã token xác thực.", correlationId);
            return;
        }

        await _next(context);
    }

    private static async Task WriteErrorResponseAsync(
        HttpContext context,
        HttpStatusCode statusCode,
        string errorCode,
        string message,
        string correlationId)
    {
        context.Response.ContentType = "application/json";
        context.Response.StatusCode = (int)statusCode;

        var envelope = ApiEnvelope<object>.Fail(errorCode, message, null, correlationId);

        var jsonOptions = new JsonSerializerOptions
        {
            PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
            DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull
        };

        var responseJson = JsonSerializer.Serialize(envelope, jsonOptions);
        await context.Response.WriteAsync(responseJson);
    }

    private static byte[] Base64UrlDecode(string input)
    {
        var output = input.Replace('-', '+').Replace('_', '/');
        switch (output.Length % 4)
        {
            case 2: output += "=="; break;
            case 3: output += "="; break;
        }
        return Convert.FromBase64String(output);
    }

    private static string Base64UrlEncode(byte[] input)
    {
        return Convert.ToBase64String(input)
            .TrimEnd('=')
            .Replace('+', '-')
            .Replace('/', '_');
    }
}
