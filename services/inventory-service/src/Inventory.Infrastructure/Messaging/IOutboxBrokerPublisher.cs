using Inventory.Domain.Entities;

namespace Inventory.Infrastructure.Messaging;

/// <summary>
/// Đẩy một outbox event lên broker. <see cref="Publish"/> chỉ trả về bình thường khi broker ĐÃ XÁC NHẬN (publisher confirm);
/// mọi trường hợp còn lại (nack, timeout, mất kết nối) đều ném exception để event được giữ lại và gửi lại sau.
/// </summary>
public interface IOutboxBrokerPublisher : IDisposable
{
    void Publish(OutboxEvent outboxEvent);
}
