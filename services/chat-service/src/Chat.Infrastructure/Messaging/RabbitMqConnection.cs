using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;
using RabbitMQ.Client;

namespace Chat.Infrastructure.Messaging;

public class RabbitMqConnection : IDisposable
{
    private readonly IConfiguration _configuration;
    private readonly ILogger<RabbitMqConnection> _logger;
    private IConnection? _connection;
    private readonly object _lock = new();
    private bool _disposed;

    public const string ExchangeName = "erp.events";
    public const string ChatQueueName = "erp.chat.events";

    public RabbitMqConnection(IConfiguration configuration, ILogger<RabbitMqConnection> logger)
    {
        _configuration = configuration;
        _logger = logger;
    }

    public bool IsConnected => _connection is { IsOpen: true } && !_disposed;

    public bool TryConnect()
    {
        if (IsConnected) return true;

        lock (_lock)
        {
            if (IsConnected) return true;

            try
            {
                var factory = new ConnectionFactory
                {
                    HostName = _configuration["RabbitMQ:HostName"] ?? "localhost",
                    Port = int.TryParse(_configuration["RabbitMQ:Port"], out var port) ? port : 5672,
                    UserName = _configuration["RabbitMQ:UserName"] ?? "erp_rabbit",
                    Password = _configuration["RabbitMQ:Password"] ?? "rabbit_secret",
                    DispatchConsumersAsync = true,
                    AutomaticRecoveryEnabled = true,
                    NetworkRecoveryInterval = TimeSpan.FromSeconds(5)
                };

                _connection = factory.CreateConnection();

                using var channel = _connection.CreateModel();
                // 1. Khai báo Topic Exchange chung toàn hệ thống
                channel.ExchangeDeclare(
                    exchange: ExchangeName,
                    type: ExchangeType.Topic,
                    durable: true,
                    autoDelete: false,
                    arguments: null);

                // 2. Khai báo Queue riêng cho Chat Bot
                channel.QueueDeclare(
                    queue: ChatQueueName,
                    durable: true,
                    exclusive: false,
                    autoDelete: false,
                    arguments: null);

                // 3. Bindings các sự kiện nghiệp vụ tới Chat Bot
                var routingKeys = new[]
                {
                    "catalog.product.*",
                    "purchase.order.*",
                    "purchase.inquiry.*",
                    "inventory.stock.*",
                    "sales.order.*",
                    "eoffice.leave.*"
                };

                foreach (var rk in routingKeys)
                {
                    channel.QueueBind(queue: ChatQueueName, exchange: ExchangeName, routingKey: rk);
                }

                _logger.LogInformation("Đã kết nối thành công tới RabbitMQ Broker tại {Host}:{Port} cho Chat Service", factory.HostName, factory.Port);
                return true;
            }
            catch (Exception ex)
            {
                _logger.LogWarning("Chưa thể kết nối tới RabbitMQ Broker ({Message}). Bot Worker sẽ tiếp tục tự động thử kết nối lại ngầm.", ex.Message);
                return false;
            }
        }
    }

    public IModel? CreateModel()
    {
        if (!IsConnected && !TryConnect())
        {
            return null;
        }

        return _connection?.CreateModel();
    }

    public void Dispose()
    {
        if (_disposed) return;
        _disposed = true;

        try
        {
            _connection?.Dispose();
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Lỗi khi đóng kết nối RabbitMQ.");
        }
    }
}
