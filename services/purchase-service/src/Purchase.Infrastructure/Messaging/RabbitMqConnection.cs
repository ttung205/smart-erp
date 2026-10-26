using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;
using RabbitMQ.Client;

namespace Purchase.Infrastructure.Messaging;

/// <summary>
/// Quản lý kết nối an toàn và bền bỉ tới RabbitMQ Broker cho purchase-service.
/// </summary>
public class RabbitMqConnection : IDisposable
{
    private readonly IConfiguration _configuration;
    private readonly ILogger<RabbitMqConnection> _logger;
    private IConnection? _connection;
    private readonly object _lock = new();
    private bool _disposed;

    public const string ExchangeName = "erp.events";

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

                // Purchase chỉ publish, chưa có consumer nên không khai báo/bind queue erp.purchase.events ở đây:
                // queue do infrastructure/rabbitmq/definitions.json khai báo (kèm dead-letter, khai báo lệch tham số sẽ bị broker từ chối)
                // và bind inventory.goods.* vào queue không ai đọc sẽ làm message tồn đọng.

                _logger.LogInformation("Đã kết nối thành công tới RabbitMQ Broker tại {Host}:{Port}", factory.HostName, factory.Port);
                return true;
            }
            catch (Exception ex)
            {
                _logger.LogWarning("Chưa thể kết nối tới RabbitMQ Broker ({Message}). Các tác vụ Outbox sẽ tạm hoãn và tự động thử lại.", ex.Message);
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
