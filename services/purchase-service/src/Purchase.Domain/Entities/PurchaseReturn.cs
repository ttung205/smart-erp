using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Domain.Entities;

/// <summary>
/// Đại diện cho Phiếu trả hàng mua cho Nhà cung cấp (Purchase Return / Return to Vendor - RTV)
/// </summary>
public class PurchaseReturn
{
    public long Id { get; set; }
    public string Code { get; set; } = string.Empty; // PRTN-YYYYMM-XXXX
    public long PurchaseOrderId { get; set; }
    public long? GoodsReceiptId { get; set; }
    public long SupplierId { get; set; }
    public long WarehouseId { get; set; }
    public string? RmaNumber { get; set; } // Return Merchandise Authorization
    public PurchaseReturnStatus Status { get; set; } = PurchaseReturnStatus.Draft;
    public PurchaseReturnReason Reason { get; set; } = PurchaseReturnReason.Defective;
    public string? Notes { get; set; }
    public decimal TotalAmount { get; set; } = 0m;

    public long CreatedBy { get; set; }
    public long? ApprovedBy { get; set; }
    public DateTime? ApprovedAt { get; set; }
    public DateTime? DispatchedAt { get; set; }

    public int Version { get; set; } = 1;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation properties
    public PurchaseOrder? PurchaseOrder { get; set; }
    public GoodsReceipt? GoodsReceipt { get; set; }
    public ICollection<PurchaseReturnItem> Items { get; set; } = new List<PurchaseReturnItem>();

    public PurchaseReturn()
    {
    }

    public void RecalculateTotal()
    {
        TotalAmount = Items.Sum(i => i.QuantityReturned * i.UnitCost);
        UpdatedAt = DateTime.UtcNow;
    }

    public void Submit()
    {
        if (Status != PurchaseReturnStatus.Draft)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể gửi duyệt phiếu trả hàng ở trạng thái Dự thảo (Draft).");

        if (!Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Phiếu trả hàng phải có ít nhất 1 dòng mặt hàng.");

        if (Items.All(i => i.QuantityReturned <= 0))
            throw new PurchaseDomainException("INVALID_QUANTITY", "Số lượng trả hàng phải lớn hơn 0.");

        Status = PurchaseReturnStatus.Submitted;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Approve(long approvedBy)
    {
        if (Status != PurchaseReturnStatus.Submitted && Status != PurchaseReturnStatus.Draft)
            throw new PurchaseDomainException("INVALID_STATE", "Phiếu trả hàng không ở trạng thái có thể phê duyệt.");

        ApprovedBy = approvedBy;
        ApprovedAt = DateTime.UtcNow;
        Status = PurchaseReturnStatus.Approved;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Dispatch()
    {
        if (Status != PurchaseReturnStatus.Approved && Status != PurchaseReturnStatus.Submitted && Status != PurchaseReturnStatus.Draft)
            throw new PurchaseDomainException("INVALID_STATE", "Phiếu trả hàng không ở trạng thái hợp lệ để xuất kho.");

        Status = PurchaseReturnStatus.Dispatched;
        DispatchedAt = DateTime.UtcNow;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Complete()
    {
        Status = PurchaseReturnStatus.Completed;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Cancel()
    {
        if (Status == PurchaseReturnStatus.Dispatched || Status == PurchaseReturnStatus.Completed)
            throw new PurchaseDomainException("INVALID_STATE", "Không thể hủy phiếu trả hàng đã xuất kho hoặc đã hoàn tất.");

        Status = PurchaseReturnStatus.Cancelled;
        UpdatedAt = DateTime.UtcNow;
    }
}
