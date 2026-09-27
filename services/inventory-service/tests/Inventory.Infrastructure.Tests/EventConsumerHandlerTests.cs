using System.Text.Json;
using Inventory.Application.Common.Events;
using Inventory.Domain.Entities;
using Inventory.Infrastructure.Persistence;
using Inventory.Infrastructure.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace Inventory.Infrastructure.Tests;

public class EventConsumerHandlerTests : IDisposable
{
    private readonly SqliteConnection _connection;
    private readonly InventoryDbContext _context;
    private readonly InventoryStockManager _stockManager;
    private readonly OutboxService _outboxService;
    private readonly EventConsumerHandler _handler;

    public EventConsumerHandlerTests()
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
            IsActive = true
        });
        _context.SaveChanges();

        _stockManager = new InventoryStockManager(_context);
        _outboxService = new OutboxService(_context);
        _handler = new EventConsumerHandler(
            _context,
            _stockManager,
            _outboxService,
            NullLogger<EventConsumerHandler>.Instance);
    }

    public void Dispose()
    {
        _context.Dispose();
        _connection.Dispose();
    }

    [Fact]
    public async Task HandleSalesOrderCreated_WithSufficientStock_ShouldReserveAndEmitStockReserved()
    {
        // Arrange: Có 50 cái sản phẩm 101 trong kho
        _context.InventoryStocks.Add(new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 50));
        await _context.SaveChangesAsync();

        var payload = new SalesOrderCreatedPayload
        {
            OrderId = "SO-SAGA-001",
            CustomerId = 99,
            TotalAmount = 70000000,
            Items = new List<SalesOrderItemPayload>
            {
                new()
                {
                    ProductId = 101,
                    WarehouseId = 1,
                    Quantity = 5,
                    UnitPrice = 14000000
                }
            }
        };

        var envelope = new CloudEventEnvelope<SalesOrderCreatedPayload>(
            eventType: "SalesOrderCreated",
            aggregateType: "SalesOrder",
            aggregateId: "SO-SAGA-001",
            payload: payload,
            correlationId: "req_saga_001"
        )
        {
            EventId = "evt_sales_order_001"
        };

        // Act
        await _handler.HandleSalesOrderCreatedAsync(envelope);

        // Assert 1: Tồn kho đã được giữ chỗ
        var stock = await _context.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == 101);
        Assert.Equal(50, stock.QuantityOnHand);
        Assert.Equal(5, stock.QuantityReserved);
        Assert.Equal(45, stock.Available);

        // Assert 2: Outbox đã ghi nhận StockReserved
        var outboxEvent = await _context.OutboxEvents
            .FirstOrDefaultAsync(e => e.EventType == "StockReserved" && e.AggregateId == "SO-SAGA-001");

        Assert.NotNull(outboxEvent);
        Assert.Equal("inventory.stock.reserved", outboxEvent.RoutingKey);
        Assert.Equal("PENDING", outboxEvent.Status);
        Assert.Contains("StockReserved", outboxEvent.Payload);

        // Assert 3: Đã ghi nhận Idempotency vào processed_events
        var processed = await _context.ProcessedEvents
            .FirstOrDefaultAsync(p => p.EventId == "evt_sales_order_001" && p.ConsumerName == "inventory-service");
        Assert.NotNull(processed);
    }

    [Fact]
    public async Task HandleSalesOrderCreated_WithInsufficientStock_ShouldEmitStockReservationFailed()
    {
        // Arrange: Chỉ có 5 cái trong kho nhưng đơn hàng xin 100 cái
        _context.InventoryStocks.Add(new InventoryStock(productId: 102, warehouseId: 1, initialOnHand: 5));
        await _context.SaveChangesAsync();

        var payload = new SalesOrderCreatedPayload
        {
            OrderId = "SO-SAGA-FAIL-001",
            CustomerId = 99,
            TotalAmount = 1400000000,
            Items = new List<SalesOrderItemPayload>
            {
                new()
                {
                    ProductId = 102,
                    WarehouseId = 1,
                    Quantity = 100 // Vượt tồn
                }
            }
        };

        var envelope = new CloudEventEnvelope<SalesOrderCreatedPayload>(
            eventType: "SalesOrderCreated",
            aggregateType: "SalesOrder",
            aggregateId: "SO-SAGA-FAIL-001",
            payload: payload
        )
        {
            EventId = "evt_sales_order_fail_001"
        };

        // Act
        await _handler.HandleSalesOrderCreatedAsync(envelope);

        // Assert 1: Tồn kho không bị trừ (Zero Overselling)
        var stock = await _context.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == 102);
        Assert.Equal(5, stock.QuantityOnHand);
        Assert.Equal(0, stock.QuantityReserved);

        // Assert 2: Outbox đã ghi nhận StockReservationFailed để Sales-Service bồi hoàn
        var outboxEvent = await _context.OutboxEvents
            .FirstOrDefaultAsync(e => e.EventType == "StockReservationFailed" && e.AggregateId == "SO-SAGA-FAIL-001");

        Assert.NotNull(outboxEvent);
        Assert.Equal("inventory.stock.reservation_failed", outboxEvent.RoutingKey);
        Assert.Equal("PENDING", outboxEvent.Status);
        Assert.Contains("INSUFFICIENT_STOCK", outboxEvent.Payload);
    }

    [Fact]
    public async Task HandleSalesOrderCreated_DuplicateMessage_ShouldBeIgnored_Idempotency()
    {
        // Arrange
        _context.InventoryStocks.Add(new InventoryStock(productId: 103, warehouseId: 1, initialOnHand: 20));
        await _context.SaveChangesAsync();

        var envelope = new CloudEventEnvelope<SalesOrderCreatedPayload>(
            eventType: "SalesOrderCreated",
            aggregateType: "SalesOrder",
            aggregateId: "SO-DUP-001",
            payload: new SalesOrderCreatedPayload
            {
                OrderId = "SO-DUP-001",
                Items = new List<SalesOrderItemPayload>
                {
                    new() { ProductId = 103, WarehouseId = 1, Quantity = 5 }
                }
            }
        )
        {
            EventId = "evt_duplicate_test_123"
        };

        // Act: Nhận message lần 1
        await _handler.HandleSalesOrderCreatedAsync(envelope);

        // Act: Nhận lại chính message đó lần 2 (At-least-once simulation)
        await _handler.HandleSalesOrderCreatedAsync(envelope);

        // Assert: Số lượng chỉ bị giữ đúng 1 lần (Reserved = 5 chứ không phải 10)
        var stock = await _context.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == 103);
        Assert.Equal(5, stock.QuantityReserved);
        Assert.Equal(15, stock.Available);

        // Outbox chỉ có đúng 1 bản ghi
        var outboxCount = await _context.OutboxEvents.CountAsync(e => e.AggregateId == "SO-DUP-001");
        Assert.Equal(1, outboxCount);
    }

    [Fact]
    public async Task HandleGoodsReceived_ShouldIncreaseOnHand_AndLogStockMovement()
    {
        // Arrange
        var payload = new GoodsReceivedPayload
        {
            ReceiptId = "GR-2026-PO-001",
            PoId = "PO-2026-0042",
            WarehouseId = 1,
            ReceivedBy = 88,
            ReceivedAt = DateTime.UtcNow,
            Items = new List<GoodsReceivedItemPayload>
            {
                new() { ProductId = 104, QuantityReceived = 30 }
            }
        };

        var envelope = new CloudEventEnvelope<GoodsReceivedPayload>(
            eventType: "GoodsReceived",
            aggregateType: "PurchaseOrder",
            aggregateId: "PO-2026-0042",
            payload: payload
        )
        {
            EventId = "evt_goods_receipt_001"
        };

        // Act
        await _handler.HandleGoodsReceivedAsync(envelope);

        // Assert: Tồn kho đã tăng
        var stock = await _context.InventoryStocks.AsNoTracking().FirstAsync(s => s.ProductId == 104);
        Assert.Equal(30, stock.QuantityOnHand);

        // Đã ghi Thẻ kho
        var movement = await _context.StockMovements
            .FirstOrDefaultAsync(m => m.ProductId == 104 && m.MovementType == "PO_RECEIPT");

        Assert.NotNull(movement);
        Assert.Equal(30, movement.QuantityDelta);
        Assert.Equal("GR-2026-PO-001", movement.ReferenceDoc);
    }
}
