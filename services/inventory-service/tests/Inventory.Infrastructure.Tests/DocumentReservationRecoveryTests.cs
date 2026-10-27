using System.Collections.Concurrent;
using Inventory.Application.Common.Commands;
using Inventory.Domain.Entities;
using Inventory.Infrastructure.Persistence;
using Inventory.Infrastructure.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Inventory.Infrastructure.Tests;

/// <summary>
/// Kịch bản "Inventory đã giữ hàng nhưng Sales timeout": Sales gọi lại khi lần đầu chưa trả lời không được giữ
/// hàng hai lần, và Sales liệt kê được chứng từ đang giữ hàng để đối soát / nhả hàng bị treo.
/// </summary>
public class DocumentReservationRecoveryTests : IDisposable
{
    private const long WarehouseId = 1;
    private const long ProductId = 501;
    private readonly string _dbPath;
    private readonly DbContextOptions<InventoryDbContext> _dbOptions;

    public DocumentReservationRecoveryTests()
    {
        _dbPath = Path.Combine(Path.GetTempPath(), $"inv_recovery_test_{Guid.NewGuid():N}.db");
        _dbOptions = new DbContextOptionsBuilder<InventoryDbContext>()
            .UseSqlite($"Data Source={_dbPath};Default Timeout=30")
            .Options;

        using var context = new InventoryDbContext(_dbOptions);
        context.Database.EnsureCreated();
        context.Database.ExecuteSqlRaw("PRAGMA journal_mode=WAL;");
        context.Warehouses.Add(new Warehouse { Id = WarehouseId, Code = "WH-01", Name = "Kho 1", Location = "HN", IsActive = true });
        context.InventoryStocks.Add(new InventoryStock(WarehouseId, ProductId, 100));
        context.InventoryStockReservations.Add(new InventoryStockReservation(WarehouseId, ProductId, 100));
        context.SaveChanges();
    }

    public void Dispose()
    {
        try
        {
            SqliteConnection.ClearAllPools();
            File.Delete(_dbPath);
        }
        catch
        {
            // Bỏ qua lỗi xóa file tạm
        }
    }

    private static BatchReserveStockCommand Reserve(string documentId, int quantity) =>
        new(documentId, [new ReserveStockItem(WarehouseId, ProductId, quantity)], null);

    private async Task<int> AvailableAsync()
    {
        using var context = new InventoryDbContext(_dbOptions);
        return await context.InventoryStockReservations.AsNoTracking()
            .Where(r => r.WarehouseId == WarehouseId && r.ProductId == ProductId)
            .Select(r => r.Quantity)
            .FirstAsync();
    }

    [Fact]
    public async Task Parallel_retries_of_the_same_document_reserve_only_once_and_never_fail()
    {
        var errors = new ConcurrentBag<Exception>();
        var tasks = Enumerable.Range(0, 5).Select(async _ =>
        {
            using var context = new InventoryDbContext(_dbOptions);
            try
            {
                await new InventoryStockManager(context).BatchReserveStockAsync(Reserve("INV-RETRY-1", 7));
            }
            catch (Exception ex)
            {
                errors.Add(ex);
            }
        });
        await Task.WhenAll(tasks);

        Assert.Empty(errors);
        Assert.Equal(93, await AvailableAsync());
        using var verify = new InventoryDbContext(_dbOptions);
        Assert.Equal(1, await verify.InventoryStockReservationTransactions.CountAsync(t => t.DocumentId == "INV-RETRY-1"));
    }

    [Fact]
    public async Task Open_document_reservations_exclude_released_documents()
    {
        using (var context = new InventoryDbContext(_dbOptions))
        {
            var manager = new InventoryStockManager(context);
            await manager.BatchReserveStockAsync(Reserve("INV-OPEN", 3));
            await manager.BatchReserveStockAsync(Reserve("INV-RELEASED", 4));
            await manager.ReleaseDocumentReservationAsync("INV-RELEASED");
        }

        using var readContext = new InventoryDbContext(_dbOptions);
        var open = await new InventoryStockManager(readContext)
            .GetOpenDocumentReservationsAsync(DateTime.UtcNow.AddHours(-1), DateTime.UtcNow.AddMinutes(1));

        var document = Assert.Single(open);
        Assert.Equal("INV-OPEN", document.DocumentId);
        var line = Assert.Single(document.Lines);
        Assert.Equal((WarehouseId, ProductId, 3), (line.WarehouseId, line.ProductId, line.Quantity));
        Assert.Equal(97, await AvailableAsync());
    }

    [Fact]
    public async Task Open_document_reservations_respect_the_time_window()
    {
        using (var context = new InventoryDbContext(_dbOptions))
        {
            await new InventoryStockManager(context).BatchReserveStockAsync(Reserve("INV-JUST-NOW", 2));
        }

        using var readContext = new InventoryDbContext(_dbOptions);
        var open = await new InventoryStockManager(readContext)
            .GetOpenDocumentReservationsAsync(DateTime.UtcNow.AddDays(-7), DateTime.UtcNow.AddMinutes(-5));

        Assert.Empty(open);
    }
}
