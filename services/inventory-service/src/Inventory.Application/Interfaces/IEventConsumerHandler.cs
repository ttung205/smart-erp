using Inventory.Application.Common.Events;

namespace Inventory.Application.Interfaces;

/// <summary>
/// Giao diện xử lý sự kiện bất đồng bộ từ các Microservices khác trong hệ thống (purchase-service, catalog-service).
/// </summary>
public interface IEventConsumerHandler
{
    /// <summary>
    /// Xử lý sự kiện nhập kho hàng hóa GoodsReceived từ purchase-service (Cộng dồn OnHand & Thẻ kho).
    /// </summary>
    Task HandleGoodsReceivedAsync(CloudEventEnvelope<GoodsReceivedPayload> envelope, CancellationToken ct = default);

    /// <summary>
    /// Xử lý sự kiện tạo sản phẩm mới ProductCreated từ catalog-service (Tự động khởi tạo tồn kho tại các kho).
    /// </summary>
    Task HandleProductCreatedAsync(CloudEventEnvelope<ProductCreatedPayload> envelope, CancellationToken ct = default);
}
