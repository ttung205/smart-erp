using Inventory.Application.Common;
using Inventory.Application.Common.Commands;
using Inventory.Domain.Entities;

namespace Inventory.Application.Interfaces;

public interface IInventoryStockManager
{
    Task<StockOperationResult> ChangePhysicalStockAsync(ChangeStockCommand command, CancellationToken ct = default);
    Task<StockOperationResult> ChangeReservationStockAsync(ChangeStockCommand command, CancellationToken ct = default);
    Task<StockOperationResult> ReceiveStockAsync(ReceiveStockCommand command, CancellationToken ct = default);
    Task<StockOperationResult> ReserveStockAsync(ReserveStockCommand command, CancellationToken ct = default);
    Task<IReadOnlyList<StockOperationResult>> BatchReserveStockAsync(BatchReserveStockCommand command, CancellationToken ct = default);
    Task<StockOperationResult> ReleaseStockAsync(ReleaseStockCommand command, CancellationToken ct = default);
    Task<IReadOnlyList<StockOperationResult>> ReleaseDocumentReservationAsync(string documentId, long? userId = null, CancellationToken ct = default);
    Task<IReadOnlyList<OpenDocumentReservation>> GetOpenDocumentReservationsAsync(DateTime reservedFrom, DateTime reservedTo, CancellationToken ct = default);
    Task<StockOperationResult> IssueStockAsync(IssueStockCommand command, CancellationToken ct = default);
    Task<InventoryStock?> GetPhysicalStockAsync(long productId, long warehouseId, CancellationToken ct = default);
    Task<InventoryStockReservation?> GetReservationStockAsync(long productId, long warehouseId, CancellationToken ct = default);
}
