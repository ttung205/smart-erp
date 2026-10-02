using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Domain.Entities;

/// <summary>
/// Đại diện cho Đề nghị bổ sung hàng (Purchase Requisition - PR)
/// Do thủ kho hoặc nhân viên lập khi phát hiện thiếu hụt tồn kho
/// </summary>
public class PurchaseRequisition
{
    public long Id { get; set; }
    public string Code { get; set; } = string.Empty; // PR-YYYYMM-XXXX
    public long WarehouseId { get; set; }
    public long RequestedBy { get; set; }
    public DateTime RequestDate { get; set; } = DateTime.UtcNow;
    public DateTime ExpectedNeededDate { get; set; }
    public RequisitionPriority Priority { get; set; } = RequisitionPriority.Medium;
    public string? Reason { get; set; }
    public string? Notes { get; set; }

    // Tách bạch 2 luồng trạng thái
    public RequisitionApprovalStatus ApprovalStatus { get; set; } = RequisitionApprovalStatus.Draft;
    public RequisitionOrderStatus OrderStatus { get; set; } = RequisitionOrderStatus.NotOrdered;

    public long? ApprovedBy { get; set; }
    public DateTime? ApprovedAt { get; set; }
    public string? RejectedReason { get; set; }

    // Concurrency Token
    public int Version { get; set; } = 1;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation properties
    public ICollection<PurchaseRequisitionItem> Items { get; set; } = new List<PurchaseRequisitionItem>();

    public PurchaseRequisition()
    {
    }

    public void Submit()
    {
        if (ApprovalStatus != RequisitionApprovalStatus.Draft)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể gửi duyệt phiếu ở trạng thái Dự thảo (Draft).");

        if (!Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Phiếu đề nghị phải có ít nhất 1 mặt hàng.");

        ApprovalStatus = RequisitionApprovalStatus.Submitted;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Approve(long approvedBy, Action<PurchaseRequisitionItem>? adjustAction = null)
    {
        if (ApprovalStatus != RequisitionApprovalStatus.Submitted)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể phê duyệt phiếu đang chờ duyệt (Submitted).");

        adjustAction?.Invoke(null!);

        foreach (var item in Items)
        {
            if (item.QuantityApproved <= 0)
                item.QuantityApproved = item.QuantityRequested;
        }

        ApprovedBy = approvedBy;
        ApprovedAt = DateTime.UtcNow;
        ApprovalStatus = RequisitionApprovalStatus.Approved;
        OrderStatus = RequisitionOrderStatus.NotOrdered;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Reject(long rejectedBy, string reason)
    {
        if (ApprovalStatus != RequisitionApprovalStatus.Submitted)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể từ chối phiếu đang chờ duyệt (Submitted).");

        ApprovedBy = rejectedBy;
        ApprovedAt = DateTime.UtcNow;
        ApprovalStatus = RequisitionApprovalStatus.Rejected;
        RejectedReason = reason;
        UpdatedAt = DateTime.UtcNow;
    }

    public void RefreshOrderStatus()
    {
        if (!Items.Any()) return;

        var totalApproved = Items.Sum(i => i.QuantityApproved);
        var totalOrdered = Items.Sum(i => i.QuantityOrdered);

        if (totalOrdered == 0)
            OrderStatus = RequisitionOrderStatus.NotOrdered;
        else if (totalOrdered >= totalApproved)
            OrderStatus = RequisitionOrderStatus.FullyOrdered;
        else
            OrderStatus = RequisitionOrderStatus.PartiallyOrdered;

        UpdatedAt = DateTime.UtcNow;
    }
}
