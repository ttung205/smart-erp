using System.Text;
using System.Text.Json;
using Inventory.Application.Common.Events;
using Inventory.Application.Interfaces;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using RabbitMQ.Client;
using RabbitMQ.Client.Events;

namespace Inventory.Infrastructure.Messaging;

/// <summary>
/// Background Worker tiêu thụ message từ queue 'erp.inventory.events' qua RabbitMQ.
/// </summary>
public class RabbitMqConsumerWorker : BackgroundService
{
    private readonly IServiceScopeFactory _scopeFactory;
    private readonly RabbitMqConnection _rabbitMqConnection;
    private readonly ILogger<RabbitMqConsumerWorker> _logger;
    private IModel? _channel;
    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower
    };

    public RabbitMqConsumerWorker(
        IServiceScopeFactory scopeFactory,
        RabbitMqConnection rabbitMqConnection,
        ILogger<RabbitMqConsumerWorker> logger)
    {
        _scopeFactory = scopeFactory;
        _rabbitMqConnection = rabbitMqConnection;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("RabbitMqConsumerWorker đang chuẩn bị lắng nghe queue '{QueueName}'...", RabbitMqConnection.InventoryQueueName);

        // Chờ kết nối RabbitMQ khả dụng
        while (!stoppingToken.IsCancellationRequested && !_rabbitMqConnection.IsConnected)
        {
            if (_rabbitMqConnection.TryConnect())
            {
                break;
            }

            await Task.Delay(5000, stoppingToken);
        }

        if (stoppingToken.IsCancellationRequested) return;

        _channel = _rabbitMqConnection.CreateModel();
        if (_channel is null)
        {
            _logger.LogWarning("Không thể tạo channel cho RabbitMqConsumerWorker.");
            return;
        }

        _channel.BasicQos(prefetchSize: 0, prefetchCount: 10, global: false);

        var consumer = new AsyncEventingBasicConsumer(_channel);
        consumer.Received += async (_, ea) =>
        {
            var messageBody = Encoding.UTF8.GetString(ea.Body.ToArray());
            var routingKey = ea.RoutingKey;

            try
            {
                await ProcessMessageAsync(messageBody, routingKey, stoppingToken);
                _channel.BasicAck(deliveryTag: ea.DeliveryTag, multiple: false);
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Lỗi khi xử lý message từ routing key '{RoutingKey}'. Đưa vào Dead-Letter Queue.", routingKey);
                // Nack không requeue để RabbitMQ tự động đẩy sang erp.dlx
                _channel.BasicNack(deliveryTag: ea.DeliveryTag, multiple: false, requeue: false);
            }
        };

        _channel.BasicConsume(
            queue: RabbitMqConnection.InventoryQueueName,
            autoAck: false,
            consumer: consumer);

        _logger.LogInformation("RabbitMqConsumerWorker đã sẵn sàng nhận tin nhắn từ queue '{QueueName}'.", RabbitMqConnection.InventoryQueueName);

        // Giữ worker sống cho đến khi có tín hiệu hủy
        while (!stoppingToken.IsCancellationRequested)
        {
            await Task.Delay(1000, stoppingToken);
        }
    }

    private async Task ProcessMessageAsync(string messageBody, string routingKey, CancellationToken ct)
    {
        using var scope = _scopeFactory.CreateScope();
        var handler = scope.ServiceProvider.GetRequiredService<IEventConsumerHandler>();

        using var doc = JsonDocument.Parse(messageBody);
        var root = doc.RootElement;

        if (!root.TryGetProperty("event_type", out var eventTypeProp))
        {
            _logger.LogWarning("Message thiếu trường 'event_type'. Bỏ qua: {Body}", messageBody);
            return;
        }

        var eventType = eventTypeProp.GetString();

        switch (eventType)
        {
            case "SalesOrderCreated":
                var salesOrderEvent = JsonSerializer.Deserialize<CloudEventEnvelope<SalesOrderCreatedPayload>>(messageBody, JsonOptions);
                if (salesOrderEvent is not null)
                {
                    await handler.HandleSalesOrderCreatedAsync(salesOrderEvent, ct);
                }
                break;

            case "GoodsReceived":
                var goodsReceivedEvent = JsonSerializer.Deserialize<CloudEventEnvelope<GoodsReceivedPayload>>(messageBody, JsonOptions);
                if (goodsReceivedEvent is not null)
                {
                    await handler.HandleGoodsReceivedAsync(goodsReceivedEvent, ct);
                }
                break;

            default:
                _logger.LogDebug("Bỏ qua sự kiện chưa hỗ trợ '{EventType}' từ '{RoutingKey}'.", eventType, routingKey);
                break;
        }
    }

    public override void Dispose()
    {
        _channel?.Dispose();
        base.Dispose();
    }
}
