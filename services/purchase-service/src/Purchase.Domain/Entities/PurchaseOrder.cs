using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Domain.Entities;

/// <summary>
/// Đại diện cho Đơn mua hàng (Purchase Order - PO)
/// </summary>
public class PurchaseOrder
{
    public long Id { get; set; }
    public string Code { get; set; } = string.Empty; // PO-YYYYMM-XXXX
    public long SupplierId { get; set; }
    public long WarehouseId { get; set; }
    public PurchaseOrderStatus Status { get; set; } = PurchaseOrderStatus.Draft;
    public DateTime OrderDate { get; set; } = DateTime.UtcNow;
    public DateTime? ExpectedDeliveryDate { get; set; }
    public string Currency { get; set; } = "VND";

    public decimal SubtotalAmount { get; set; } = 0m;
    public decimal TaxAmount { get; set; } = 0m;
    public decimal DiscountAmount { get; set; } = 0m;
    public decimal GrandTotal { get; set; } = 0m;

    public string? PaymentTerms { get; set; }
    public string? Notes { get; set; }
    public long CreatedBy { get; set; }
    public long? ApprovedBy { get; set; }
    public DateTime? ApprovedAt { get; set; }

    // Concurrency Token
    public int Version { get; set; } = 1;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation properties
    public ICollection<PurchaseOrderItem> Items { get; set; } = new List<PurchaseOrderItem>();
    public ICollection<PurchaseOrderPrAllocation> Allocations { get; set; } = new List<PurchaseOrderPrAllocation>();

    public PurchaseOrder()
    {
    }

    public void RecalculateTotals()
    {
        SubtotalAmount = Items.Sum(i => i.Subtotal);
        TaxAmount = Items.Sum(i => i.Subtotal * (i.TaxRate / 100m));
        GrandTotal = SubtotalAmount + TaxAmount - DiscountAmount;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Submit()
    {
        if (Status != PurchaseOrderStatus.Draft)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể gửi duyệt đơn PO ở trạng thái Dự thảo (Draft).");

        if (!Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Đơn mua hàng phải có ít nhất 1 dòng hàng.");

        Status = PurchaseOrderStatus.PendingApproval;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Approve(long approvedBy)
    {
        if (Status != PurchaseOrderStatus.PendingApproval)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể phê duyệt đơn PO đang chờ duyệt (PendingApproval).");

        ApprovedBy = approvedBy;
        ApprovedAt = DateTime.UtcNow;
        Status = PurchaseOrderStatus.Approved;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Cancel()
    {
        if (Status == PurchaseOrderStatus.Completed)
            throw new PurchaseDomainException("INVALID_STATE", "Không thể hủy đơn PO đã hoàn tất nhập kho.");

        Status = PurchaseOrderStatus.Cancelled;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Close(string? reason = null)
    {
        if (Status == PurchaseOrderStatus.Completed)
            throw new PurchaseDomainException("INVALID_STATE", "Đơn PO đã ở trạng thái Hoàn tất.");

        if (Status == PurchaseOrderStatus.Cancelled)
            throw new PurchaseDomainException("INVALID_STATE", "Không thể đóng đơn PO đã bị hủy.");

        if (Status == PurchaseOrderStatus.Draft || Status == PurchaseOrderStatus.PendingApproval)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể đóng đơn PO đã được phê duyệt hoặc đang nhập kho dở dang.");

        Status = PurchaseOrderStatus.Completed;
        if (!string.IsNullOrWhiteSpace(reason))
        {
            Notes = string.IsNullOrWhiteSpace(Notes)
                ? $"[Đóng đơn hoàn tất]: {reason}"
                : $"{Notes}\n[Đóng đơn hoàn tất]: {reason}";
        }
        UpdatedAt = DateTime.UtcNow;
    }

    public void RefreshFulfillmentStatus()
    {
        if (!Items.Any()) return;

        var totalOrdered = Items.Sum(i => i.QuantityOrdered);
        var totalReceived = Items.Sum(i => i.QuantityReceived);

        if (totalReceived == 0)
        {
            if (Status != PurchaseOrderStatus.Cancelled && Status != PurchaseOrderStatus.Draft && Status != PurchaseOrderStatus.PendingApproval)
                Status = PurchaseOrderStatus.Approved;
        }
        else if (totalReceived >= totalOrdered)
        {
            Status = PurchaseOrderStatus.Completed;
        }
        else
        {
            Status = PurchaseOrderStatus.PartiallyReceived;
        }

        UpdatedAt = DateTime.UtcNow;
    }
}
