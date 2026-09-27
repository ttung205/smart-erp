using Inventory.Domain.Common;

namespace Inventory.Domain.Entities;

public class StockMovement : IStockResourceIdentifier
{
    public long Id { get; set; }
    public long ProductId { get; set; }
    public long WarehouseId { get; set; }
    
    // Movement Types: PO_RECEIPT, SO_RESERVE, SO_ISSUE, SO_RELEASE, ADJUSTMENT, TRANSFER_IN, TRANSFER_OUT
    public string MovementType { get; set; } = string.Empty;
    public int QuantityDelta { get; set; }
    public int BalanceOnHandAfter { get; set; }
    public int BalanceReservedAfter { get; set; }
    
    // Reference document: SO-xxx, PO-xxx, GR-xxx, GI-xxx
    public string ReferenceDoc { get; set; } = string.Empty;
    public long? CreatedBy { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    // Navigation property
    public Warehouse? Warehouse { get; set; }
}
