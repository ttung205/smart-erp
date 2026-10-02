using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Purchase.Infrastructure.Persistence;

namespace Purchase.Tests;

public static class TestDbContextFactory
{
    public static (PurchaseDbContext Context, SqliteConnection Connection) CreateInMemoryDbContext()
    {
        var connection = new SqliteConnection("Data Source=:memory:");
        connection.Open();

        var options = new DbContextOptionsBuilder<PurchaseDbContext>()
            .UseSqlite(connection)
            .Options;

        var context = new PurchaseDbContext(options);
        context.Database.EnsureCreated();

        return (context, connection);
    }
}
