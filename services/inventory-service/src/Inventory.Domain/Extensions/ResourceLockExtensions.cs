using Inventory.Domain.Common;

namespace Inventory.Domain.Extensions;

public static class ResourceLockExtensions
{
    /// <summary>
    /// Sắp xếp danh sách tài nguyên tồn kho theo thứ tự tăng dần xác định (WarehouseId ASC, ProductId ASC).
    /// </summary>
    public static IReadOnlyList<T> ToDeterministicLockOrder<T>(this IEnumerable<T> items)
        where T : IStockResourceIdentifier
    {
        ArgumentNullException.ThrowIfNull(items);

        return items
            .OrderBy(x => x.WarehouseId)
            .ThenBy(x => x.ProductId)
            .ToList()
            .AsReadOnly();
    }

    /// <summary>
    /// Sắp xếp danh sách bất kỳ theo thứ tự tăng dần xác định thông qua bộ chọn (Selectors).
    /// </summary>
    public static IReadOnlyList<T> ToDeterministicLockOrder<T>(
        this IEnumerable<T> items,
        Func<T, long> warehouseIdSelector,
        Func<T, long> productIdSelector)
    {
        ArgumentNullException.ThrowIfNull(items);
        ArgumentNullException.ThrowIfNull(warehouseIdSelector);
        ArgumentNullException.ThrowIfNull(productIdSelector);

        return items
            .OrderBy(warehouseIdSelector)
            .ThenBy(productIdSelector)
            .ToList()
            .AsReadOnly();
    }

    /// <summary>
    /// Tạo khóa định danh duy nhất cho cặp (WarehouseId, ProductId) nhằm phục vụ logging hoặc hash mapping.
    /// </summary>
    public static string ToResourceLockKey(this IStockResourceIdentifier item)
    {
        ArgumentNullException.ThrowIfNull(item);
        return $"wh:{item.WarehouseId}:prod:{item.ProductId}";
    }

    /// <summary>
    /// Tạo chuỗi khóa định danh dạng tài nguyên từ tham số rời.
    /// </summary>
    public static string ToResourceLockKey(long warehouseId, long productId)
    {
        return $"wh:{warehouseId}:prod:{productId}";
    }
}
