using Purchase.Application.DTOs.PurchaseOrders;

namespace Purchase.Application.Interfaces;

public interface IPurchaseOrderService
{
    Task<PurchaseOrderDto> CreateAsync(CreatePurchaseOrderDto dto, CancellationToken ct = default);
    Task<PurchaseOrderDto> SubmitAsync(long id, CancellationToken ct = default);
    Task<PurchaseOrderDto> ApproveAsync(long id, long approvedBy, CancellationToken ct = default);
    Task<PurchaseOrderDto> CancelAsync(long id, CancelPurchaseOrderDto dto, CancellationToken ct = default);
    Task<PurchaseOrderDto?> GetByIdAsync(long id, CancellationToken ct = default);
    Task<List<PurchaseOrderDto>> GetListAsync(long? supplierId = null, long? warehouseId = null, string? status = null, CancellationToken ct = default);
}
