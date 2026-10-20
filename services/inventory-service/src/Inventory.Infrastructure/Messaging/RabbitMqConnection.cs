using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;
using RabbitMQ.Client;

namespace Inventory.Infrastructure.Messaging;

/// <summary>
/// Quản lý kết nối an toàn và bền bỉ tới RabbitMQ Broker.
/// </summary>
public class RabbitMqConnection : IDisposable
{
    private readonly IConfiguration _configuration;
    private readonly ILogger<RabbitMqConnection> _logger;
    private IConnection? _connection;
    private readonly object _lock = new();
    private bool _disposed;

    public const string ExchangeName = "erp.events";
    public const string InventoryQueueName = "erp.inventory.events";

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
                // Khởi tạo Topic Exchange chuẩn toàn hệ thống
                channel.ExchangeDeclare(
                    exchange: ExchangeName,
                    type: ExchangeType.Topic,
                    durable: true,
                    autoDelete: false,
                    arguments: null);

                // Khởi tạo Queue cho Inventory Service
                channel.QueueDeclare(
                    queue: InventoryQueueName,
                    durable: true,
                    exclusive: false,
                    autoDelete: false,
                    arguments: null);

                // Bind queue với các routing key cần nghe
                // Giữ hàng cho chứng từ bán hàng đi qua REST (reserve/batch), inventory không nghe sales.order.* nữa;
                // gỡ binding cũ còn sót trên broker đã chạy trước đây (unbind binding không tồn tại không lỗi)
                channel.QueueUnbind(queue: InventoryQueueName, exchange: ExchangeName, routingKey: "sales.order.*");
                channel.QueueBind(queue: InventoryQueueName, exchange: ExchangeName, routingKey: "purchase.goods.*");
                channel.QueueBind(queue: InventoryQueueName, exchange: ExchangeName, routingKey: "catalog.product.*");

                _logger.LogInformation("Đã kết nối thành công tới RabbitMQ Broker tại {Host}:{Port}", factory.HostName, factory.Port);
                return true;
            }
            catch (Exception ex)
            {
                _logger.LogWarning("Chưa thể kết nối tới RabbitMQ Broker ({Message}). Các tác vụ nền sẽ thử lại sau.", ex.Message);
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
