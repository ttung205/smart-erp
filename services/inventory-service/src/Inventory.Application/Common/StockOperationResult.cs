namespace Inventory.Application.Common;

/// <summary>
/// Kết quả sau khi thực hiện thao tác biến động tồn kho.
/// </summary>
public class StockOperationResult
{
    public long ProductId { get; set; }
    public long WarehouseId { get; set; }
    public int QuantityOnHand { get; set; }
    public int QuantityReserved { get; set; }
    public int Available => QuantityOnHand - QuantityReserved;
    public string OperationType { get; set; } = string.Empty;
    public int QuantityDelta { get; set; }
    public string ReferenceDoc { get; set; } = string.Empty;
    public long MovementId { get; set; }
    public long? ReservationId { get; set; }
    public DateTime Timestamp { get; set; } = DateTime.UtcNow;
}
