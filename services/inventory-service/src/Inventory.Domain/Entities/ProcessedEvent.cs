namespace Inventory.Domain.Entities;

public class ProcessedEvent
{
    public string EventId { get; set; } = string.Empty;
    public string ConsumerName { get; set; } = string.Empty;
    public DateTime ProcessedAt { get; set; } = DateTime.UtcNow;
}
