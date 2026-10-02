using Purchase.Application.DTOs.Requisitions;

namespace Purchase.Application.Interfaces;

public interface IPurchaseRequisitionService
{
    Task<PurchaseRequisitionDto> CreateAsync(CreatePurchaseRequisitionDto dto, CancellationToken ct = default);
    Task<PurchaseRequisitionDto> SubmitAsync(long id, CancellationToken ct = default);
    Task<PurchaseRequisitionDto> ApproveAsync(long id, ApprovePurchaseRequisitionDto dto, CancellationToken ct = default);
    Task<PurchaseRequisitionDto> RejectAsync(long id, RejectPurchaseRequisitionDto dto, CancellationToken ct = default);
    Task<PurchaseRequisitionDto?> GetByIdAsync(long id, CancellationToken ct = default);
    Task<List<PurchaseRequisitionDto>> GetListAsync(long? warehouseId = null, string? approvalStatus = null, string? orderStatus = null, CancellationToken ct = default);
    Task<List<ApprovedPrItemForOrderingDto>> GetApprovedItemsAvailableForOrderingAsync(long? warehouseId = null, CancellationToken ct = default);
}
