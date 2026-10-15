using Purchase.Application.DTOs.PurchaseReturns;

namespace Purchase.Application.Interfaces;

public interface IPurchaseReturnService
{
    Task<List<PurchaseReturnDto>> GetListAsync(long? supplierId = null, string? status = null, CancellationToken ct = default);
    Task<PurchaseReturnDto?> GetByIdAsync(long id, CancellationToken ct = default);
    Task<PurchaseReturnDto> CreateAsync(CreatePurchaseReturnDto dto, CancellationToken ct = default);
    Task<PurchaseReturnDto> UpdateDraftAsync(long id, UpdatePurchaseReturnDto dto, CancellationToken ct = default);
    Task<PurchaseReturnDto> SubmitAsync(long id, SubmitPurchaseReturnDto? dto = null, CancellationToken ct = default);
    Task<PurchaseReturnDto> ApproveAsync(long id, long approvedBy, CancellationToken ct = default);
    Task<PurchaseReturnDto> DispatchAsync(long id, CancellationToken ct = default);
    Task<PurchaseReturnDto> CancelAsync(long id, CancellationToken ct = default);
}
