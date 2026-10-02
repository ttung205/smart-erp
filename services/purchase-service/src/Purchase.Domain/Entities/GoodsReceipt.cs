using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Domain.Entities;

/// <summary>
/// Đại diện cho Phiếu nhập kho (Goods Receipt Note - GRN)
/// Quản lý việc kiểm đếm và tiếp nhận hàng hóa thực tế tại cổng kho
/// </summary>
public class GoodsReceipt
{
    public long Id { get; set; }
    public string Code { get; set; } = string.Empty; // GR-YYYYMM-XXXX
    public long PurchaseOrderId { get; set; }
    public long WarehouseId { get; set; }
    public long ReceivedBy { get; set; }
    public DateTime ReceivedAt { get; set; } = DateTime.UtcNow;
    public string? DeliveryNoteNumber { get; set; }
    public GoodsReceiptStatus Status { get; set; } = GoodsReceiptStatus.Draft;
    public GoodsReceiptSyncStatus InventorySyncStatus { get; set; } = GoodsReceiptSyncStatus.PendingSync;
    public string? Notes { get; set; }

    public int Version { get; set; } = 1;
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation properties
    public PurchaseOrder? PurchaseOrder { get; set; }
    public ICollection<GoodsReceiptItem> Items { get; set; } = new List<GoodsReceiptItem>();

    public GoodsReceipt()
    {
    }

    public void Confirm()
    {
        if (Status != GoodsReceiptStatus.Draft)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể xác nhận phiếu nhập kho ở trạng thái Dự thảo (Draft).");

        if (!Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Phiếu nhập kho phải có ít nhất 1 dòng kiểm nhận.");

        var hasValidQuantity = Items.Any(i => i.QuantityReceived > 0);
        if (!hasValidQuantity)
            throw new PurchaseDomainException("NO_GOODS_RECEIVED", "Tổng số lượng thực nhận phải lớn hơn 0.");

        Status = GoodsReceiptStatus.Confirmed;
        UpdatedAt = DateTime.UtcNow;
    }

    public void MarkSynced()
    {
        InventorySyncStatus = GoodsReceiptSyncStatus.Synced;
        UpdatedAt = DateTime.UtcNow;
    }

    public void MarkSyncFailed()
    {
        InventorySyncStatus = GoodsReceiptSyncStatus.Failed;
        UpdatedAt = DateTime.UtcNow;
    }
}
