using Microsoft.EntityFrameworkCore;
using Purchase.Infrastructure.Persistence;

namespace Purchase.Tests;

/// <summary>
/// Database MySQL tạm cho một test: tên erp_purchase_{guid}_test (khớp quyền `erp\_%\_test` của erp_user),
/// được xóa khi Dispose. Kết nối mặc định tới MySQL của docker compose; CI đặt TEST_MYSQL_CONNECTION.
/// </summary>
public sealed class TestDatabase : IDisposable
{
    private static readonly string BaseConnection = Environment.GetEnvironmentVariable("TEST_MYSQL_CONNECTION")
        ?? "Server=127.0.0.1;Port=3306;User=erp_user;Password=erp_secret;TreatTinyAsBoolean=true;";

    private readonly DbContextOptions<PurchaseDbContext> _options;

    public TestDatabase()
    {
        var name = $"erp_purchase_{Guid.NewGuid():N}_test";
        _options = new DbContextOptionsBuilder<PurchaseDbContext>()
            .UseMySql($"{BaseConnection}Database={name};", new MySqlServerVersion(new Version(8, 0, 36)))
            .Options;
    }

    public PurchaseDbContext CreateContext() => new(_options);

    public void Dispose()
    {
        using var context = CreateContext();
        context.Database.EnsureDeleted();
    }
}

public static class TestDbContextFactory
{
    public static (PurchaseDbContext Context, TestDatabase Connection) CreateInMemoryDbContext()
    {
        var database = new TestDatabase();
        var context = database.CreateContext();
        context.Database.EnsureCreated();

        return (context, database);
    }
}
