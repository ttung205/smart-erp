using Inventory.Application.Common.Commands;
using Inventory.Domain.Entities;
using Inventory.Domain.Exceptions;
using Inventory.Infrastructure.Persistence;
using Inventory.Infrastructure.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Inventory.Infrastructure.Tests;

public class InventoryStockManagerTests : IDisposable
{
    private readonly SqliteConnection _connection;
    private readonly InventoryDbContext _context;
    private readonly InventoryStockManager _manager;

    public InventoryStockManagerTests()
    {
        _connection = new SqliteConnection("DataSource=:memory:");
        _connection.Open();

        var options = new DbContextOptionsBuilder<InventoryDbContext>()
            .UseSqlite(_connection)
            .Options;

        _context = new InventoryDbContext(options);
        _context.Database.EnsureCreated();

        // Seed Warehouse
        _context.Warehouses.Add(new Warehouse
        {
            Id = 1,
            Code = "WH-HN-01",
            Name = "Kho Tổng Hà Nội",
            Location = "Hà Nội",
            IsActive = true
        });
        _context.SaveChanges();

        _manager = new InventoryStockManager(_context);
    }

    public void Dispose()
    {
        _context.Dispose();
        _connection.Dispose();
    }

    [Fact]
    public async Task ReserveStock_ShouldUpdateReserved_And_CreateStockMovement()
    {
        // Arrange: Sản phẩm có OnHand = 50, Reserved = 0
        _context.InventoryStocks.Add(new InventoryStock(productId: 201, warehouseId: 1, initialOnHand: 50));
        await _context.SaveChangesAsync();

        var command = new ReserveStockCommand(
            OrderId: "SO-2026-0001",
            WarehouseId: 1,
            ProductId: 201,
            Quantity: 15,
            UserId: 99
        );

        // Act
        var result = await _manager.ReserveStockAsync(command);

        // Assert
        Assert.Equal(50, result.QuantityOnHand);
        Assert.Equal(15, result.QuantityReserved);
        Assert.Equal(35, result.Available);
        Assert.Equal("SO_RESERVE", result.OperationType);

        // Kiểm tra Sổ cái bất biến (Immutable Ledger)
        var movement = await _context.StockMovements
            .FirstOrDefaultAsync(m => m.ProductId == 201 && m.WarehouseId == 1 && m.MovementType == "SO_RESERVE");

        Assert.NotNull(movement);
        Assert.Equal(15, movement.QuantityDelta);
        Assert.Equal(50, movement.BalanceOnHandAfter);
        Assert.Equal(15, movement.BalanceReservedAfter);
        Assert.Equal("SO-2026-0001", movement.ReferenceDoc);
    }

    [Fact]
    public async Task ReserveStock_ZeroOverselling_ShouldThrowInsufficientStockException_WhenRequestExceedsAvailable()
    {
        // Arrange: Có 10 món
        _context.InventoryStocks.Add(new InventoryStock(productId: 202, warehouseId: 1, initialOnHand: 10));
        await _context.SaveChangesAsync();

        var command = new ReserveStockCommand(
            OrderId: "SO-2026-0002",
            WarehouseId: 1,
            ProductId: 202,
            Quantity: 15 // Đòi 15 khi chỉ có 10
        );

        // Act & Assert
        var ex = await Assert.ThrowsAsync<InsufficientStockException>(() => _manager.ReserveStockAsync(command));
        Assert.Equal("INSUFFICIENT_STOCK", ex.ErrorCode);
        Assert.Equal(15, ex.RequestedQuantity);
        Assert.Equal(10, ex.AvailableQuantity);

        // Đảm bảo số tồn trong DB không bị thay đổi (Zero Overselling & Không rò rỉ trạng thái)
        var stock = await _context.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == 202);
        Assert.Equal(10, stock.QuantityOnHand);
        Assert.Equal(0, stock.QuantityReserved);
    }

    [Fact]
    public async Task BatchReserveStock_ShouldRollbackAll_WhenAnyItemFails()
    {
        // Arrange: SP 301 có 20, SP 302 chỉ có 5
        _context.InventoryStocks.Add(new InventoryStock(productId: 301, warehouseId: 1, initialOnHand: 20));
        _context.InventoryStocks.Add(new InventoryStock(productId: 302, warehouseId: 1, initialOnHand: 5));
        await _context.SaveChangesAsync();

        var batchCmd = new BatchReserveStockCommand(
            OrderId: "SO-BATCH-001",
            Items: new List<ReserveStockItem>
            {
                new(WarehouseId: 1, ProductId: 301, Quantity: 10), // Đủ
                new(WarehouseId: 1, ProductId: 302, Quantity: 15)  // Thiếu (cần 15 mà chỉ có 5)
            }
        );

        // Act & Assert: Phải ném ngoại lệ và ROLLBACK toàn bộ
        await Assert.ThrowsAsync<InsufficientStockException>(() => _manager.BatchReserveStockAsync(batchCmd));

        // Kiểm tra tính nguyên tử (Atomicity): SP 301 KHÔNG ĐƯỢC PHÉP giữ chỗ khi cả lô thất bại
        var stock301 = await _context.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == 301);
        Assert.Equal(0, stock301.QuantityReserved);
        Assert.Equal(20, stock301.Available);

        var movements = await _context.StockMovements.Where(m => m.ReferenceDoc == "SO-BATCH-001").ToListAsync();
        Assert.Empty(movements);
    }

    [Fact]
    public async Task ReleaseStock_ShouldDecreaseReserved_And_LogStockMovement()
    {
        // Arrange
        var stock = new InventoryStock(productId: 401, warehouseId: 1, initialOnHand: 50);
        stock.Reserve(20);
        _context.InventoryStocks.Add(stock);

        _context.StockReservations.Add(new StockReservation
        {
            OrderId = "SO-401",
            WarehouseId = 1,
            ProductId = 401,
            Quantity = 20,
            Status = "ACTIVE"
        });
        await _context.SaveChangesAsync();

        var releaseCmd = new ReleaseStockCommand(
            OrderId: "SO-401",
            WarehouseId: 1,
            ProductId: 401,
            Quantity: 10
        );

        // Act
        var result = await _manager.ReleaseStockAsync(releaseCmd);

        // Assert
        Assert.Equal(50, result.QuantityOnHand);
        Assert.Equal(10, result.QuantityReserved);
        Assert.Equal(40, result.Available);

        var movement = await _context.StockMovements
            .FirstOrDefaultAsync(m => m.ProductId == 401 && m.MovementType == "SO_RELEASE");

        Assert.NotNull(movement);
        Assert.Equal(-10, movement.QuantityDelta);
        Assert.Equal(10, movement.BalanceReservedAfter);
    }

    [Fact]
    public async Task ReceiveStock_ShouldIncreaseOnHand_And_LogMovement()
    {
        // Arrange: Hàng mới nhập kho lần đầu
        var receiveCmd = new ReceiveStockCommand(
            ReferenceDoc: "PO-2026-0088",
            WarehouseId: 1,
            ProductId: 501,
            Quantity: 100,
            MinStock: 20
        );

        // Act
        var result = await _manager.ReceiveStockAsync(receiveCmd);

        // Assert
        Assert.Equal(100, result.QuantityOnHand);
        Assert.Equal(0, result.QuantityReserved);
        Assert.Equal(100, result.Available);

        var stockInDb = await _context.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == 501);
        Assert.Equal(100, stockInDb.QuantityOnHand);
        Assert.Equal(20, stockInDb.MinStock);

        var movement = await _context.StockMovements
            .FirstOrDefaultAsync(m => m.ProductId == 501 && m.MovementType == "PO_RECEIPT");
        Assert.NotNull(movement);
        Assert.Equal(100, movement.QuantityDelta);
        Assert.Equal(100, movement.BalanceOnHandAfter);
    }

    [Fact]
    public async Task IssueStock_ShouldDecreaseOnHandAndReserved_And_LogMovement()
    {
        // Arrange: Có 40 món đã giữ 15 món cho SO-601
        var stock = new InventoryStock(productId: 601, warehouseId: 1, initialOnHand: 40);
        stock.Reserve(15);
        _context.InventoryStocks.Add(stock);

        _context.StockReservations.Add(new StockReservation
        {
            OrderId = "SO-601",
            WarehouseId = 1,
            ProductId = 601,
            Quantity = 15,
            Status = "ACTIVE"
        });
        await _context.SaveChangesAsync();

        var issueCmd = new IssueStockCommand(
            ReferenceDoc: "GI-2026-001",
            WarehouseId: 1,
            ProductId: 601,
            Quantity: 15,
            OrderId: "SO-601"
        );

        // Act
        var result = await _manager.IssueStockAsync(issueCmd);

        // Assert
        Assert.Equal(25, result.QuantityOnHand);
        Assert.Equal(0, result.QuantityReserved);
        Assert.Equal(25, result.Available);

        // Kiểm tra reservation được chuyển thành FULFILLED
        var reservation = await _context.StockReservations.FirstAsync(r => r.OrderId == "SO-601");
        Assert.Equal("FULFILLED", reservation.Status);

        // Kiểm tra thẻ kho ghi nhận xuất hàng
        var movement = await _context.StockMovements
            .FirstOrDefaultAsync(m => m.ProductId == 601 && m.MovementType == "SO_ISSUE");
        Assert.NotNull(movement);
        Assert.Equal(-15, movement.QuantityDelta);
        Assert.Equal(25, movement.BalanceOnHandAfter);
        Assert.Equal(0, movement.BalanceReservedAfter);
    }
}
