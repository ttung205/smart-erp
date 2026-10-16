using System.Net;
using System.Security.Claims;
using System.Text.Json;
using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;

namespace Chat.Api.Middlewares;

/// <summary>
/// Lớp HTTP của xác thực: bỏ qua route công khai, lấy Bearer token và gắn ClaimsPrincipal vào HttpContext.
/// Việc kiểm tra token thuộc về IAccessTokenValidator (Infrastructure).
/// </summary>
public class JwtAuthMiddleware(RequestDelegate next, ILogger<JwtAuthMiddleware> logger, IConfiguration configuration)
{
    public const string HubPath = "/api/v1/chat/hub";

    private readonly bool _isEnabled = configuration.GetValue<bool?>("Jwt:Enabled") ?? true;

    private static readonly string[] AnonymousPaths =
    [
        "/health",
        "/api/health",
        "/api/v1/chat/health",
        "/swagger"
    ];

    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull
    };

    public async Task InvokeAsync(HttpContext context, IAccessTokenValidator validator)
    {
        var path = context.Request.Path.Value ?? string.Empty;

        if (!_isEnabled
            || HttpMethods.IsOptions(context.Request.Method)
            || AnonymousPaths.Any(p => path.StartsWith(p, StringComparison.OrdinalIgnoreCase)))
        {
            await next(context);
            return;
        }

        var correlationId = context.Items["CorrelationId"]?.ToString() ?? $"req_corr_{Guid.NewGuid():N}";

        string? token = null;
        var authHeader = context.Request.Headers.Authorization.FirstOrDefault();
        if (!string.IsNullOrWhiteSpace(authHeader) && authHeader.StartsWith("Bearer ", StringComparison.OrdinalIgnoreCase))
        {
            token = authHeader["Bearer ".Length..].Trim();
        }
        else if (path.StartsWith(HubPath, StringComparison.OrdinalIgnoreCase))
        {
            // Trình duyệt không gửi được header Authorization qua WebSocket nên SignalR truyền token trên query
            token = context.Request.Query["access_token"].FirstOrDefault();
        }

        if (string.IsNullOrWhiteSpace(token))
        {
            logger.LogWarning("[{CorrelationId}] Unauthorized access attempt to {Path}: Missing Bearer token", correlationId, path);
            await WriteErrorResponseAsync(context, "UNAUTHORIZED", "Yêu cầu cần token xác thực hợp lệ (Bearer Token).", correlationId);
            return;
        }

        var result = validator.Validate(token);
        if (!result.IsValid)
        {
            logger.LogWarning("[{CorrelationId}] Rejected JWT for {Path}: {ErrorCode}", correlationId, path, result.ErrorCode);
            await WriteErrorResponseAsync(context, result.ErrorCode!, result.ErrorMessage!, correlationId);
            return;
        }

        context.User = result.Principal!;
        context.Items["UserId"] = context.User.FindFirstValue(ClaimTypes.NameIdentifier);

        await next(context);
    }

    private static async Task WriteErrorResponseAsync(HttpContext context, string errorCode, string message, string correlationId)
    {
        context.Response.ContentType = "application/json";
        context.Response.StatusCode = (int)HttpStatusCode.Unauthorized;
        var envelope = ApiEnvelope<object>.Fail(errorCode, message, null, correlationId);
        await context.Response.WriteAsync(JsonSerializer.Serialize(envelope, JsonOptions));
    }
}
