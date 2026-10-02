namespace Purchase.Application.Interfaces;

/// <summary>
/// Quản lý Transactional Outbox ghi nhận sự kiện miền trong cùng 1 Local SQL Transaction.
/// </summary>
public interface IOutboxService
{
    /// <summary>
    /// Lưu một Domain Event vào bảng outbox_events với trạng thái PENDING.
    /// </summary>
    Task SaveEventAsync<T>(
        string routingKey,
        string eventType,
        string aggregateType,
        string aggregateId,
        T payload,
        string? correlationId = null,
        CancellationToken ct = default);
}
