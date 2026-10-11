using Inventory.Domain.Common;

namespace Inventory.Domain.Entities;

/// <summary>
/// Bảng tồn kho khả dụng (Available / Reservation balance).
/// Lưu số lượng hàng hóa có sẵn để bán hoặc phân bổ cho đơn hàng.
/// </summary>
public class InventoryStockReservation : IStockResourceIdentifier
{
    public long Id { get; set; }
    public long WarehouseId { get; set; }
    public long ProductId { get; set; }
    public int Quantity { get; set; } = 0;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation property
    public Warehouse? Warehouse { get; set; }

    public InventoryStockReservation()
    {
    }

    public InventoryStockReservation(long warehouseId, long productId, int initialQuantity = 0)
    {
        WarehouseId = warehouseId;
        ProductId = productId;
        Quantity = initialQuantity;
        UpdatedAt = DateTime.UtcNow;
    }
}
