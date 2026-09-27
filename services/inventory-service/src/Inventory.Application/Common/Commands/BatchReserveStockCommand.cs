using Inventory.Domain.Common;

namespace Inventory.Application.Common.Commands;

public record ReserveStockItem(
    long WarehouseId,
    long ProductId,
    int Quantity
) : IStockResourceIdentifier;

/// <summary>
/// Lệnh yêu cầu giữ chỗ theo lô (nhiều mặt hàng) trong cùng một đơn bán hàng.
/// Bắt buộc phải áp dụng Resource Lock Ordering trước khi thực hiện để triệt tiêu Deadlock.
/// </summary>
public record BatchReserveStockCommand(
    string OrderId,
    IReadOnlyList<ReserveStockItem> Items,
    DateTime? ExpiresAt = null,
    long? UserId = null
);
