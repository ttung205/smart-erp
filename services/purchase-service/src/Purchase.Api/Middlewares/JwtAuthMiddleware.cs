using System.Net;
using System.Security.Claims;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Purchase.Application.Common.Models;
using Purchase.Application.Interfaces;

namespace Purchase.Api.Middlewares;

/// <summary>
/// Lớp HTTP của xác thực: bỏ qua route công khai, lấy Bearer token và gắn ClaimsPrincipal vào HttpContext.
/// Việc kiểm tra token thuộc về IAccessTokenValidator (Infrastructure).
/// </summary>
public class JwtAuthMiddleware
{
    private readonly RequestDelegate _next;
    private readonly ILogger<JwtAuthMiddleware> _logger;
    private readonly bool _isEnabled;
    private readonly string? _internalKey;

    private static readonly string[] AnonymousPaths =
    [
        "/health",
        "/api/health",
        "/api/v1/purchase/health",
        "/swagger"
    ];

    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull
    };

    public JwtAuthMiddleware(RequestDelegate next, ILogger<JwtAuthMiddleware> logger, IConfiguration configuration)
    {
        _next = next;
        _logger = logger;
        _isEnabled = configuration.GetValue<bool?>("Jwt:Enabled") ?? true;
        _internalKey = configuration["Services:InternalKey"];
    }

    public async Task InvokeAsync(HttpContext context, IAccessTokenValidator validator)
    {
        var path = context.Request.Path.Value ?? string.Empty;

        // Tắt xác thực (Testing / Local bypass), CORS preflight và route công khai (Health Check, Swagger UI)
        if (!_isEnabled
            || HttpMethods.IsOptions(context.Request.Method)
            || AnonymousPaths.Any(p => path.StartsWith(p, StringComparison.OrdinalIgnoreCase)))
        {
            await _next(context);
            return;
        }

        var correlationId = context.Items["CorrelationId"]?.ToString()
            ?? context.Request.Headers["X-Correlation-ID"].FirstOrDefault()
            ?? $"req_corr_{Guid.NewGuid():N}";

        // Đường dẫn /internal chỉ dành cho service khác (X-Internal-Service-Key), không nhận JWT người dùng
        if (path.StartsWith("/internal/", StringComparison.OrdinalIgnoreCase))
        {
            var serviceKey = context.Request.Headers["X-Internal-Service-Key"].FirstOrDefault();
            if (string.IsNullOrEmpty(_internalKey) || string.IsNullOrEmpty(serviceKey)
                || !CryptographicOperations.FixedTimeEquals(Encoding.UTF8.GetBytes(serviceKey), Encoding.UTF8.GetBytes(_internalKey)))
            {
                _logger.LogWarning("[{CorrelationId}] Rejected internal call to {Path}: missing or invalid service key", correlationId, path);
                await WriteErrorResponseAsync(context, "UNAUTHORIZED", "Cần khóa dịch vụ nội bộ hợp lệ.", correlationId);
                return;
            }

            context.User = new ClaimsPrincipal(new ClaimsIdentity([new Claim(ClaimTypes.Name, "internal-service")], "InternalServiceKey"));
            await _next(context);
            return;
        }

        var authHeader = context.Request.Headers.Authorization.FirstOrDefault();
        if (string.IsNullOrWhiteSpace(authHeader) || !authHeader.StartsWith("Bearer ", StringComparison.OrdinalIgnoreCase))
        {
            _logger.LogWarning("[{CorrelationId}] Unauthorized access attempt to {Path}: Missing or malformed Bearer token", correlationId, path);
            await WriteErrorResponseAsync(context, "UNAUTHORIZED", "Yêu cầu cần token xác thực hợp lệ (Bearer Token).", correlationId);
            return;
        }

        var result = validator.Validate(authHeader["Bearer ".Length..].Trim());
        if (!result.IsValid)
        {
            _logger.LogWarning("[{CorrelationId}] Rejected JWT for {Path}: {ErrorCode}", correlationId, path, result.ErrorCode);
            await WriteErrorResponseAsync(context, result.ErrorCode!, result.ErrorMessage!, correlationId);
            return;
        }

        context.User = result.Principal!;
        context.Items["UserId"] = context.User.FindFirstValue(ClaimTypes.NameIdentifier);

        await _next(context);
    }

    private static async Task WriteErrorResponseAsync(HttpContext context, string errorCode, string message, string correlationId)
    {
        context.Response.ContentType = "application/json";
        context.Response.StatusCode = (int)HttpStatusCode.Unauthorized;

        var envelope = ApiEnvelope<object>.Fail(errorCode, message, null, correlationId);
        await context.Response.WriteAsync(JsonSerializer.Serialize(envelope, JsonOptions));
    }
}
