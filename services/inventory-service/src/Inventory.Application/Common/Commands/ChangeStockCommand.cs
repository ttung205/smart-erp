namespace Inventory.Application.Common.Commands;

public record ChangeStockCommand(
    long WarehouseId,
    long ProductId,
    int QuantityDelta,
    string DocumentType,
    string DocumentId,
    string? Notes = null,
    long? CreatedBy = null
);
