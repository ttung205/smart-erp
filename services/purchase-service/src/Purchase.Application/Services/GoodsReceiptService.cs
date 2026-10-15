using Microsoft.EntityFrameworkCore;
using Purchase.Application.Common.Events;
using Purchase.Application.DTOs.GoodsReceipts;
using Purchase.Application.Interfaces;
using Purchase.Domain.Entities;
using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Application.Services;

public class GoodsReceiptService : IGoodsReceiptService
{
    private readonly IPurchaseDbContext _context;
    private readonly IOutboxService? _outboxService;

    public GoodsReceiptService(IPurchaseDbContext context, IOutboxService? outboxService = null)
    {
        _context = context;
        _outboxService = outboxService;
    }

    public async Task<GoodsReceiptDto> CreateAsync(CreateGoodsReceiptDto dto, CancellationToken ct = default)
    {
        if (dto.PurchaseOrderId <= 0)
            throw new PurchaseDomainException("INVALID_PO", "Đơn mua hàng không hợp lệ.");

        if (dto.WarehouseId <= 0)
            throw new PurchaseDomainException("INVALID_WAREHOUSE", "Kho nhận hàng không hợp lệ.");

        if (!dto.Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Phiếu nhập kho phải có ít nhất 1 dòng mặt hàng.");

        var po = await _context.PurchaseOrders
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == dto.PurchaseOrderId, ct);

        if (po == null)
            throw new PurchaseDomainException("PO_NOT_FOUND", $"Không tìm thấy đơn mua hàng ID {dto.PurchaseOrderId}.");

        if (po.Status != PurchaseOrderStatus.Approved && po.Status != PurchaseOrderStatus.PartiallyReceived)
        {
            throw new PurchaseDomainException("INVALID_PO_STATUS",
                $"Chỉ có thể tạo phiếu nhập kho cho đơn hàng đã duyệt hoặc đang giao dở dang (Trạng thái hiện tại: {po.Status}).");
        }

        var count = await _context.GoodsReceipts.CountAsync(ct);
        var datePrefix = DateTime.UtcNow.ToString("yyyyMM");
        var code = $"GR-{datePrefix}-{(count + 1):D4}";

        var grn = new GoodsReceipt
        {
            Code = code,
            PurchaseOrderId = dto.PurchaseOrderId,
            WarehouseId = dto.WarehouseId,
            ReceivedBy = dto.ReceivedBy,
            ReceivedAt = dto.ReceivedAt ?? DateTime.UtcNow,
            DeliveryNoteNumber = dto.DeliveryNoteNumber,
            Status = GoodsReceiptStatus.Draft,
            InventorySyncStatus = GoodsReceiptSyncStatus.PendingSync,
            Notes = dto.Notes,
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow
        };

        foreach (var itemDto in dto.Items)
        {
            if (itemDto.QuantityReceived < 0 || itemDto.QuantityRejected < 0 || (itemDto.QuantityReceived == 0 && itemDto.QuantityRejected == 0))
                throw new PurchaseDomainException("INVALID_QUANTITY", $"Mặt hàng {itemDto.Sku}: Số lượng thực nhận hoặc số lượng trả lại phải lớn hơn 0.");

            var poItem = po.Items.FirstOrDefault(x => x.Id == itemDto.PurchaseOrderItemId);
            if (poItem == null)
            {
                throw new PurchaseDomainException("PO_ITEM_NOT_FOUND",
                    $"Dòng PO item ID {itemDto.PurchaseOrderItemId} không thuộc đơn hàng {po.Code}.");
            }

            var remainingReceivable = poItem.QuantityOrdered - poItem.QuantityReceived;
            if ((itemDto.QuantityReceived + itemDto.QuantityRejected) > remainingReceivable)
            {
                throw new PurchaseDomainException("OVER_RECEIPT_NOT_ALLOWED",
                    $"Mặt hàng {poItem.Sku}: Tổng số lượng thực nhận ({itemDto.QuantityReceived}) và trả lại ({itemDto.QuantityRejected}) vượt quá số lượng còn lại ({remainingReceivable}). Đã đặt: {poItem.QuantityOrdered}, Đã nhận trước đó: {poItem.QuantityReceived}.");
            }

            var grnItem = new GoodsReceiptItem
            {
                PurchaseOrderItemId = poItem.Id,
                ProductId = poItem.ProductId,
                Sku = poItem.Sku,
                ProductName = poItem.ProductName,
                Unit = poItem.Unit,
                QuantityOrdered = poItem.QuantityOrdered,
                QuantityReceived = itemDto.QuantityReceived,
                QuantityRejected = itemDto.QuantityRejected,
                UnitCost = poItem.UnitCost,
                BatchNumber = itemDto.BatchNumber,
                ExpiryDate = itemDto.ExpiryDate,
                Notes = itemDto.Notes
            };

            grn.Items.Add(grnItem);
        }

        grn.PurchaseOrder = po;
        _context.GoodsReceipts.Add(grn);
        await _context.SaveChangesAsync(ct);

        return MapToDto(grn);
    }

    public async Task<GoodsReceiptDto> ConfirmAsync(long id, CancellationToken ct = default)
    {
        using var transaction = await _context.BeginTransactionAsync(ct);
        try
        {
            var grn = await _context.GoodsReceipts
                .Include(x => x.Items)
                .FirstOrDefaultAsync(x => x.Id == id, ct);

            if (grn == null)
                throw new PurchaseDomainException("GRN_NOT_FOUND", $"Không tìm thấy phiếu nhập kho ID {id}.");

            var po = await _context.PurchaseOrders
                .Include(x => x.Items)
                .FirstOrDefaultAsync(x => x.Id == grn.PurchaseOrderId, ct);

            if (po == null)
                throw new PurchaseDomainException("PO_NOT_FOUND", $"Không tìm thấy đơn mua hàng ID {grn.PurchaseOrderId}.");

            // Chuyển trạng thái phiếu nhập kho sang Confirmed
            grn.Confirm();
            grn.PurchaseOrder = po;

            // Cập nhật số lượng đã nhận trên từng dòng PO
            foreach (var grItem in grn.Items)
            {
                var poItem = po.Items.FirstOrDefault(x => x.Id == grItem.PurchaseOrderItemId);
                if (poItem == null)
                    throw new PurchaseDomainException("PO_ITEM_NOT_FOUND", $"Không tìm thấy dòng PO Item {grItem.PurchaseOrderItemId}.");

                var remainingReceivable = poItem.QuantityOrdered - poItem.QuantityReceived;
                if (grItem.QuantityReceived > remainingReceivable)
                {
                    throw new PurchaseDomainException("OVER_RECEIPT_NOT_ALLOWED",
                        $"Mặt hàng {poItem.Sku} bị nhận vượt mức cho phép. Đã đặt: {poItem.QuantityOrdered}, Đã nhận: {poItem.QuantityReceived}, Phiếu này nhận: {grItem.QuantityReceived}.");
                }

                poItem.QuantityReceived += grItem.QuantityReceived;
            }

            // Cập nhật trạng thái của PO dựa trên tiến độ giao hàng
            var totalOrdered = po.Items.Sum(x => x.QuantityOrdered);
            var totalReceived = po.Items.Sum(x => x.QuantityReceived);

            if (totalReceived >= totalOrdered)
            {
                po.Status = PurchaseOrderStatus.Received;
            }
            else if (totalReceived > 0)
            {
                po.Status = PurchaseOrderStatus.PartiallyReceived;
            }

            po.UpdatedAt = DateTime.UtcNow;

            // Tự động tạo Phiếu trả hàng (PurchaseReturn) ở trạng thái Draft nếu có mặt hàng bị trả lại tại cửa kho
            PurchaseReturn? autoReturn = null;
            var rejectedItems = grn.Items.Where(x => x.QuantityRejected > 0).ToList();
            if (rejectedItems.Any())
            {
                var existingReturn = await _context.PurchaseReturns.FirstOrDefaultAsync(x => x.GoodsReceiptId == grn.Id, ct);
                if (existingReturn == null)
                {
                    var returnCount = await _context.PurchaseReturns.CountAsync(ct);
                    var datePrefix = DateTime.UtcNow.ToString("yyyyMM");
                    var returnCode = $"PRTN-{datePrefix}-{(returnCount + 1):D4}";

                    autoReturn = new PurchaseReturn
                    {
                        Code = returnCode,
                        PurchaseOrderId = po.Id,
                        GoodsReceiptId = grn.Id,
                        SupplierId = po.SupplierId,
                        WarehouseId = grn.WarehouseId,
                        RmaNumber = null,
                        Status = PurchaseReturnStatus.Draft,
                        Reason = PurchaseReturnReason.Defective,
                        Notes = $"Tự động tạo từ phiếu nhập kho {grn.Code} cho hàng bị trả lại tại cửa kho.",
                        CreatedBy = grn.ReceivedBy,
                        CreatedAt = DateTime.UtcNow,
                        UpdatedAt = DateTime.UtcNow
                    };

                    foreach (var rej in rejectedItems)
                    {
                        autoReturn.Items.Add(new PurchaseReturnItem
                        {
                            PurchaseOrderItemId = rej.PurchaseOrderItemId,
                            ProductId = rej.ProductId,
                            Sku = rej.Sku,
                            ProductName = rej.ProductName,
                            Unit = rej.Unit,
                            QuantityReturned = rej.QuantityRejected,
                            UnitCost = rej.UnitCost,
                            ReasonDetail = "Trả lại ngay khi kiểm đếm tại cửa kho"
                        });
                    }

                    autoReturn.RecalculateTotal();
                    _context.PurchaseReturns.Add(autoReturn);
                }
                else
                {
                    autoReturn = existingReturn;
                }
            }

            await _context.SaveChangesAsync(ct);

            // Ghi sự kiện GoodsReceived vào Transactional Outbox (chỉ tăng tồn OnHand cho SL thực nhận)
            if (_outboxService != null)
            {
                var payload = new GoodsReceivedPayload
                {
                    ReceiptId = grn.Code,
                    PoId = po.Code,
                    WarehouseId = grn.WarehouseId,
                    ReceivedBy = grn.ReceivedBy,
                    ReceivedAt = grn.ReceivedAt,
                    Items = grn.Items.Select(i => new GoodsReceivedItemPayload
                    {
                        ProductId = i.ProductId,
                        QuantityReceived = i.QuantityReceived
                    }).ToList()
                };

                await _outboxService.SaveEventAsync(
                    routingKey: "purchase.goods.received",
                    eventType: "GoodsReceived",
                    aggregateType: "GoodsReceipt",
                    aggregateId: grn.Code,
                    payload: payload,
                    ct: ct
                );
            }

            await transaction.CommitAsync(ct);

            var resultDto = MapToDto(grn);
            if (autoReturn != null)
            {
                resultDto.AutoPurchaseReturnId = autoReturn.Id;
                resultDto.AutoPurchaseReturnCode = autoReturn.Code;
                resultDto.AutoPurchaseReturnQty = rejectedItems.Sum(x => x.QuantityRejected);
            }

            return resultDto;
        }
        catch
        {
            await transaction.RollbackAsync(ct);
            throw;
        }
    }

    public async Task<GoodsReceiptDto?> GetByIdAsync(long id, CancellationToken ct = default)
    {
        var grn = await _context.GoodsReceipts
            .AsNoTracking()
            .Include(x => x.PurchaseOrder)
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        return grn != null ? MapToDto(grn) : null;
    }

    public async Task<List<GoodsReceiptDto>> GetListAsync(long? purchaseOrderId = null, long? warehouseId = null, string? status = null, CancellationToken ct = default)
    {
        var query = _context.GoodsReceipts
            .AsNoTracking()
            .Include(x => x.PurchaseOrder)
            .Include(x => x.Items)
            .AsQueryable();

        if (purchaseOrderId.HasValue)
            query = query.Where(x => x.PurchaseOrderId == purchaseOrderId.Value);

        if (warehouseId.HasValue)
            query = query.Where(x => x.WarehouseId == warehouseId.Value);

        if (!string.IsNullOrWhiteSpace(status) && Enum.TryParse<GoodsReceiptStatus>(status, true, out var receiptStatus))
            query = query.Where(x => x.Status == receiptStatus);

        var list = await query.OrderByDescending(x => x.CreatedAt).ToListAsync(ct);
        return list.Select(MapToDto).ToList();
    }

    private static GoodsReceiptDto MapToDto(GoodsReceipt entity)
    {
        return new GoodsReceiptDto
        {
            Id = entity.Id,
            Code = entity.Code,
            PurchaseOrderId = entity.PurchaseOrderId,
            PoCode = entity.PurchaseOrder?.Code ?? string.Empty,
            WarehouseId = entity.WarehouseId,
            ReceivedBy = entity.ReceivedBy,
            ReceivedAt = entity.ReceivedAt,
            DeliveryNoteNumber = entity.DeliveryNoteNumber,
            Status = entity.Status.ToString(),
            InventorySyncStatus = entity.InventorySyncStatus.ToString(),
            Notes = entity.Notes,
            CreatedAt = entity.CreatedAt,
            Items = entity.Items.Select(i => new GoodsReceiptItemDto
            {
                Id = i.Id,
                PurchaseOrderItemId = i.PurchaseOrderItemId,
                ProductId = i.ProductId,
                Sku = i.Sku,
                ProductName = i.ProductName,
                Unit = i.Unit,
                QuantityOrdered = i.QuantityOrdered,
                QuantityReceived = i.QuantityReceived,
                QuantityRejected = i.QuantityRejected,
                UnitCost = i.UnitCost,
                BatchNumber = i.BatchNumber,
                ExpiryDate = i.ExpiryDate,
                Notes = i.Notes
            }).ToList()
        };
    }
}
