using Inventory.Domain.Common;

namespace Inventory.Domain.Entities;

/// <summary>
/// Bảng tồn kho thực tế (Physical on-hand balance).
/// Lưu số lượng hàng hóa đang thực sự nằm trong kho.
/// </summary>
public class InventoryStock : IStockResourceIdentifier
{
    public long Id { get; set; }
    public long WarehouseId { get; set; }
    public long ProductId { get; set; }
    public int Quantity { get; set; } = 0;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation property
    public Warehouse? Warehouse { get; set; }

    public InventoryStock()
    {
    }

    public InventoryStock(long warehouseId, long productId, int initialQuantity = 0)
    {
        WarehouseId = warehouseId;
        ProductId = productId;
        Quantity = initialQuantity;
        UpdatedAt = DateTime.UtcNow;
    }
}
