using Purchase.Domain.Exceptions;

namespace Purchase.Domain.Entities;

public class PurchaseOrderItem
{
    public long Id { get; set; }
    public long PurchaseOrderId { get; set; }
    public long ProductId { get; set; }

    // Data Snapshotting
    public string Sku { get; set; } = string.Empty;
    public string ProductName { get; set; } = string.Empty;
    public string Unit { get; set; } = "Cái";
    public decimal UnitCost { get; set; }

    public int QuantityOrdered { get; set; }
    public int QuantityReceived { get; set; } = 0;
    public int QuantityInvoiced { get; set; } = 0;
    public decimal TaxRate { get; set; } = 10.00m;
    public decimal DiscountPercent { get; set; } = 0.00m;
    public decimal Subtotal { get; set; }
    public string? Notes { get; set; }

    // Navigation properties
    public PurchaseOrder? PurchaseOrder { get; set; }
    public ICollection<PurchaseOrderPrAllocation> Allocations { get; set; } = new List<PurchaseOrderPrAllocation>();

    public PurchaseOrderItem()
    {
    }

    public void CalculateSubtotal()
    {
        var lineTotal = QuantityOrdered * UnitCost;
        var discount = lineTotal * (DiscountPercent / 100m);
        Subtotal = lineTotal - discount;
    }

    public void RegisterReceived(int quantity)
    {
        if (quantity < 0)
            throw new PurchaseDomainException("INVALID_QUANTITY", "Số lượng nhận không được âm.");

        if (QuantityReceived + quantity > QuantityOrdered)
            throw new PurchaseDomainException("OVER_RECEIPT_NOT_ALLOWED",
                $"Số lượng thực nhận lũy kế ({QuantityReceived + quantity}) vượt quá số lượng đặt mua ({QuantityOrdered}) của mặt hàng {Sku}.");

        QuantityReceived += quantity;
    }
}
