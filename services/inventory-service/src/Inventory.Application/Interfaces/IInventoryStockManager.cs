using Inventory.Application.Common;
using Inventory.Application.Common.Commands;
using Inventory.Domain.Entities;

namespace Inventory.Application.Interfaces;

/// <summary>
/// Interface điều phối các thao tác quản lý tồn kho nguyên tử (Concurrency Engine),
/// chống Overselling và tự động ghi sổ cái thẻ kho bất biến (Immutable Stock Ledger).
/// </summary>
public interface IInventoryStockManager
{
    Task<StockOperationResult> ReserveStockAsync(ReserveStockCommand command, CancellationToken ct = default);
    Task<IReadOnlyList<StockOperationResult>> BatchReserveStockAsync(BatchReserveStockCommand command, CancellationToken ct = default);
    Task<StockOperationResult> ReleaseStockAsync(ReleaseStockCommand command, CancellationToken ct = default);
    Task<StockOperationResult> ReceiveStockAsync(ReceiveStockCommand command, CancellationToken ct = default);
    Task<StockOperationResult> IssueStockAsync(IssueStockCommand command, CancellationToken ct = default);
    Task<InventoryStock?> GetStockAsync(long productId, long warehouseId, CancellationToken ct = default);
}
