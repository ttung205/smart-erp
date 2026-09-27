namespace Inventory.Domain.Exceptions;

/// <summary>
/// Ném ra khi tham số hoặc ngữ cảnh thao tác tồn kho không hợp lệ (ví dụ: số lượng <= 0, giải phóng nhiều hơn số đã giữ).
/// </summary>
public class InvalidStockOperationException : InventoryDomainException
{
    public InvalidStockOperationException(string message) 
        : base("INVALID_STOCK_OPERATION", message)
    {
    }

    public InvalidStockOperationException(string errorCode, string message) 
        : base(errorCode, message)
    {
    }
}
