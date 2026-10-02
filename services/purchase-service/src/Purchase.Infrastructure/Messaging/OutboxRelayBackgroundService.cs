using System.Text;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Purchase.Infrastructure.Persistence;
using RabbitMQ.Client;

namespace Purchase.Infrastructure.Messaging;

/// <summary>
/// Background Service quét bảng outbox_events định kỳ và publish message lên RabbitMQ Topic Exchange 'erp.events'.
/// </summary>
public class OutboxRelayBackgroundService : BackgroundService
{
    private readonly IServiceScopeFactory _scopeFactory;
    private readonly RabbitMqConnection _rabbitMqConnection;
    private readonly ILogger<OutboxRelayBackgroundService> _logger;
    private readonly TimeSpan _pollingInterval = TimeSpan.FromSeconds(1.5);

    public OutboxRelayBackgroundService(
        IServiceScopeFactory scopeFactory,
        RabbitMqConnection rabbitMqConnection,
        ILogger<OutboxRelayBackgroundService> logger)
    {
        _scopeFactory = scopeFactory;
        _rabbitMqConnection = rabbitMqConnection;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("OutboxRelayBackgroundService đã khởi động.");

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                await PublishPendingOutboxEventsAsync(stoppingToken);
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Lỗi không mong muốn trong chu kỳ quét Outbox Events.");
            }

            await Task.Delay(_pollingInterval, stoppingToken);
        }

        _logger.LogInformation("OutboxRelayBackgroundService đã dừng.");
    }

    private async Task PublishPendingOutboxEventsAsync(CancellationToken ct)
    {
        using var scope = _scopeFactory.CreateScope();
        var context = scope.ServiceProvider.GetRequiredService<PurchaseDbContext>();

        // 1. Quét các sự kiện PENDING hoặc FAILED với số lần thử < 5
        var pendingEvents = await context.OutboxEvents
            .Where(e => e.Status == "PENDING" || (e.Status == "FAILED" && e.RetryCount < 5))
            .OrderBy(e => e.CreatedAt)
            .Take(50)
            .ToListAsync(ct);

        if (pendingEvents.Count == 0) return;

        // 2. Thử tạo Channel RabbitMQ
        using var channel = _rabbitMqConnection.CreateModel();
        if (channel is null)
        {
            _logger.LogDebug("RabbitMQ chưa sẵn sàng. Tạm hoãn lượt đẩy Outbox Events này.");
            return;
        }

        foreach (var outboxEvent in pendingEvents)
        {
            try
            {
                var body = Encoding.UTF8.GetBytes(outboxEvent.Payload);
                var properties = channel.CreateBasicProperties();
                properties.ContentType = "application/json";
                properties.DeliveryMode = 2; // Persistent message
                properties.MessageId = outboxEvent.EventId;
                properties.Type = outboxEvent.EventType;
                properties.Timestamp = new AmqpTimestamp(DateTimeOffset.UtcNow.ToUnixTimeSeconds());

                // Publish lên Topic Exchange chuẩn
                channel.BasicPublish(
                    exchange: RabbitMqConnection.ExchangeName,
                    routingKey: outboxEvent.RoutingKey,
                    basicProperties: properties,
                    body: body
                );

                outboxEvent.Status = "PUBLISHED";
                outboxEvent.PublishedAt = DateTime.UtcNow;
                outboxEvent.LastError = null;

                _logger.LogInformation("Đã publish thành công Outbox Event {EventId} [{EventType}] lên '{RoutingKey}'",
                    outboxEvent.EventId, outboxEvent.EventType, outboxEvent.RoutingKey);
            }
            catch (Exception ex)
            {
                outboxEvent.RetryCount++;
                outboxEvent.LastError = ex.Message;
                if (outboxEvent.RetryCount >= 5)
                {
                    outboxEvent.Status = "FAILED";
                }

                _logger.LogWarning(ex, "Thất bại khi publish Outbox Event {EventId} (Lần thử {RetryCount})",
                    outboxEvent.EventId, outboxEvent.RetryCount);
            }
        }

        await context.SaveChangesAsync(ct);
    }
}
