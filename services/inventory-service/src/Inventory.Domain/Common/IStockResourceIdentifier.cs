namespace Inventory.Domain.Common;

/// <summary>
/// Interface định danh tài nguyên kho vật lý (WarehouseId, ProductId).
/// Dùng làm cơ sở để xác định thứ tự khóa tài nguyên (Resource Lock Ordering),
/// triệt tiêu Deadlock phân tán và tranh chấp dòng đồng thời.
/// </summary>
public interface IStockResourceIdentifier
{
    long WarehouseId { get; }
    long ProductId { get; }
}
