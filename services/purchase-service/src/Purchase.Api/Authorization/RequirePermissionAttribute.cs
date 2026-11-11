using System.Security.Claims;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Filters;
using Purchase.Application.Common.Models;

namespace Purchase.Api.Authorization;

/// <summary>
/// Quy tắc quyền giống các service Laravel (Actor::can): SUPER_ADMIN được mọi thao tác, có đúng quyền thì được,
/// có quyền quản lý module (vd. purchase.manage) thì được mọi quyền purchase.*.
/// </summary>
public static class PermissionRules
{
    public const string PermissionClaim = "permission";

    public static bool Can(ClaimsPrincipal user, string permission)
    {
        if (user.IsInRole("SUPER_ADMIN") || user.HasClaim(PermissionClaim, permission))
        {
            return true;
        }

        var dot = permission.IndexOf('.');
        return dot > 0 && user.HasClaim(PermissionClaim, permission[..dot] + ".manage");
    }
}

/// <summary>
/// Chặn action khi JWT không có quyền yêu cầu: trả 403 PERMISSION_DENIED theo envelope chuẩn.
/// Bỏ qua khi tắt xác thực (Jwt:Enabled=false, chỉ dùng cho test/local) giống JwtAuthMiddleware.
/// </summary>
[AttributeUsage(AttributeTargets.Method | AttributeTargets.Class, AllowMultiple = false)]
public sealed class RequirePermissionAttribute(string permission) : Attribute, IAuthorizationFilter
{
    public string Permission { get; } = permission;

    public void OnAuthorization(AuthorizationFilterContext context)
    {
        var http = context.HttpContext;
        var configuration = http.RequestServices.GetRequiredService<IConfiguration>();
        if (!(configuration.GetValue<bool?>("Jwt:Enabled") ?? true) || PermissionRules.Can(http.User, Permission))
        {
            return;
        }

        var correlationId = http.Items["CorrelationId"]?.ToString() ?? http.Request.Headers["X-Correlation-ID"].FirstOrDefault();
        var logger = http.RequestServices.GetRequiredService<ILogger<RequirePermissionAttribute>>();
        logger.LogWarning("[{CorrelationId}] User {UserId} thiếu quyền {Permission} cho {Method} {Path}",
            correlationId, http.User.FindFirstValue(ClaimTypes.NameIdentifier), Permission, http.Request.Method, http.Request.Path);

        context.Result = new ObjectResult(ApiEnvelope<object>.Fail(
            "PERMISSION_DENIED", $"Bạn cần quyền {Permission} để thực hiện thao tác này.", null, correlationId))
        {
            StatusCode = StatusCodes.Status403Forbidden,
        };
    }
}
