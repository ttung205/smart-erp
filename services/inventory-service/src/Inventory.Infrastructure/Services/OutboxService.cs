using System.Text.Json;
using Inventory.Application.Common.Events;
using Inventory.Application.Interfaces;
using Inventory.Domain.Entities;
using Inventory.Infrastructure.Persistence;

namespace Inventory.Infrastructure.Services;

/// <summary>
/// Triển khai Transactional Outbox Pattern: Đảm bảo Domain Event luôn được ghi vào cùng Database Transaction với thay đổi nghiệp vụ.
/// </summary>
public class OutboxService : IOutboxService
{
    private readonly InventoryDbContext _context;
    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        WriteIndented = false
    };

    public OutboxService(InventoryDbContext context)
    {
        _context = context ?? throw new ArgumentNullException(nameof(context));
    }

    /// <inheritdoc />
    public async Task SaveEventAsync<T>(
        string routingKey,
        string eventType,
        string aggregateType,
        string aggregateId,
        T payload,
        string? correlationId = null,
        CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(payload);

        var cloudEvent = new CloudEventEnvelope<T>(
            eventType: eventType,
            aggregateType: aggregateType,
            aggregateId: aggregateId,
            payload: payload,
            correlationId: correlationId
        );

        var payloadJson = JsonSerializer.Serialize(cloudEvent, JsonOptions);

        var outboxRecord = new OutboxEvent
        {
            EventId = cloudEvent.EventId,
            EventType = eventType,
            AggregateType = aggregateType,
            AggregateId = aggregateId,
            RoutingKey = routingKey,
            Payload = payloadJson,
            Status = "PENDING",
            RetryCount = 0,
            CreatedAt = DateTime.UtcNow
        };

        _context.OutboxEvents.Add(outboxRecord);
        await _context.SaveChangesAsync(ct);
    }
}
