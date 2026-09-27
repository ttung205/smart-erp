using Inventory.Domain.Common;
using Inventory.Domain.Exceptions;

namespace Inventory.Domain.Entities;

/// <summary>
/// Đại diện cho bản ghi tồn kho của một sản phẩm tại một kho cụ thể.
/// Chịu trách nhiệm bảo toàn các bất biến SCM (Zero Overselling, Không âm kho, Available >= 0).
/// </summary>
public class InventoryStock : IStockResourceIdentifier
{
    public long Id { get; set; }
    public long ProductId { get; set; }
    public long WarehouseId { get; set; }
    public int QuantityOnHand { get; set; } = 0;
    public int QuantityReserved { get; set; } = 0;
    public int MinStock { get; set; } = 0;
    
    // Concurrency Token for Optimistic Locking
    public int Version { get; set; } = 1;
    
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // SCM Invariant Computed Property
    public int Available => QuantityOnHand - QuantityReserved;

    // Navigation property
    public Warehouse? Warehouse { get; set; }

    public InventoryStock()
    {
    }

    public InventoryStock(long productId, long warehouseId, int initialOnHand = 0, int minStock = 0)
    {
        if (productId <= 0)
            throw new InvalidStockOperationException("ProductId phải lớn hơn 0.");
        if (warehouseId <= 0)
            throw new InvalidStockOperationException("WarehouseId phải lớn hơn 0.");
        if (initialOnHand < 0)
            throw new NegativeStockException(productId, warehouseId, nameof(QuantityOnHand), initialOnHand);
        if (minStock < 0)
            throw new InvalidStockOperationException("MinStock không được là số âm.");

        ProductId = productId;
        WarehouseId = warehouseId;
        QuantityOnHand = initialOnHand;
        QuantityReserved = 0;
        MinStock = minStock;
        Version = 1;
        CreatedAt = DateTime.UtcNow;
        UpdatedAt = DateTime.UtcNow;

        ValidateInvariants();
    }

    /// <summary>
    /// Kiểm tra các ràng buộc bất biến cốt lõi của chuỗi cung ứng (SCM Invariants).
    /// </summary>
    public void ValidateInvariants()
    {
        if (QuantityOnHand < 0)
            throw new NegativeStockException(ProductId, WarehouseId, nameof(QuantityOnHand), QuantityOnHand);

        if (QuantityReserved < 0)
            throw new NegativeStockException(ProductId, WarehouseId, nameof(QuantityReserved), QuantityReserved);

        if (QuantityOnHand < QuantityReserved)
            throw new NegativeStockException(ProductId, WarehouseId, "Available", Available);
    }

    /// <summary>
    /// Kiểm tra xem tồn kho khả dụng có đủ để giữ chỗ với số lượng yêu cầu hay không.
    /// </summary>
    public bool CanReserve(int quantity)
    {
        return quantity > 0 && Available >= quantity;
    }

    /// <summary>
    /// Giữ hàng nguyên tử cho đơn bán hàng (Sales Order Reservation).
    /// Tăng QuantityReserved mà không làm thay đổi QuantityOnHand.
    /// </summary>
    public void Reserve(int quantity)
    {
        if (quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng giữ hàng phải lớn hơn 0 (nhận: {quantity}).");

        if (Available < quantity)
            throw new InsufficientStockException(ProductId, WarehouseId, quantity, Available);

        QuantityReserved += quantity;
        UpdatedAt = DateTime.UtcNow;

        ValidateInvariants();
    }

    /// <summary>
    /// Giải phóng số lượng đã giữ chỗ khi đơn hàng bị hủy hoặc hết hạn (Release Reservation).
    /// </summary>
    public void Release(int quantity)
    {
        if (quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng giải phóng phải lớn hơn 0 (nhận: {quantity}).");

        if (quantity > QuantityReserved)
            throw new InvalidStockOperationException(
                $"Không thể giải phóng {quantity} món vì số lượng đang giữ chỗ chỉ là {QuantityReserved} (Sản phẩm ID {ProductId}, Kho ID {WarehouseId}).");

        QuantityReserved -= quantity;
        UpdatedAt = DateTime.UtcNow;

        ValidateInvariants();
    }

    /// <summary>
    /// Nhập kho thực tế từ đơn mua hàng PO (Goods Receipt).
    /// Tăng QuantityOnHand.
    /// </summary>
    public void Receive(int quantity)
    {
        if (quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng nhập kho phải lớn hơn 0 (nhận: {quantity}).");

        QuantityOnHand += quantity;
        UpdatedAt = DateTime.UtcNow;

        ValidateInvariants();
    }

    /// <summary>
    /// Xuất kho thực tế cho đơn bán hàng đã qua giữ chỗ (Goods Issue / Delivery).
    /// Giảm đồng thời cả QuantityOnHand và QuantityReserved.
    /// </summary>
    public void Issue(int quantity)
    {
        if (quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng xuất kho phải lớn hơn 0 (nhận: {quantity}).");

        if (quantity > QuantityReserved)
            throw new InvalidStockOperationException(
                $"Số lượng xuất kho ({quantity}) vượt quá số lượng đã giữ chỗ ({QuantityReserved}) cho sản phẩm ID {ProductId} tại kho ID {WarehouseId}.");

        if (quantity > QuantityOnHand)
            throw new InsufficientStockException(ProductId, WarehouseId, quantity, QuantityOnHand);

        QuantityOnHand -= quantity;
        QuantityReserved -= quantity;
        UpdatedAt = DateTime.UtcNow;

        ValidateInvariants();
    }

    /// <summary>
    /// Xuất kho trực tiếp không qua giữ chỗ (ví dụ xuất hao hụt, xuất hàng mẫu).
    /// Giảm trực tiếp QuantityOnHand và phải đảm bảo Available >= quantity.
    /// </summary>
    public void DirectIssue(int quantity)
    {
        if (quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng xuất kho trực tiếp phải lớn hơn 0 (nhận: {quantity}).");

        if (Available < quantity)
            throw new InsufficientStockException(ProductId, WarehouseId, quantity, Available);

        QuantityOnHand -= quantity;
        UpdatedAt = DateTime.UtcNow;

        ValidateInvariants();
    }
}
