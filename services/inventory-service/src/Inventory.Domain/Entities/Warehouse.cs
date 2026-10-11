namespace Inventory.Domain.Entities;

public class Warehouse
{
    public long Id { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string? Location { get; set; }
    public long? ManagerId { get; set; }
    public bool IsActive { get; set; } = true;
    public bool CanSell { get; set; } = true;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation properties
    public ICollection<InventoryStock> Stocks { get; set; } = new List<InventoryStock>();
    public ICollection<InventoryStockReservation> Reservations { get; set; } = new List<InventoryStockReservation>();
}
