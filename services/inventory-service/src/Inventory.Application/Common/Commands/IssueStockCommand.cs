using Inventory.Domain.Common;

namespace Inventory.Application.Common.Commands;

/// <summary>
/// Lệnh yêu cầu xuất kho thực tế (Goods Issue / Delivery) cho đơn hàng đã giữ chỗ hoặc xuất trực tiếp.
/// </summary>
public record IssueStockCommand(
    string ReferenceDoc,
    long WarehouseId,
    long ProductId,
    int Quantity,
    string? OrderId = null,
    long? UserId = null
) : IStockResourceIdentifier;
