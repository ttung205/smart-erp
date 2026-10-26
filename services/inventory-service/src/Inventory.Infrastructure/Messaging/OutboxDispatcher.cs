using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;
using Inventory.Infrastructure.Persistence;

namespace Inventory.Infrastructure.Messaging;

/// <summary>
/// Một chu kỳ relay của Transactional Outbox. Chỉ đánh dấu PUBLISHED SAU KHI broker xác nhận;
/// lỗi thì giữ PENDING (tăng RetryCount, ghi LastError) và dừng chu kỳ để giữ đúng thứ tự và không dồn dập khi broker đang lỗi.
/// Nếu tiến trình chết sau khi broker xác nhận nhưng trước khi lưu PUBLISHED, event được gửi lại với cùng EventId
/// (at-least-once) - consumer chống trùng theo event_id.
/// </summary>
public sealed class OutboxDispatcher
{
    public const int BatchSize = 50;
    private const int MaxErrorLength = 2000;

    private readonly Func<IOutboxBrokerPublisher?> _publisherFactory;
    private readonly ILogger _logger;

    public OutboxDispatcher(Func<IOutboxBrokerPublisher?> publisherFactory, ILogger logger)
    {
        _publisherFactory = publisherFactory ?? throw new ArgumentNullException(nameof(publisherFactory));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
    }

    /// <returns>false khi broker chưa sẵn sàng hoặc có event gửi lỗi (caller nên chờ lâu hơn rồi thử lại).</returns>
    public async Task<bool> DispatchAsync(InventoryDbContext context, CancellationToken ct)
    {
        // FAILED chỉ còn là trạng thái cũ (bản trước đặt FAILED sau 5 lần lỗi); vẫn được gửi lại để không mất event
        var pending = await context.OutboxEvents
            .Where(e => e.Status == "PENDING" || e.Status == "FAILED")
            .OrderBy(e => e.CreatedAt)
            .ThenBy(e => e.Id)
            .Take(BatchSize)
            .ToListAsync(ct);

        if (pending.Count == 0) return true;

        using var publisher = _publisherFactory();
        if (publisher is null)
        {
            _logger.LogDebug("RabbitMQ chưa sẵn sàng. Tạm hoãn lượt đẩy Outbox Events này.");
            return false;
        }

        foreach (var outboxEvent in pending)
        {
            try
            {
                publisher.Publish(outboxEvent);
            }
            catch (Exception ex)
            {
                outboxEvent.Status = "PENDING";
                outboxEvent.RetryCount++;
                outboxEvent.LastError = Truncate(ex.Message);
                _logger.LogWarning(ex, "Broker chưa xác nhận Outbox Event {EventId} (lần thử {RetryCount}); giữ PENDING để gửi lại.",
                    outboxEvent.EventId, outboxEvent.RetryCount);
                await context.SaveChangesAsync(CancellationToken.None);
                return false;
            }

            outboxEvent.Status = "PUBLISHED";
            outboxEvent.PublishedAt = DateTime.UtcNow;
            outboxEvent.LastError = null;
            // Lưu ngay từng event để cửa sổ "đã gửi nhưng chưa ghi PUBLISHED" chỉ còn là 1 event
            await context.SaveChangesAsync(ct);

            _logger.LogInformation("Đã publish (broker xác nhận) Outbox Event {EventId} [{EventType}] lên '{RoutingKey}'",
                outboxEvent.EventId, outboxEvent.EventType, outboxEvent.RoutingKey);
        }

        return true;
    }

    private static string Truncate(string message) =>
        message.Length <= MaxErrorLength ? message : message[..MaxErrorLength];
}
