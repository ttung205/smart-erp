using System.Security.Claims;
using Inventory.Api.Authorization;
using Inventory.Api.Controllers;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Abstractions;
using Microsoft.AspNetCore.Mvc.Filters;
using Microsoft.AspNetCore.Routing;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace Inventory.Api.IntegrationTests;

public class RequirePermissionAttributeTests
{
    private static ClaimsPrincipal User(params string[] permissions)
    {
        var claims = permissions.Select(p => new Claim(PermissionRules.PermissionClaim, p));
        return new ClaimsPrincipal(new ClaimsIdentity(claims, "Test"));
    }

    private static AuthorizationFilterContext Run(string[] required, ClaimsPrincipal user, bool jwtEnabled = true)
    {
        var services = new ServiceCollection()
            .AddSingleton<IConfiguration>(new ConfigurationBuilder()
                .AddInMemoryCollection(new Dictionary<string, string?> { ["Jwt:Enabled"] = jwtEnabled.ToString() }).Build())
            .AddSingleton(typeof(ILogger<>), typeof(NullLogger<>))
            .BuildServiceProvider();
        var http = new DefaultHttpContext { User = user, RequestServices = services };
        var context = new AuthorizationFilterContext(
            new ActionContext(http, new RouteData(), new ActionDescriptor()), new List<IFilterMetadata>());
        new RequirePermissionAttribute(required).OnAuthorization(context);
        return context;
    }

    [Fact]
    public void Exact_permission_is_allowed()
        => Assert.Null(Run(["inventory.receive"], User("inventory.view", "inventory.receive")).Result);

    [Fact]
    public void Any_one_of_the_listed_permissions_is_enough()
        => Assert.Null(Run(["order.create", "sale-invoice.create"], User("sale-invoice.create")).Result);

    [Fact]
    public void Missing_permission_returns_403_permission_denied()
    {
        var result = Assert.IsType<ObjectResult>(Run(["inventory.issue"], User("inventory.view")).Result);
        Assert.Equal(StatusCodes.Status403Forbidden, result.StatusCode);
        Assert.Contains("PERMISSION_DENIED", System.Text.Json.JsonSerializer.Serialize(result.Value));
    }

    [Fact]
    public void Module_manage_and_super_admin_are_allowed()
    {
        Assert.Null(Run(["inventory.issue"], User("inventory.manage")).Result);
        var superAdmin = new ClaimsPrincipal(new ClaimsIdentity([new Claim(ClaimTypes.Role, "SUPER_ADMIN")], "Test"));
        Assert.Null(Run(["inventory.issue"], superAdmin).Result);
    }

    [Fact]
    public void Other_module_manage_does_not_grant_inventory()
        => Assert.NotNull(Run(["inventory.view"], User("purchase.manage")).Result);

    [Fact]
    public void Internal_service_principal_is_allowed_without_user_permissions()
    {
        var internalPrincipal = new ClaimsPrincipal(new ClaimsIdentity(
            [new Claim(ClaimTypes.Name, "internal-service")], PermissionRules.InternalServiceAuthType));
        Assert.Null(Run(["order.create", "sale-invoice.create"], internalPrincipal).Result);
    }

    [Fact]
    public void Disabled_jwt_skips_check_like_the_middleware()
        => Assert.Null(Run(["inventory.manage"], User(), jwtEnabled: false).Result);

    [Theory]
    [InlineData(typeof(StocksController), "ChangePhysicalStock", "inventory.manage")]
    [InlineData(typeof(StocksController), "ReserveStock", "sale-invoice.create")]
    [InlineData(typeof(StocksController), "BatchReserveStock", "sale-invoice.create")]
    [InlineData(typeof(StocksController), "ReleaseStock", "sale-invoice.create")]
    [InlineData(typeof(StocksController), "ReleaseDocument", "sale-invoice.create")]
    [InlineData(typeof(StocksController), "ReceiveStock", "inventory.receive")]
    [InlineData(typeof(StocksController), "IssueStock", "inventory.issue")]
    [InlineData(typeof(StocksController), "GetStocks", "inventory.view")]
    [InlineData(typeof(ReservationsController), "ChangeReservationStock", "inventory.manage")]
    [InlineData(typeof(WarehousesController), "Create", "inventory.manage")]
    [InlineData(typeof(SalesReturnsController), "Receive", "inventory.receive")]
    [InlineData(typeof(SalesReturnsController), "Transfer", "inventory.transfer")]
    public void Actions_declare_the_expected_permission(Type controller, string action, string permission)
    {
        var attribute = controller.GetMethod(action)!.GetCustomAttributes(typeof(RequirePermissionAttribute), false)
            .Cast<RequirePermissionAttribute>().Single();
        Assert.Contains(permission, attribute.Permissions);
    }
}
