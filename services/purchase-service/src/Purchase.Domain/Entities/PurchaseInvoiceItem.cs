namespace Purchase.Domain.Entities;

public class PurchaseInvoiceItem
{
    public long Id { get; set; }
    public long PurchaseInvoiceId { get; set; }
    public long PurchaseOrderItemId { get; set; }
    public long? GoodsReceiptItemId { get; set; }
    public long ProductId { get; set; }

    public string Sku { get; set; } = string.Empty;
    public string ProductName { get; set; } = string.Empty;
    public string Unit { get; set; } = "Cái";
    public int QuantityInvoiced { get; set; }
    public decimal UnitCost { get; set; }
    public decimal TaxRate { get; set; } = 10.00m;
    public decimal Subtotal { get; set; }
    public string MatchingStatus { get; set; } = "MATCHED"; // MATCHED, PRICE_DIFF, QTY_DIFF

    // Navigation properties
    public PurchaseInvoice? PurchaseInvoice { get; set; }
    public PurchaseOrderItem? PurchaseOrderItem { get; set; }
    public GoodsReceiptItem? GoodsReceiptItem { get; set; }

    public PurchaseInvoiceItem()
    {
    }

    public void CalculateSubtotal()
    {
        Subtotal = QuantityInvoiced * UnitCost;
    }
}
