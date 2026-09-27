using System.Text.Json.Serialization;

namespace Inventory.Application.Common.Events;

/// <summary>
/// Cấu trúc Envelope sự kiện chuẩn hóa tương thích CloudEvents theo docs/contracts/events-spec.md
/// </summary>
public class CloudEventEnvelope<T>
{
    [JsonPropertyName("event_id")]
    public string EventId { get; set; } = $"evt_{Guid.NewGuid():N}";

    [JsonPropertyName("event_type")]
    public string EventType { get; set; } = string.Empty;

    [JsonPropertyName("aggregate_type")]
    public string AggregateType { get; set; } = string.Empty;

    [JsonPropertyName("aggregate_id")]
    public string AggregateId { get; set; } = string.Empty;

    [JsonPropertyName("timestamp")]
    public DateTime Timestamp { get; set; } = DateTime.UtcNow;

    [JsonPropertyName("version")]
    public string Version { get; set; } = "1.0";

    [JsonPropertyName("correlation_id")]
    public string CorrelationId { get; set; } = $"req_corr_{Guid.NewGuid():N}";

    [JsonPropertyName("producer")]
    public string Producer { get; set; } = "inventory-service";

    [JsonPropertyName("payload")]
    public T Payload { get; set; } = default!;

    public CloudEventEnvelope()
    {
    }

    public CloudEventEnvelope(string eventType, string aggregateType, string aggregateId, T payload, string? correlationId = null)
    {
        EventType = eventType;
        AggregateType = aggregateType;
        AggregateId = aggregateId;
        Payload = payload;
        Timestamp = DateTime.UtcNow;
        if (!string.IsNullOrWhiteSpace(correlationId))
        {
            CorrelationId = correlationId;
        }
    }
}
