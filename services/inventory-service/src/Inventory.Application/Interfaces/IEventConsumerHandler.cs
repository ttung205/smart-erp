using Inventory.Application.Common.Events;

namespace Inventory.Application.Interfaces;

/// <summary>
/// Giao diện xử lý sự kiện bất đồng bộ từ các Microservices khác trong hệ thống (sales-service, purchase-service).
/// </summary>
public interface IEventConsumerHandler
{
    /// <summary>
    /// Xử lý sự kiện tạo đơn bán hàng SalesOrderCreated (Saga Orchestration: Giữ kho nguyên tử).
    /// </summary>
    Task HandleSalesOrderCreatedAsync(CloudEventEnvelope<SalesOrderCreatedPayload> envelope, CancellationToken ct = default);

    /// <summary>
    /// Xử lý sự kiện nhập kho hàng hóa GoodsReceived từ purchase-service (Cộng dồn OnHand & Thẻ kho).
    /// </summary>
    Task HandleGoodsReceivedAsync(CloudEventEnvelope<GoodsReceivedPayload> envelope, CancellationToken ct = default);
}
