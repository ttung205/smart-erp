using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Purchase.Infrastructure.Persistence;

namespace Purchase.Infrastructure.Messaging;

/// <summary>
/// Background Service quét bảng outbox_events định kỳ và publish lên RabbitMQ Topic Exchange 'erp.events'
/// qua <see cref="OutboxDispatcher"/> (publisher confirms). Broker lỗi thì backoff tăng dần, event vẫn nằm trong DB.
/// </summary>
public class OutboxRelayBackgroundService : BackgroundService
{
    private static readonly TimeSpan PollingInterval = TimeSpan.FromSeconds(1.5);
    private static readonly TimeSpan MaxBackoff = TimeSpan.FromSeconds(30);

    private readonly IServiceScopeFactory _scopeFactory;
    private readonly OutboxDispatcher _dispatcher;
    private readonly ILogger<OutboxRelayBackgroundService> _logger;

    public OutboxRelayBackgroundService(
        IServiceScopeFactory scopeFactory,
        RabbitMqConnection rabbitMqConnection,
        ILogger<OutboxRelayBackgroundService> logger)
    {
        _scopeFactory = scopeFactory;
        _logger = logger;
        _dispatcher = new OutboxDispatcher(
            () => rabbitMqConnection.CreateModel() is { } channel ? new ConfirmedOutboxPublisher(channel) : null,
            logger);
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("OutboxRelayBackgroundService đã khởi động.");
        var failures = 0;

        while (!stoppingToken.IsCancellationRequested)
        {
            var healthy = false;
            try
            {
                using var scope = _scopeFactory.CreateScope();
                var context = scope.ServiceProvider.GetRequiredService<PurchaseDbContext>();
                healthy = await _dispatcher.DispatchAsync(context, stoppingToken);
            }
            catch (OperationCanceledException) when (stoppingToken.IsCancellationRequested)
            {
                break;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Lỗi không mong muốn trong chu kỳ quét Outbox Events.");
            }

            failures = healthy ? 0 : Math.Min(failures + 1, 10);
            var delay = healthy
                ? PollingInterval
                : TimeSpan.FromTicks(Math.Min(MaxBackoff.Ticks, PollingInterval.Ticks << failures));

            try
            {
                await Task.Delay(delay, stoppingToken);
            }
            catch (OperationCanceledException)
            {
                break;
            }
        }

        _logger.LogInformation("OutboxRelayBackgroundService đã dừng.");
    }
}
