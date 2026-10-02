namespace Purchase.Domain.Entities;

public class GoodsReceiptItem
{
    public long Id { get; set; }
    public long GoodsReceiptId { get; set; }
    public long PurchaseOrderItemId { get; set; }
    public long ProductId { get; set; }

    // Snapshotting
    public string Sku { get; set; } = string.Empty;
    public string ProductName { get; set; } = string.Empty;
    public string Unit { get; set; } = "Cái";

    public int QuantityOrdered { get; set; }
    public int QuantityReceived { get; set; }
    public int QuantityRejected { get; set; } = 0;
    public decimal UnitCost { get; set; }
    public string? BatchNumber { get; set; }
    public DateTime? ExpiryDate { get; set; }
    public string? Notes { get; set; }

    // Navigation properties
    public GoodsReceipt? GoodsReceipt { get; set; }
    public PurchaseOrderItem? PurchaseOrderItem { get; set; }

    public GoodsReceiptItem()
    {
    }
}
