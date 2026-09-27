using Inventory.Domain.Common;

namespace Inventory.Domain.Entities;

public class StockReservation : IStockResourceIdentifier
{
    public long Id { get; set; }
    public string OrderId { get; set; } = string.Empty;
    public long WarehouseId { get; set; }
    public long ProductId { get; set; }
    public int Quantity { get; set; }
    public string Status { get; set; } = "ACTIVE"; // ACTIVE, CONFIRMED, RELEASED, FULFILLED
    public DateTime? ExpiresAt { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation property
    public Warehouse? Warehouse { get; set; }
}
