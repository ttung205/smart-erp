using Inventory.Application.Common.Commands;
using Inventory.Domain.Entities;
using Inventory.Domain.Exceptions;
using Inventory.Infrastructure.Persistence;
using Inventory.Infrastructure.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Inventory.Infrastructure.Tests;

/// <summary>
/// Mô hình 2 tầng: inventory_stocks (tồn thực tế) và inventory_stocks_reservation (tồn khả dụng).
/// Giữ chỗ trừ tồn khả dụng; xuất kho trừ tồn thực tế.
/// </summary>
public class InventoryStockManagerTests : IDisposable
{
    private const long Warehouse = 1;

    private readonly SqliteConnection _connection;
    private readonly InventoryDbContext _context;
    private readonly InventoryStockManager _manager;

    public InventoryStockManagerTests()
    {
        _connection = new SqliteConnection("DataSource=:memory:");
        _connection.Open();

        var options = new DbContextOptionsBuilder<InventoryDbContext>().UseSqlite(_connection).Options;
        _context = new InventoryDbContext(options);
        _context.Database.EnsureCreated();
        _context.Warehouses.Add(new Warehouse { Id = Warehouse, Code = "WH-HN-01", Name = "Kho Tổng Hà Nội", Location = "Hà Nội", IsActive = true });
        _context.SaveChanges();

        _manager = new InventoryStockManager(_context);
    }

    public void Dispose()
    {
        _context.Dispose();
        _connection.Dispose();
    }

    private async Task SeedAsync(long productId, int onHand, int available)
    {
        _context.InventoryStocks.Add(new InventoryStock(Warehouse, productId, onHand));
        _context.InventoryStockReservations.Add(new InventoryStockReservation(Warehouse, productId, available));
        await _context.SaveChangesAsync();
        _context.ChangeTracker.Clear();
    }

    private Task<int> AvailableAsync(long productId) =>
        _context.InventoryStockReservations.AsNoTracking().Where(r => r.ProductId == productId).Select(r => r.Quantity).FirstAsync();

    private Task<int> OnHandAsync(long productId) =>
        _context.InventoryStocks.AsNoTracking().Where(s => s.ProductId == productId).Select(s => s.Quantity).FirstAsync();

    [Fact]
    public async Task Reserve_DecreasesAvailable_AndWritesLedger()
    {
        await SeedAsync(201, onHand: 50, available: 50);

        var result = await _manager.ReserveStockAsync(new ReserveStockCommand("INV-2026-000001", Warehouse, 201, 15, UserId: 99));

        Assert.Equal(35, result.QuantityReserved); // số dư khả dụng sau giữ
        Assert.Equal(-15, result.QuantityDelta);
        Assert.Equal(35, await AvailableAsync(201));
        Assert.Equal(50, await OnHandAsync(201)); // giữ chỗ không đụng tồn thực tế

        var ledger = await _context.InventoryStockReservationTransactions.AsNoTracking().SingleAsync(t => t.ProductId == 201);
        Assert.Equal("SALES_ORDER", ledger.DocumentType);
        Assert.Equal("INV-2026-000001", ledger.DocumentId);
        Assert.Equal(50, ledger.BalanceBefore);
        Assert.Equal(35, ledger.BalanceAfter);
        Assert.Equal(99, ledger.CreatedBy);
    }

    [Fact]
    public async Task Reserve_MoreThanAvailable_ThrowsInsufficientStock_AndChangesNothing()
    {
        await SeedAsync(202, onHand: 10, available: 10);

        var ex = await Assert.ThrowsAsync<InsufficientStockException>(
            () => _manager.ReserveStockAsync(new ReserveStockCommand("INV-2", Warehouse, 202, 15)));

        Assert.Equal("INSUFFICIENT_STOCK", ex.ErrorCode);
        Assert.Equal(15, ex.RequestedQuantity);
        Assert.Equal(10, ex.AvailableQuantity);
        Assert.Equal(10, await AvailableAsync(202));
        Assert.Empty(await _context.InventoryStockReservationTransactions.AsNoTracking().ToListAsync());
    }

    [Fact]
    public async Task BatchReserve_RollsBackWholeDocument_WhenAnyLineIsShort()
    {
        await SeedAsync(301, onHand: 20, available: 20);
        await SeedAsync(302, onHand: 5, available: 5);

        await Assert.ThrowsAsync<InsufficientStockException>(() => _manager.BatchReserveStockAsync(new BatchReserveStockCommand(
            "INV-BATCH-1",
            new List<ReserveStockItem> { new(Warehouse, 301, 10), new(Warehouse, 302, 15) })));

        Assert.Equal(20, await AvailableAsync(301)); // dòng đủ hàng cũng không được giữ
        Assert.Equal(5, await AvailableAsync(302));
        Assert.Empty(await _context.InventoryStockReservationTransactions.AsNoTracking().Where(t => t.DocumentId == "INV-BATCH-1").ToListAsync());
    }

    [Fact]
    public async Task BatchReserve_MergesLinesOfSameProduct()
    {
        await SeedAsync(303, onHand: 10, available: 10);

        var results = await _manager.BatchReserveStockAsync(new BatchReserveStockCommand(
            "INV-MERGE", new List<ReserveStockItem> { new(Warehouse, 303, 3), new(Warehouse, 303, 4) }));

        Assert.Single(results);
        Assert.Equal(-7, results[0].QuantityDelta);
        Assert.Equal(3, await AvailableAsync(303));
    }

    [Fact]
    public async Task BatchReserve_IsIdempotent_AndCompletesPartiallyReservedDocument()
    {
        await SeedAsync(401, onHand: 50, available: 50);
        await SeedAsync(402, onHand: 50, available: 50);
        // Chứng từ cũ đã giữ được dòng 401 (giữ từng dòng trước đây), dòng 402 thất bại
        await _manager.ReserveStockAsync(new ReserveStockCommand("INV-RETRY", Warehouse, 401, 10));

        var command = new BatchReserveStockCommand("INV-RETRY", new List<ReserveStockItem> { new(Warehouse, 401, 10), new(Warehouse, 402, 20) });
        await _manager.BatchReserveStockAsync(command);
        await _manager.BatchReserveStockAsync(command); // gọi lại lần nữa

        Assert.Equal(40, await AvailableAsync(401)); // chỉ trừ một lần
        Assert.Equal(30, await AvailableAsync(402));
        Assert.Equal(2, await _context.InventoryStockReservationTransactions.CountAsync(t => t.DocumentId == "INV-RETRY"));
    }

    [Fact]
    public async Task Reserve_SameDocumentWithDifferentQuantity_IsRejected()
    {
        await SeedAsync(403, onHand: 50, available: 50);
        await _manager.ReserveStockAsync(new ReserveStockCommand("INV-DIFF", Warehouse, 403, 10));

        var ex = await Assert.ThrowsAsync<InvalidStockOperationException>(
            () => _manager.ReserveStockAsync(new ReserveStockCommand("INV-DIFF", Warehouse, 403, 12)));

        Assert.Equal("DOCUMENT_ALREADY_POSTED", ex.ErrorCode);
        Assert.Equal(40, await AvailableAsync(403));
    }

    [Fact]
    public async Task SecondDocument_CannotReserve_WhenFirstTookTheStock_UntilFirstIsCancelled()
    {
        // Kho còn 3; hai yêu cầu giao mỗi cái 2
        await SeedAsync(450, onHand: 3, available: 3);
        await _manager.ReserveStockAsync(new ReserveStockCommand("INV-A", Warehouse, 450, 2));

        var ex = await Assert.ThrowsAsync<InsufficientStockException>(
            () => _manager.ReserveStockAsync(new ReserveStockCommand("INV-B", Warehouse, 450, 2)));
        Assert.Equal(1, ex.AvailableQuantity);

        // Hủy chứng từ A → nhả đúng 2, gọi lại không nhả thêm
        await _manager.ReleaseDocumentReservationAsync("INV-A");
        await _manager.ReleaseDocumentReservationAsync("INV-A");
        Assert.Equal(3, await AvailableAsync(450));

        await _manager.ReserveStockAsync(new ReserveStockCommand("INV-B", Warehouse, 450, 2));
        Assert.Equal(1, await AvailableAsync(450));
    }

    [Fact]
    public async Task ReleaseDocument_WithoutReservation_DoesNothing()
    {
        Assert.Empty(await _manager.ReleaseDocumentReservationAsync("INV-NEVER-RESERVED"));
    }

    [Fact]
    public async Task Release_IncreasesAvailable()
    {
        await SeedAsync(501, onHand: 50, available: 30);

        var result = await _manager.ReleaseStockAsync(new ReleaseStockCommand("INV-501", Warehouse, 501, 10));

        Assert.Equal(40, result.QuantityReserved);
        Assert.Equal(40, await AvailableAsync(501));
    }

    [Fact]
    public async Task Receive_IncreasesOnHandAndAvailable_ForNewProduct()
    {
        var result = await _manager.ReceiveStockAsync(new ReceiveStockCommand("PO-2026-0088", Warehouse, 601, 100, MinStock: 0));

        Assert.Equal(100, result.QuantityOnHand);
        Assert.Equal(100, await OnHandAsync(601));
        Assert.Equal(100, await AvailableAsync(601));
    }

    [Fact]
    public async Task Issue_DecreasesOnHand_AndRejectsShortage()
    {
        await SeedAsync(701, onHand: 40, available: 25); // 15 đã giữ

        var result = await _manager.IssueStockAsync(new IssueStockCommand("INV-701:1", Warehouse, 701, 15, "SO-701"));

        Assert.Equal(25, result.QuantityOnHand);
        Assert.Equal(25, await OnHandAsync(701));
        Assert.Equal(25, await AvailableAsync(701)); // khả dụng đã trừ lúc giữ chỗ

        await Assert.ThrowsAsync<InsufficientStockException>(
            () => _manager.IssueStockAsync(new IssueStockCommand("INV-701:2", Warehouse, 701, 26, "SO-701")));
        Assert.Equal(25, await OnHandAsync(701));
    }
}
