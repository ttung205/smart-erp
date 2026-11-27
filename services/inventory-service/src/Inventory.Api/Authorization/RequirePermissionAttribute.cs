using System.Security.Claims;
using Inventory.Application.Common.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Filters;

namespace Inventory.Api.Authorization;

/// <summary>
/// Mã quyền (khớp bảng permissions của identity-service). Quyền nghiệp vụ bán hàng có mặt ở đây vì
/// sales-service chuyển tiếp JWT của người lập chứng từ khi giữ hàng, nhả hàng, xuất kho, nhận hàng trả.
/// </summary>
public static class Perm
{
    public const string View = "inventory.view";
    public const string Manage = "inventory.manage";
    public const string Receive = "inventory.receive";
    public const string Issue = "inventory.issue";
    public const string Transfer = "inventory.transfer";

    public const string SaleInvoiceCreate = "sale-invoice.create";
    public const string InvoiceManage = "invoice.manage";
    public const string SaleReturnTechnical = "sale_return.technical";
    public const string SaleReturnPost = "sale_return.post";
}

/// <summary>
/// Quy tắc quyền giống các service Laravel (Actor::can) và purchase-service:
/// SUPER_ADMIN được mọi thao tác; có đúng quyền thì được; có quyền quản lý module (vd. inventory.manage)
/// thì được mọi quyền inventory.*.
/// </summary>
public static class PermissionRules
{
    public const string PermissionClaim = "permission";

    /// <summary>Danh tính do JwtAuthMiddleware gắn cho lời gọi nội bộ bằng X-Internal-Service-Key (job đối soát).</summary>
    public const string InternalServiceAuthType = "InternalServiceKey";

    public static bool Can(ClaimsPrincipal user, string permission)
    {
        if (user.IsInRole("SUPER_ADMIN") || user.HasClaim(PermissionClaim, permission))
        {
            return true;
        }

        var dot = permission.IndexOf('.');
        return dot > 0 && user.HasClaim(PermissionClaim, permission[..dot] + ".manage");
    }

    public static bool CanAny(ClaimsPrincipal user, IEnumerable<string> permissions) =>
        user.Identity?.AuthenticationType == InternalServiceAuthType || permissions.Any(p => Can(user, p));
}

/// <summary>
/// Chặn action khi JWT không có ít nhất một trong các quyền yêu cầu: trả 403 PERMISSION_DENIED theo envelope chuẩn.
/// Nhận nhiều quyền vì lệnh kho thường do sales-service gọi thay người dùng (chuyển tiếp JWT): người lập
/// chứng từ bán có quyền nghiệp vụ bán hàng (vd. sale-invoice.create) chứ không có quyền kho.
/// Bỏ qua khi tắt xác thực (Jwt:Enabled=false, chỉ dùng cho test/local) giống JwtAuthMiddleware.
/// </summary>
[AttributeUsage(AttributeTargets.Method | AttributeTargets.Class, AllowMultiple = false)]
public sealed class RequirePermissionAttribute(params string[] permissions) : Attribute, IAuthorizationFilter
{
    public IReadOnlyList<string> Permissions { get; } = permissions;

    public void OnAuthorization(AuthorizationFilterContext context)
    {
        var http = context.HttpContext;
        var configuration = http.RequestServices.GetRequiredService<IConfiguration>();
        if (!(configuration.GetValue<bool?>("Jwt:Enabled") ?? true) || PermissionRules.CanAny(http.User, Permissions))
        {
            return;
        }

        var required = string.Join(" hoặc ", Permissions);
        var correlationId = http.Items["CorrelationId"]?.ToString() ?? http.Request.Headers["X-Correlation-ID"].FirstOrDefault();
        var logger = http.RequestServices.GetRequiredService<ILogger<RequirePermissionAttribute>>();
        logger.LogWarning("[{CorrelationId}] service=inventory-service user_id={UserId} thiếu quyền {Permission} cho {Method} {Path}",
            correlationId, http.User.FindFirstValue(ClaimTypes.NameIdentifier), required, http.Request.Method, http.Request.Path);

        context.Result = new ObjectResult(ApiEnvelope<object>.Fail(
            "PERMISSION_DENIED", $"Bạn cần quyền {required} để thực hiện thao tác này.", null, correlationId))
        {
            StatusCode = StatusCodes.Status403Forbidden,
        };
    }
}
