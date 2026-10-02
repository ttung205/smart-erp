using Purchase.Application.DTOs.GoodsReceipts;

namespace Purchase.Application.Interfaces;

public interface IGoodsReceiptService
{
    Task<GoodsReceiptDto> CreateAsync(CreateGoodsReceiptDto dto, CancellationToken ct = default);
    Task<GoodsReceiptDto> ConfirmAsync(long id, CancellationToken ct = default);
    Task<GoodsReceiptDto?> GetByIdAsync(long id, CancellationToken ct = default);
    Task<List<GoodsReceiptDto>> GetListAsync(long? purchaseOrderId = null, long? warehouseId = null, string? status = null, CancellationToken ct = default);
}
