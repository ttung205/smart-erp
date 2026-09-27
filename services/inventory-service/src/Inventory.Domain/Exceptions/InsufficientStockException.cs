namespace Inventory.Domain.Exceptions;

/// <summary>
/// Ném ra khi số lượng tồn kho khả dụng (Available = OnHand - Reserved) không đủ để đáp ứng yêu cầu giữ hàng/xuất hàng.
/// </summary>
public class InsufficientStockException : InventoryDomainException
{
    public long ProductId { get; }
    public long WarehouseId { get; }
    public int RequestedQuantity { get; }
    public int AvailableQuantity { get; }

    public InsufficientStockException(long productId, long warehouseId, int requestedQuantity, int availableQuantity)
        : base(
            "INSUFFICIENT_STOCK",
            $"Số lượng tồn kho khả dụng không đủ cho sản phẩm ID {productId} tại kho ID {warehouseId}. Yêu cầu: {requestedQuantity}, Khả dụng: {availableQuantity}.")
    {
        ProductId = productId;
        WarehouseId = warehouseId;
        RequestedQuantity = requestedQuantity;
        AvailableQuantity = availableQuantity;
    }
}
