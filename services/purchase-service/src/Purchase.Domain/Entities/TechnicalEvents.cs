namespace Purchase.Domain.Entities;

public class OutboxEvent
{
    public long Id { get; set; }
    public string EventId { get; set; } = string.Empty;
    public string EventType { get; set; } = string.Empty;
    public string AggregateType { get; set; } = string.Empty;
    public string AggregateId { get; set; } = string.Empty;
    public string RoutingKey { get; set; } = string.Empty;
    public string Payload { get; set; } = string.Empty;
    public string Status { get; set; } = "PENDING"; // PENDING, PUBLISHED, FAILED
    public int RetryCount { get; set; } = 0;
    public string? LastError { get; set; }
    public DateTime? PublishedAt { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
}

public class ProcessedEvent
{
    public string EventId { get; set; } = string.Empty;
    public string ConsumerName { get; set; } = string.Empty;
    public DateTime ProcessedAt { get; set; } = DateTime.UtcNow;
}
