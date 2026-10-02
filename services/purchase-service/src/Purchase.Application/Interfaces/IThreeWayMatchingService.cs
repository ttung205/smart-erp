using Purchase.Application.DTOs.Invoices;

namespace Purchase.Application.Interfaces;

public interface IThreeWayMatchingService
{
    Task<PurchaseInvoiceDto> CreateInvoiceAsync(CreatePurchaseInvoiceDto dto, CancellationToken ct = default);
    Task<ThreeWayMatchingResultDto> MatchInvoiceAsync(long invoiceId, CancellationToken ct = default);
    Task<PurchaseInvoiceDto> PostInvoiceAsync(long invoiceId, CancellationToken ct = default);
    Task<PurchaseInvoiceDto?> GetByIdAsync(long id, CancellationToken ct = default);
    Task<List<PurchaseInvoiceDto>> GetListAsync(long? purchaseOrderId = null, long? supplierId = null, string? status = null, CancellationToken ct = default);
}
