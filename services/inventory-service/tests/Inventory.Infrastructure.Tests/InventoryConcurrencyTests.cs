using System.Collections.Concurrent;
using Inventory.Application.Common.Commands;
using Inventory.Domain.Entities;
using Inventory.Domain.Exceptions;
using Inventory.Infrastructure.Persistence;
using Inventory.Infrastructure.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Inventory.Infrastructure.Tests;

public class InventoryConcurrencyTests : IDisposable
{
    private readonly string _dbPath;
    private readonly string _connectionString;
    private readonly DbContextOptions<InventoryDbContext> _dbOptions;

    public InventoryConcurrencyTests()
    {
        _dbPath = Path.Combine(Path.GetTempPath(), $"inv_concurrency_test_{Guid.NewGuid():N}.db");
        _connectionString = $"Data Source={_dbPath};Default Timeout=30";

        _dbOptions = new DbContextOptionsBuilder<InventoryDbContext>()
            .UseSqlite(_connectionString)
            .Options;

        // Khởi tạo Database và bật WAL mode để hỗ trợ truy cập đa luồng tối ưu
        using var initContext = new InventoryDbContext(_dbOptions);
        initContext.Database.EnsureCreated();
        initContext.Database.ExecuteSqlRaw("PRAGMA journal_mode=WAL;");

        // Seed Warehouse 1
        initContext.Warehouses.Add(new Warehouse
        {
            Id = 1,
            Code = "WH-HN-01",
            Name = "Kho Tổng Hà Nội",
            Location = "Hà Nội",
            IsActive = true
        });
        initContext.SaveChanges();
    }

    public void Dispose()
    {
        try
        {
            if (File.Exists(_dbPath))
            {
                SqliteConnection.ClearAllPools();
                File.Delete(_dbPath);
            }
        }
        catch
        {
            // Bỏ qua lỗi xóa file tạm nếu còn process giữ handle
        }
    }

    [Fact]
    public async Task ConcurrencyTest_20ParallelTasks_OnlyOneSucceeds_ZeroOverselling()
    {
        // -------------------------------------------------------------
        // GIẢ LẬP KỊCH BẢN CHẶNG 6.1 (ROADMAP):
        // 20 luồng đồng thời cùng xin giữ 10 món hàng khi trong kho chỉ còn 15 món.
        // Kỳ vọng: Đúng 1 đơn giữ thành công 10 món, 19 đơn bị từ chối INSUFFICIENT_STOCK.
        // Số tồn không bao giờ bị âm (Available >= 0).
        // -------------------------------------------------------------

        const long productId = 999;
        const long warehouseId = 1;
        const int initialOnHand = 15;
        const int requestedQty = 10;
        const int totalTasks = 20;

        // 1. Arrange: Nạp kho ban đầu
        using (var setupContext = new InventoryDbContext(_dbOptions))
        {
            setupContext.InventoryStocks.Add(new InventoryStock(productId, warehouseId, initialOnHand));
            await setupContext.SaveChangesAsync();
        }

        var successOrders = new ConcurrentBag<string>();
        var rejectedErrors = new ConcurrentBag<string>();
        var unexpectedExceptions = new ConcurrentBag<Exception>();

        // 2. Act: Bắn 20 Task song song đồng thời cùng 1 thời điểm
        var tasks = Enumerable.Range(1, totalTasks).Select(async i =>
        {
            // Mỗi thread sở hữu 1 DbContext và 1 InventoryStockManager riêng biệt
            using var taskContext = new InventoryDbContext(_dbOptions);
            var manager = new InventoryStockManager(taskContext);

            var cmd = new ReserveStockCommand(
                OrderId: $"SO-CONCURRENT-{i:D3}",
                WarehouseId: warehouseId,
                ProductId: productId,
                Quantity: requestedQty
            );

            try
            {
                var result = await manager.ReserveStockAsync(cmd);
                successOrders.Add(result.ReferenceDoc);
            }
            catch (InsufficientStockException ex)
            {
                rejectedErrors.Add(ex.ErrorCode);
            }
            catch (Exception ex)
            {
                unexpectedExceptions.Add(ex);
            }
        });

        await Task.WhenAll(tasks);

        // 3. Assert: Kiểm tra tính nguyên tử (Atomicity & Zero Overselling)
        Assert.Empty(unexpectedExceptions);
        Assert.Single(successOrders); // ĐÚNG DUY NHẤT 1 ĐƠN THÀNH CÔNG
        Assert.Equal(totalTasks - 1, rejectedErrors.Count); // 19 ĐƠN BỊ TỪ CHỐI
        Assert.All(rejectedErrors, code => Assert.Equal("INSUFFICIENT_STOCK", code));

        // Kiểm tra số dư cuối cùng trong Database
        using (var verifyContext = new InventoryDbContext(_dbOptions))
        {
            var finalStock = await verifyContext.InventoryStocks
                .AsNoTracking()
                .FirstAsync(s => s.ProductId == productId && s.WarehouseId == warehouseId);

            Assert.Equal(initialOnHand, finalStock.QuantityOnHand); // 15
            Assert.Equal(requestedQty, finalStock.QuantityReserved); // 10
            Assert.Equal(5, finalStock.Available); // 15 - 10 = 5

            // Bất biến: Available tuyệt đối không âm
            Assert.True(finalStock.Available >= 0, "LỖI OBERSELLING: Tồn khả dụng bị âm!");

            // Kiểm tra số lượng phiếu giữ chỗ thực tế
            var reservations = await verifyContext.StockReservations
                .Where(r => r.ProductId == productId)
                .ToListAsync();

            Assert.Single(reservations);
            Assert.Equal(requestedQty, reservations[0].Quantity);
        }
    }

    [Fact]
    public async Task ConcurrencyTest_ResourceLockOrdering_PreventsDeadlock_BetweenOppositeSequences()
    {
        // -------------------------------------------------------------
        // GIẢ LẬP KỊCH BẢN CHẶNG 6.2 (ROADMAP):
        // 2 Transaction xin giữ hàng chéo nhau [A, B] và [B, A] song song.
        // Nhờ Resource Lock Ordering (.ToDeterministicLockOrder()), cả hai đều được
        // chuẩn hóa thứ tự khóa [A, B] trước khi thực thi -> Hoàn thành không deadlock.
        // -------------------------------------------------------------

        const long prodA = 801;
        const long prodB = 802;
        const long warehouseId = 1;

        // 1. Arrange: Nạp kho ban đầu 100 món cho cả 2 sản phẩm
        using (var setupContext = new InventoryDbContext(_dbOptions))
        {
            setupContext.InventoryStocks.Add(new InventoryStock(prodA, warehouseId, 100));
            setupContext.InventoryStocks.Add(new InventoryStock(prodB, warehouseId, 100));
            await setupContext.SaveChangesAsync();
        }

        // Kịch bản chéo:
        // Tx1: Xin giữ [A: 10, B: 20]
        var batchCmd1 = new BatchReserveStockCommand(
            OrderId: "SO-DEADLOCK-001",
            Items: new List<ReserveStockItem>
            {
                new(warehouseId, prodA, 10),
                new(warehouseId, prodB, 20)
            }
        );

        // Tx2: Cố tình xin theo thứ tự ngược lại [B: 15, A: 5]
        var batchCmd2 = new BatchReserveStockCommand(
            OrderId: "SO-DEADLOCK-002",
            Items: new List<ReserveStockItem>
            {
                new(warehouseId, prodB, 15),
                new(warehouseId, prodA, 5)
            }
        );

        // 2. Act: Chạy đồng thời 2 Transaction đối nghịch
        var task1 = Task.Run(async () =>
        {
            using var ctx1 = new InventoryDbContext(_dbOptions);
            var mgr1 = new InventoryStockManager(ctx1);
            return await mgr1.BatchReserveStockAsync(batchCmd1);
        });

        var task2 = Task.Run(async () =>
        {
            using var ctx2 = new InventoryDbContext(_dbOptions);
            var mgr2 = new InventoryStockManager(ctx2);
            return await mgr2.BatchReserveStockAsync(batchCmd2);
        });

        var allResults = await Task.WhenAll(task1, task2);

        // 3. Assert: Cả 2 đều hoàn tất thành công, không gặp SQL Deadlock
        Assert.NotNull(allResults[0]);
        Assert.NotNull(allResults[1]);
        Assert.Equal(2, allResults[0].Count);
        Assert.Equal(2, allResults[1].Count);

        // Kiểm tra số dư cuối cùng
        using (var verifyContext = new InventoryDbContext(_dbOptions))
        {
            var stockA = await verifyContext.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == prodA);
            var stockB = await verifyContext.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == prodB);

            // Prod A: 10 (từ Tx1) + 5 (từ Tx2) = 15 reserved
            Assert.Equal(15, stockA.QuantityReserved);
            Assert.Equal(85, stockA.Available);

            // Prod B: 20 (từ Tx1) + 15 (từ Tx2) = 35 reserved
            Assert.Equal(35, stockB.QuantityReserved);
            Assert.Equal(65, stockB.Available);
        }
    }
}
