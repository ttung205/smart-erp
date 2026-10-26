using Inventory.Application.Common;
using Inventory.Application.Common.Commands;
using Inventory.Application.Common.Events;
using Inventory.Application.Interfaces;
using Inventory.Domain.Entities;
using Inventory.Infrastructure.Messaging;
using Inventory.Infrastructure.Persistence;
using Inventory.Infrastructure.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace Inventory.Infrastructure.Tests;

/// <summary>
/// PR-03: relay chỉ PUBLISHED sau khi broker xác nhận, giữ PENDING khi lỗi, gửi lại cùng EventId;
/// consumer áp nghiệp vụ đúng một lần dù nhận trùng hoặc crash giữa chừng.
/// </summary>
public class OutboxReliabilityTests : IDisposable
{
    private const long Warehouse = 1;
    private const long Product = 10;
    private const long Product2 = 11;

    private readonly SqliteConnection _connection;
    private readonly InventoryDbContext _context;

    public OutboxReliabilityTests()
    {
        _connection = new SqliteConnection("DataSource=:memory:");
        _connection.Open();
        _context = NewContext();
        _context.Database.EnsureCreated();
        _context.Warehouses.Add(new Warehouse { Id = Warehouse, Code = "WH", Name = "Kho", Location = "HN", IsActive = true });
        _context.InventoryStocks.Add(new InventoryStock(Warehouse, Product, 5));
        _context.InventoryStockReservations.Add(new InventoryStockReservation(Warehouse, Product, 5));
        _context.InventoryStocks.Add(new InventoryStock(Warehouse, Product2, 0));
        _context.InventoryStockReservations.Add(new InventoryStockReservation(Warehouse, Product2, 0));
        _context.SaveChanges();
    }

    public void Dispose()
    {
        _context.Dispose();
        _connection.Dispose();
    }

    private InventoryDbContext NewContext() =>
        new(new DbContextOptionsBuilder<InventoryDbContext>().UseSqlite(_connection).Options);

    private OutboxEvent AddEvent(string id, DateTime createdAt)
    {
        var e = new OutboxEvent { EventId = id, EventType = "GoodsIssued", AggregateType = "S", AggregateId = id, RoutingKey = "inventory.goods.issued", Payload = "{}", CreatedAt = createdAt };
        _context.OutboxEvents.Add(e);
        _context.SaveChanges();
        return e;
    }

    private sealed class FakePublisher : IOutboxBrokerPublisher
    {
        public List<string> Delivered { get; } = new();
        public Func<OutboxEvent, bool> Accept { get; set; } = _ => true;

        public void Publish(OutboxEvent outboxEvent)
        {
            if (!Accept(outboxEvent)) throw new InvalidOperationException("broker nack");
            Delivered.Add(outboxEvent.EventId);
        }

        public void Dispose() { }
    }

    private sealed class CrashAfterPublish : IOutboxBrokerPublisher
    {
        private readonly IOutboxBrokerPublisher _inner;
        public CrashAfterPublish(IOutboxBrokerPublisher inner) => _inner = inner;

        public void Publish(OutboxEvent outboxEvent)
        {
            _inner.Publish(outboxEvent);
            throw new InvalidOperationException("process crashed before PUBLISHED was saved");
        }

        public void Dispose() { }
    }

    private static OutboxDispatcher Dispatcher(IOutboxBrokerPublisher? publisher) =>
        new(() => publisher, NullLogger.Instance);

    [Fact]
    public async Task Broker_confirm_marks_published()
    {
        var e = AddEvent("evt-1", DateTime.UtcNow);
        var broker = new FakePublisher();

        Assert.True(await Dispatcher(broker).DispatchAsync(_context, default));

        _context.ChangeTracker.Clear();
        var stored = await _context.OutboxEvents.SingleAsync(x => x.Id == e.Id);
        Assert.Equal("PUBLISHED", stored.Status);
        Assert.NotNull(stored.PublishedAt);
        Assert.Equal(new[] { "evt-1" }, broker.Delivered);
    }

    [Fact]
    public async Task Broker_down_keeps_event_pending_and_it_is_sent_after_recovery()
    {
        var e = AddEvent("evt-2", DateTime.UtcNow);

        Assert.False(await Dispatcher(null).DispatchAsync(_context, default)); // không tạo được channel

        _context.ChangeTracker.Clear();
        Assert.Equal("PENDING", (await _context.OutboxEvents.SingleAsync(x => x.Id == e.Id)).Status);

        var broker = new FakePublisher();
        Assert.True(await Dispatcher(broker).DispatchAsync(_context, default));

        _context.ChangeTracker.Clear();
        Assert.Equal("PUBLISHED", (await _context.OutboxEvents.SingleAsync(x => x.Id == e.Id)).Status);
        Assert.Equal(new[] { "evt-2" }, broker.Delivered);
    }

    [Fact]
    public async Task Nack_or_timeout_keeps_pending_records_error_and_never_gives_up()
    {
        var e = AddEvent("evt-3", DateTime.UtcNow);
        var broker = new FakePublisher { Accept = _ => false };

        for (var i = 0; i < 8; i++) // bản cũ: 5 lần lỗi là bị đánh FAILED vĩnh viễn
        {
            Assert.False(await Dispatcher(broker).DispatchAsync(_context, default));
        }

        _context.ChangeTracker.Clear();
        var stored = await _context.OutboxEvents.SingleAsync(x => x.Id == e.Id);
        Assert.Equal("PENDING", stored.Status);
        Assert.Equal(8, stored.RetryCount);
        Assert.Contains("nack", stored.LastError);
        Assert.Null(stored.PublishedAt);

        broker.Accept = _ => true;
        Assert.True(await Dispatcher(broker).DispatchAsync(_context, default));
        _context.ChangeTracker.Clear();
        Assert.Equal("PUBLISHED", (await _context.OutboxEvents.SingleAsync(x => x.Id == e.Id)).Status);
    }

    [Fact]
    public async Task Legacy_failed_events_are_retried()
    {
        var e = AddEvent("evt-4", DateTime.UtcNow);
        e.Status = "FAILED";
        e.RetryCount = 5;
        _context.SaveChanges();

        await Dispatcher(new FakePublisher()).DispatchAsync(_context, default);

        _context.ChangeTracker.Clear();
        Assert.Equal("PUBLISHED", (await _context.OutboxEvents.SingleAsync(x => x.Id == e.Id)).Status);
    }

    [Fact]
    public async Task Failure_stops_the_batch_so_order_is_preserved()
    {
        var now = DateTime.UtcNow;
        AddEvent("evt-a", now);
        AddEvent("evt-b", now.AddSeconds(1));
        AddEvent("evt-c", now.AddSeconds(2));
        var broker = new FakePublisher { Accept = e => e.EventId != "evt-b" };

        Assert.False(await Dispatcher(broker).DispatchAsync(_context, default));

        _context.ChangeTracker.Clear();
        Assert.Equal(new[] { "evt-a" }, broker.Delivered);
        var status = await _context.OutboxEvents.ToDictionaryAsync(x => x.EventId, x => x.Status);
        Assert.Equal("PUBLISHED", status["evt-a"]);
        Assert.Equal("PENDING", status["evt-b"]);
        Assert.Equal("PENDING", status["evt-c"]);
    }

    private static CloudEventEnvelope<GoodsReceivedPayload> Receipt(string eventId, params (long product, int qty)[] items) => new()
    {
        EventId = eventId,
        EventType = "GoodsReceived",
        Payload = new GoodsReceivedPayload
        {
            ReceiptId = "GR-1",
            PoId = "PO-1",
            WarehouseId = Warehouse,
            Items = items.Select(i => new GoodsReceivedItemPayload { ProductId = i.product, QuantityReceived = i.qty }).ToList()
        }
    };

    private static EventConsumerHandler Handler(InventoryDbContext context) =>
        new(context, new InventoryStockManager(context), new OutboxService(context), NullLogger<EventConsumerHandler>.Instance);

    private async Task<int> OnHandAsync()
    {
        using var fresh = NewContext();
        return (await fresh.InventoryStocks.AsNoTracking().SingleAsync(s => s.ProductId == Product)).Quantity;
    }

    [Fact]
    public async Task Redelivery_after_crash_between_publish_and_mark_applies_business_once()
    {
        // Relay: broker đã nhận event nhưng tiến trình chết trước khi ghi PUBLISHED → event vẫn PENDING và được gửi lại
        AddEvent("evt-redeliver", DateTime.UtcNow);
        var broker = new FakePublisher();
        Assert.False(await Dispatcher(new CrashAfterPublish(broker)).DispatchAsync(_context, default));
        Assert.True(await Dispatcher(broker).DispatchAsync(_context, default));
        Assert.Equal(new[] { "evt-redeliver", "evt-redeliver" }, broker.Delivered); // at-least-once, cùng EventId

        // Consumer nhận cả hai bản sao của cùng event_id
        var receipt = Receipt("evt-redeliver", (Product, 3));
        await Handler(NewContext()).HandleGoodsReceivedAsync(receipt);
        await Handler(NewContext()).HandleGoodsReceivedAsync(receipt);

        Assert.Equal(8, await OnHandAsync()); // 5 + 3, không phải 5 + 6
    }

    /// <summary>Manager thật nhưng "crash" ở lần nhập kho thứ hai (mô phỏng tiến trình chết giữa chừng).</summary>
    private sealed class CrashOnSecondReceive : IInventoryStockManager
    {
        private readonly InventoryStockManager _inner;
        private int _receives;
        public CrashOnSecondReceive(InventoryStockManager inner) => _inner = inner;

        public Task<StockOperationResult> ReceiveStockAsync(ReceiveStockCommand command, CancellationToken ct = default) =>
            ++_receives == 2 ? throw new InvalidOperationException("crash") : _inner.ReceiveStockAsync(command, ct);

        public Task<StockOperationResult> ChangePhysicalStockAsync(ChangeStockCommand command, CancellationToken ct = default) => _inner.ChangePhysicalStockAsync(command, ct);
        public Task<StockOperationResult> ChangeReservationStockAsync(ChangeStockCommand command, CancellationToken ct = default) => _inner.ChangeReservationStockAsync(command, ct);
        public Task<StockOperationResult> ReserveStockAsync(ReserveStockCommand command, CancellationToken ct = default) => _inner.ReserveStockAsync(command, ct);
        public Task<IReadOnlyList<StockOperationResult>> BatchReserveStockAsync(BatchReserveStockCommand command, CancellationToken ct = default) => _inner.BatchReserveStockAsync(command, ct);
        public Task<StockOperationResult> ReleaseStockAsync(ReleaseStockCommand command, CancellationToken ct = default) => _inner.ReleaseStockAsync(command, ct);
        public Task<IReadOnlyList<StockOperationResult>> ReleaseDocumentReservationAsync(string documentId, long? userId = null, CancellationToken ct = default) => _inner.ReleaseDocumentReservationAsync(documentId, userId, ct);
        public Task<StockOperationResult> IssueStockAsync(IssueStockCommand command, CancellationToken ct = default) => _inner.IssueStockAsync(command, ct);
        public Task<InventoryStock?> GetPhysicalStockAsync(long productId, long warehouseId, CancellationToken ct = default) => _inner.GetPhysicalStockAsync(productId, warehouseId, ct);
        public Task<InventoryStockReservation?> GetReservationStockAsync(long productId, long warehouseId, CancellationToken ct = default) => _inner.GetReservationStockAsync(productId, warehouseId, ct);
    }

    [Fact]
    public async Task Crash_midway_rolls_back_stock_and_event_is_not_marked_processed()
    {
        var context = NewContext();
        var crashing = new EventConsumerHandler(context, new CrashOnSecondReceive(new InventoryStockManager(context)), new OutboxService(context), NullLogger<EventConsumerHandler>.Instance);

        await Assert.ThrowsAsync<InvalidOperationException>(() =>
            crashing.HandleGoodsReceivedAsync(Receipt("evt-partial", (Product, 3), (Product2, 4))));

        using var fresh = NewContext();
        Assert.Equal(5, await OnHandAsync()); // dòng đầu không bị cộng nửa chừng
        Assert.False(await fresh.ProcessedEvents.AnyAsync(p => p.EventId == "evt-partial"));

        // Broker giao lại: xử lý trọn vẹn đúng một lần
        await Handler(NewContext()).HandleGoodsReceivedAsync(Receipt("evt-partial", (Product, 3), (Product2, 4)));
        await Handler(NewContext()).HandleGoodsReceivedAsync(Receipt("evt-partial", (Product, 3), (Product2, 4)));
        Assert.Equal(8, await OnHandAsync()); // 5 + 3, mỗi dòng đúng một lần
        using var check = NewContext();
        Assert.Equal(4, (await check.InventoryStocks.AsNoTracking().SingleAsync(s => s.ProductId == Product2)).Quantity);
    }
}
