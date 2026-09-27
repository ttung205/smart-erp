using Inventory.Domain.Common;

namespace Inventory.Application.Common.Commands;

/// <summary>
/// Lệnh yêu cầu giải phóng giữ chỗ khi đơn bán hàng bị hủy hoặc hết hạn thanh toán.
/// </summary>
public record ReleaseStockCommand(
    string OrderId,
    long WarehouseId,
    long ProductId,
    int Quantity,
    long? UserId = null
) : IStockResourceIdentifier;
