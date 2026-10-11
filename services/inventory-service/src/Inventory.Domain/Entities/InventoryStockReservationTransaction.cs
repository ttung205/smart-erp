using Inventory.Domain.Common;

namespace Inventory.Domain.Entities;

/// <summary>
/// Sổ cái giao dịch tồn kho khả dụng (Available / Reservation Transaction Ledger).
/// Ghi chép bất biến mọi biến động giữ chỗ, hoàn trả, phân bổ cho đơn hàng/kế hoạch.
/// </summary>
public class InventoryStockReservationTransaction : IStockResourceIdentifier
{
    public long Id { get; set; }
    public long WarehouseId { get; set; }
    public long ProductId { get; set; }
    public string DocumentType { get; set; } = string.Empty;
    public string DocumentId { get; set; } = string.Empty;
    public int BalanceBefore { get; set; }
    public int QuantityDelta { get; set; }
    public int BalanceAfter { get; set; }
    public string? Notes { get; set; }
    public long? CreatedBy { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    // Navigation property
    public Warehouse? Warehouse { get; set; }
}
