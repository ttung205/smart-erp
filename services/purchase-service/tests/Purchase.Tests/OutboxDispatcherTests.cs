using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging.Abstractions;
using Purchase.Domain.Entities;
using Purchase.Infrastructure.Messaging;
using Purchase.Infrastructure.Persistence;
using Xunit;

namespace Purchase.Tests;

/// <summary>PR-03: chỉ PUBLISHED sau khi broker xác nhận; lỗi thì giữ PENDING và gửi lại cùng EventId, đúng thứ tự.</summary>
public class OutboxDispatcherTests : IDisposable
{
    private readonly PurchaseDbContext _context;
    private readonly SqliteConnection _connection;

    public OutboxDispatcherTests()
    {
        (_context, _connection) = TestDbContextFactory.CreateInMemoryDbContext();
    }

    public void Dispose()
    {
        _context.Dispose();
        _connection.Dispose();
    }

    private OutboxEvent AddEvent(string id, DateTime createdAt)
    {
        var e = new OutboxEvent { EventId = id, EventType = "PurchaseOrderApproved", AggregateType = "PO", AggregateId = id, RoutingKey = "purchase.order.approved", Payload = "{}", CreatedAt = createdAt };
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

        Assert.False(await Dispatcher(null).DispatchAsync(_context, default));

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

    [Fact]
    public async Task Crash_after_publish_resends_same_event_id_so_consumers_can_deduplicate()
    {
        AddEvent("evt-redeliver", DateTime.UtcNow);
        var broker = new FakePublisher();

        Assert.False(await Dispatcher(new CrashAfterPublish(broker)).DispatchAsync(_context, default));
        Assert.True(await Dispatcher(broker).DispatchAsync(_context, default));

        Assert.Equal(new[] { "evt-redeliver", "evt-redeliver" }, broker.Delivered);
    }
}
