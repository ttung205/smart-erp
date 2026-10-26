using System.Text;
using Purchase.Domain.Entities;
using RabbitMQ.Client;
using RabbitMQ.Client.Exceptions;

namespace Purchase.Infrastructure.Messaging;

/// <summary>
/// Publish lên exchange 'erp.events' với publisher confirms: chỉ coi là đã gửi khi broker ack
/// (message đã được broker nhận và xử lý, với queue bền vững là đã ghi xuống đĩa).
/// </summary>
public sealed class ConfirmedOutboxPublisher : IOutboxBrokerPublisher
{
    private static readonly TimeSpan ConfirmTimeout = TimeSpan.FromSeconds(5);
    private readonly IModel _channel;

    public ConfirmedOutboxPublisher(IModel channel)
    {
        _channel = channel ?? throw new ArgumentNullException(nameof(channel));
        _channel.ConfirmSelect();
    }

    public void Publish(OutboxEvent outboxEvent)
    {
        var properties = _channel.CreateBasicProperties();
        properties.ContentType = "application/json";
        properties.DeliveryMode = 2; // Persistent message
        properties.MessageId = outboxEvent.EventId;
        properties.Type = outboxEvent.EventType;
        properties.Timestamp = new AmqpTimestamp(DateTimeOffset.UtcNow.ToUnixTimeSeconds());

        _channel.BasicPublish(
            exchange: RabbitMqConnection.ExchangeName,
            routingKey: outboxEvent.RoutingKey,
            basicProperties: properties,
            body: Encoding.UTF8.GetBytes(outboxEvent.Payload));

        if (!_channel.WaitForConfirms(ConfirmTimeout, out var timedOut))
        {
            throw new BrokerUnreachableException(new InvalidOperationException(
                timedOut ? "Broker không xác nhận (publisher confirm) trong thời gian cho phép." : "Broker từ chối (nack) message."));
        }
    }

    public void Dispose() => _channel.Dispose();
}
