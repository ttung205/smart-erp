using Inventory.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Infrastructure.Tests;

/// <summary>
/// Database MySQL tạm cho một test: tên erp_inventory_{guid}_test (khớp quyền `erp\_%\_test` của erp_user),
/// được tạo schema khi khởi tạo và xóa khi Dispose. CI đặt TEST_MYSQL_CONNECTION.
/// </summary>
public sealed class TestDatabase : IDisposable
{
    private static readonly string BaseConnection = Environment.GetEnvironmentVariable("TEST_MYSQL_CONNECTION")
        ?? "Server=127.0.0.1;Port=3306;User=erp_user;Password=erp_secret;";

    public TestDatabase()
    {
        var name = $"erp_inventory_{Guid.NewGuid():N}_test";
        Options = new DbContextOptionsBuilder<InventoryDbContext>()
            .UseMySql($"{BaseConnection}Database={name};", new MySqlServerVersion(new Version(8, 0, 36)))
            .Options;

        using var context = CreateContext();
        context.Database.EnsureCreated();
    }

    public DbContextOptions<InventoryDbContext> Options { get; }

    public InventoryDbContext CreateContext() => new(Options);

    public void Dispose()
    {
        using var context = CreateContext();
        context.Database.EnsureDeleted();
    }
}
