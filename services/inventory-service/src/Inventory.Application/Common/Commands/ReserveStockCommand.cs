using Inventory.Domain.Common;

namespace Inventory.Application.Common.Commands;

/// <summary>
/// Lệnh yêu cầu khóa giữ chỗ tồn kho (Reservation) cho một sản phẩm thuộc đơn hàng SO.
/// </summary>
public record ReserveStockCommand(
    string OrderId,
    long WarehouseId,
    long ProductId,
    int Quantity,
    DateTime? ExpiresAt = null,
    long? UserId = null
) : IStockResourceIdentifier;
