namespace Purchase.Domain.Entities;

public class PurchaseReturnItem
{
    public long Id { get; set; }
    public long PurchaseReturnId { get; set; }
    public long PurchaseOrderItemId { get; set; }
    public long ProductId { get; set; }

    public string Sku { get; set; } = string.Empty;
    public string ProductName { get; set; } = string.Empty;
    public string Unit { get; set; } = "Cái";

    public int QuantityReturned { get; set; }
    public decimal UnitCost { get; set; }
    public string? ReasonDetail { get; set; }

    public decimal Subtotal => QuantityReturned * UnitCost;

    // Navigation properties
    public PurchaseReturn? PurchaseReturn { get; set; }
    public PurchaseOrderItem? PurchaseOrderItem { get; set; }

    public PurchaseReturnItem()
    {
    }
}
