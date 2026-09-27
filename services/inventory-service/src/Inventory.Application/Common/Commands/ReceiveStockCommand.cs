using Inventory.Domain.Common;

namespace Inventory.Application.Common.Commands;

/// <summary>
/// Lệnh yêu cầu nhập kho thực tế từ Đơn mua hàng PO hoặc Phiếu nhập kho (Goods Receipt).
/// </summary>
public record ReceiveStockCommand(
    string ReferenceDoc,
    long WarehouseId,
    long ProductId,
    int Quantity,
    int MinStock = 0,
    long? UserId = null
) : IStockResourceIdentifier;
