using System.Security.Claims;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Abstractions;
using Microsoft.AspNetCore.Mvc.Filters;
using Microsoft.AspNetCore.Routing;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Logging.Abstractions;
using Purchase.Api.Authorization;
using Purchase.Api.Controllers;
using Xunit;

namespace Purchase.Tests;

public class RequirePermissionAttributeTests
{
    private static ClaimsPrincipal User(string[]? roles = null, params string[] permissions)
    {
        var claims = permissions.Select(p => new Claim(PermissionRules.PermissionClaim, p))
            .Concat((roles ?? []).Select(r => new Claim(ClaimTypes.Role, r)));
        return new ClaimsPrincipal(new ClaimsIdentity(claims, "Test"));
    }

    private static AuthorizationFilterContext Run(string permission, ClaimsPrincipal user, bool jwtEnabled = true)
    {
        var services = new ServiceCollection()
            .AddSingleton<IConfiguration>(new ConfigurationBuilder()
                .AddInMemoryCollection(new Dictionary<string, string?> { ["Jwt:Enabled"] = jwtEnabled.ToString() }).Build())
            .AddSingleton(typeof(ILogger<>), typeof(NullLogger<>))
            .BuildServiceProvider();
        var http = new DefaultHttpContext { User = user, RequestServices = services };
        var context = new AuthorizationFilterContext(
            new ActionContext(http, new RouteData(), new ActionDescriptor()), new List<IFilterMetadata>());
        new RequirePermissionAttribute(permission).OnAuthorization(context);
        return context;
    }

    [Fact]
    public void Exact_permission_is_allowed()
        => Assert.Null(Run("purchase.create", User(null, "purchase.view", "purchase.create")).Result);

    [Fact]
    public void Missing_permission_returns_403_permission_denied()
    {
        var result = Assert.IsType<ObjectResult>(Run("purchase.approve", User(null, "purchase.view", "purchase.create")).Result);
        Assert.Equal(StatusCodes.Status403Forbidden, result.StatusCode);
        Assert.Contains("PERMISSION_DENIED", System.Text.Json.JsonSerializer.Serialize(result.Value));
    }

    [Fact]
    public void Module_manage_and_super_admin_are_allowed()
    {
        Assert.Null(Run("purchase.approve", User(null, "purchase.manage")).Result);
        Assert.Null(Run("purchase.approve", User(["SUPER_ADMIN"])).Result);
    }

    [Fact]
    public void Other_module_manage_does_not_grant_purchase()
        => Assert.NotNull(Run("purchase.create", User(null, "order.manage")).Result);

    [Fact]
    public void Disabled_jwt_skips_check_like_the_middleware()
        => Assert.Null(Run("purchase.approve", User(), jwtEnabled: false).Result);

    [Theory]
    [InlineData(typeof(PurchaseOrdersController), "Create", "purchase.create")]
    [InlineData(typeof(PurchaseOrdersController), "Approve", "purchase.approve")]
    [InlineData(typeof(PurchaseOrdersController), "GetList", "purchase.view")]
    [InlineData(typeof(GoodsReceiptsController), "Confirm", "purchase.create")]
    [InlineData(typeof(PurchaseRequisitionsController), "Approve", "purchase.approve")]
    [InlineData(typeof(PurchaseInvoicesController), "Post", "purchase.approve")]
    [InlineData(typeof(PurchaseReturnsController), "Approve", "purchase.approve")]
    public void Actions_declare_the_expected_permission(Type controller, string action, string permission)
    {
        var attribute = controller.GetMethod(action)!.GetCustomAttributes(typeof(RequirePermissionAttribute), false)
            .Cast<RequirePermissionAttribute>().Single();
        Assert.Equal(permission, attribute.Permission);
    }

    [Fact]
    public void Every_public_action_of_purchase_controllers_requires_a_permission()
    {
        Type[] controllers = [typeof(PurchaseOrdersController), typeof(GoodsReceiptsController), typeof(PurchaseRequisitionsController),
            typeof(PurchaseInvoicesController), typeof(PurchaseReturnsController)];
        var unguarded = controllers
            .SelectMany(c => c.GetMethods().Where(m => m.DeclaringType == c && m.ReturnType == typeof(Task<IActionResult>))
                .Select(m => (c, m)))
            .Where(x => !x.m.GetCustomAttributes(typeof(RequirePermissionAttribute), false).Any())
            .Select(x => $"{x.c.Name}.{x.m.Name}")
            .ToList();
        Assert.Empty(unguarded);
    }
}
