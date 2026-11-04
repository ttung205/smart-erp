using Inventory.Infrastructure.Persistence;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc.Testing;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;

namespace Inventory.Api.IntegrationTests;

/// <summary>
/// Chạy ứng dụng thật trên database MySQL tạm erp_inventory_{guid}_test (không bao giờ chạm erp_inventory_db của dev),
/// xóa khi factory bị hủy. CI đặt TEST_MYSQL_CONNECTION.
/// </summary>
public sealed class InventoryApiFactory : WebApplicationFactory<Program>
{
    private static readonly string BaseConnection = Environment.GetEnvironmentVariable("TEST_MYSQL_CONNECTION")
        ?? "Server=127.0.0.1;Port=3306;User=erp_user;Password=erp_secret;";

    private readonly string _connectionString = $"{BaseConnection}Database=erp_inventory_{Guid.NewGuid():N}_test;";

    protected override void ConfigureWebHost(IWebHostBuilder builder)
    {
        builder.ConfigureAppConfiguration((_, configuration) =>
        {
            configuration.AddInMemoryCollection(new Dictionary<string, string?>
            {
                ["ConnectionStrings:MySqlConnection"] = _connectionString,
                // Test chạy ở môi trường Development, không có secret trong appsettings.json (compose cấp ở runtime).
                ["Jwt:Secret"] = "integration-test-secret-not-used-in-production",
                // Database tạm còn trống: để ứng dụng tự nạp dữ liệu mẫu (kho, tồn kho) mà các test dựa vào.
                ["AUTO_SEED"] = "true"
            });
        });
    }

    protected override void Dispose(bool disposing)
    {
        base.Dispose(disposing);
        if (!disposing)
        {
            return;
        }

        var options = new DbContextOptionsBuilder<InventoryDbContext>()
            .UseMySql(_connectionString, new MySqlServerVersion(new Version(8, 0, 36)))
            .Options;
        using var context = new InventoryDbContext(options);
        context.Database.EnsureDeleted();
    }
}
