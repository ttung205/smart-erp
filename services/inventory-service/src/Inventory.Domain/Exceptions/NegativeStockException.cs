namespace Inventory.Domain.Exceptions;

/// <summary>
/// Ném ra khi một thao tác vi phạm quy tắc SCM bất biến: không bao giờ cho phép tồn kho âm (OnHand < 0, Reserved < 0 hoặc Available < 0).
/// </summary>
public class NegativeStockException : InventoryDomainException
{
    public long ProductId { get; }
    public long WarehouseId { get; }
    public string FieldName { get; }
    public int AttemptedValue { get; }

    public NegativeStockException(long productId, long warehouseId, string fieldName, int attemptedValue)
        : base(
            "NEGATIVE_STOCK",
            $"Vi phạm ràng buộc tồn kho bất biến: Trường '{fieldName}' không được phép mang giá trị âm ({attemptedValue}) cho sản phẩm ID {productId} tại kho ID {warehouseId}.")
    {
        ProductId = productId;
        WarehouseId = warehouseId;
        FieldName = fieldName;
        AttemptedValue = attemptedValue;
    }
}
