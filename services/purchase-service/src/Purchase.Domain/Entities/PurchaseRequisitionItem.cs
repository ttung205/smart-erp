using Purchase.Domain.Exceptions;

namespace Purchase.Domain.Entities;

public class PurchaseRequisitionItem
{
    public long Id { get; set; }
    public long PurchaseRequisitionId { get; set; }
    public long ProductId { get; set; }

    // Data Snapshotting từ Catalog Service
    public string Sku { get; set; } = string.Empty;
    public string ProductName { get; set; } = string.Empty;
    public string Unit { get; set; } = "Cái";
    public string? Origin { get; set; } = "Việt Nam";

    public int QuantityRequested { get; set; }
    public int QuantityApproved { get; set; } = 0;
    public int QuantityOrdered { get; set; } = 0;
    public decimal? EstimatedUnitCost { get; set; }
    public string? Notes { get; set; }

    // Computed: Số lượng còn khả dụng để phân bổ sang đơn PO
    public int AvailableToOrder => Math.Max(0, QuantityApproved - QuantityOrdered);

    // Navigation property
    public PurchaseRequisition? PurchaseRequisition { get; set; }

    public PurchaseRequisitionItem()
    {
    }

    public void AllocateToOrder(int quantity)
    {
        if (quantity <= 0)
            throw new PurchaseDomainException("INVALID_QUANTITY", "Số lượng phân bổ phải lớn hơn 0.");

        if (quantity > AvailableToOrder)
            throw new PurchaseDomainException("PR_QUANTITY_EXCEEDED", 
                $"Số lượng đặt ({quantity}) vượt quá số lượng duyệt còn lại ({AvailableToOrder}) của mặt hàng {Sku}.");

        QuantityOrdered += quantity;
    }

    public void ReleaseAllocation(int quantity)
    {
        if (quantity <= 0) return;
        QuantityOrdered = Math.Max(0, QuantityOrdered - quantity);
    }
}
