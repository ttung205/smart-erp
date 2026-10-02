using Purchase.Domain.Enums;

namespace Purchase.Domain.Entities;

/// <summary>
/// Quản lý phân bổ nhu cầu từ dòng PR sang dòng PO
/// Đảm bảo tính toàn vẹn 1-n, n-n và cơ chế hoàn trả số lượng khi hủy PO
/// </summary>
public class PurchaseOrderPrAllocation
{
    public long Id { get; set; }
    public long PurchaseOrderId { get; set; }
    public long PurchaseOrderItemId { get; set; }
    public long PurchaseRequisitionId { get; set; }
    public long PurchaseRequisitionItemId { get; set; }
    public int AllocatedQuantity { get; set; }
    public PrAllocationStatus Status { get; set; } = PrAllocationStatus.Active;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation properties
    public PurchaseOrder? PurchaseOrder { get; set; }
    public PurchaseOrderItem? PurchaseOrderItem { get; set; }
    public PurchaseRequisition? PurchaseRequisition { get; set; }
    public PurchaseRequisitionItem? PurchaseRequisitionItem { get; set; }

    public PurchaseOrderPrAllocation()
    {
    }

    public void Release()
    {
        Status = PrAllocationStatus.Released;
        UpdatedAt = DateTime.UtcNow;
    }
}
