using Microsoft.EntityFrameworkCore;
using Purchase.Application.Common.Events;
using Purchase.Application.DTOs.PurchaseReturns;
using Purchase.Application.Interfaces;
using Purchase.Domain.Entities;
using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Application.Services;

public class PurchaseReturnService : IPurchaseReturnService
{
    private readonly IPurchaseDbContext _context;
    private readonly IOutboxService? _outboxService;

    public PurchaseReturnService(IPurchaseDbContext context, IOutboxService? outboxService = null)
    {
        _context = context;
        _outboxService = outboxService;
    }

    public async Task<List<PurchaseReturnDto>> GetListAsync(long? supplierId = null, string? status = null, CancellationToken ct = default)
    {
        var query = _context.PurchaseReturns
            .AsNoTracking()
            .Include(x => x.PurchaseOrder)
            .Include(x => x.GoodsReceipt)
            .Include(x => x.Items)
            .AsQueryable();

        if (supplierId.HasValue)
            query = query.Where(x => x.SupplierId == supplierId.Value);

        if (!string.IsNullOrWhiteSpace(status) && Enum.TryParse<PurchaseReturnStatus>(status, true, out var returnStatus))
            query = query.Where(x => x.Status == returnStatus);

        var list = await query.OrderByDescending(x => x.CreatedAt).ToListAsync(ct);
        return list.Select(MapToDto).ToList();
    }

    public async Task<PurchaseReturnDto?> GetByIdAsync(long id, CancellationToken ct = default)
    {
        var item = await _context.PurchaseReturns
            .AsNoTracking()
            .Include(x => x.PurchaseOrder)
            .Include(x => x.GoodsReceipt)
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        return item != null ? MapToDto(item) : null;
    }

    public async Task<PurchaseReturnDto> CreateAsync(CreatePurchaseReturnDto dto, CancellationToken ct = default)
    {
        if (dto.PurchaseOrderId <= 0)
            throw new PurchaseDomainException("INVALID_PO", "Đơn mua hàng không hợp lệ.");

        if (!dto.Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Phiếu trả hàng phải có ít nhất 1 dòng mặt hàng.");

        var po = await _context.PurchaseOrders
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == dto.PurchaseOrderId, ct);

        if (po == null)
            throw new PurchaseDomainException("PO_NOT_FOUND", $"Không tìm thấy đơn mua hàng ID {dto.PurchaseOrderId}.");

        var count = await _context.PurchaseReturns.CountAsync(ct);
        var datePrefix = DateTime.UtcNow.ToString("yyyyMM");
        var code = $"PRTN-{datePrefix}-{(count + 1):D4}";

        if (!Enum.TryParse<PurchaseReturnReason>(dto.Reason, true, out var reason))
        {
            reason = PurchaseReturnReason.Defective;
        }

        var prtn = new PurchaseReturn
        {
            Code = code,
            PurchaseOrderId = dto.PurchaseOrderId,
            GoodsReceiptId = dto.GoodsReceiptId,
            SupplierId = dto.SupplierId > 0 ? dto.SupplierId : po.SupplierId,
            WarehouseId = dto.WarehouseId > 0 ? dto.WarehouseId : po.WarehouseId,
            RmaNumber = dto.RmaNumber,
            Status = PurchaseReturnStatus.Draft,
            Reason = reason,
            Notes = dto.Notes,
            CreatedBy = dto.CreatedBy,
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow
        };

        foreach (var itemDto in dto.Items)
        {
            if (itemDto.QuantityReturned <= 0)
                continue;

            var poItem = po.Items.FirstOrDefault(x => x.Id == itemDto.PurchaseOrderItemId);
            if (poItem == null)
                throw new PurchaseDomainException("PO_ITEM_NOT_FOUND", $"Dòng PO item ID {itemDto.PurchaseOrderItemId} không thuộc đơn hàng {po.Code}.");

            var returnItem = new PurchaseReturnItem
            {
                PurchaseOrderItemId = poItem.Id,
                ProductId = poItem.ProductId,
                Sku = poItem.Sku,
                ProductName = poItem.ProductName,
                Unit = poItem.Unit,
                QuantityReturned = itemDto.QuantityReturned,
                UnitCost = poItem.UnitCost,
                ReasonDetail = itemDto.ReasonDetail
            };

            prtn.Items.Add(returnItem);
        }

        if (!prtn.Items.Any())
            throw new PurchaseDomainException("NO_VALID_RETURN_ITEMS", "Vui lòng nhập số lượng trả lại lớn hơn 0 cho ít nhất 1 mặt hàng.");

        prtn.RecalculateTotal();
        prtn.PurchaseOrder = po;

        _context.PurchaseReturns.Add(prtn);
        await _context.SaveChangesAsync(ct);

        return MapToDto(prtn);
    }

    public async Task<PurchaseReturnDto> ApproveAsync(long id, long approvedBy, CancellationToken ct = default)
    {
        var prtn = await _context.PurchaseReturns
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        if (prtn == null)
            throw new PurchaseDomainException("RETURN_NOT_FOUND", $"Không tìm thấy phiếu trả hàng ID {id}.");

        prtn.Approve(approvedBy);
        await _context.SaveChangesAsync(ct);

        return MapToDto(prtn);
    }

    public async Task<PurchaseReturnDto> DispatchAsync(long id, CancellationToken ct = default)
    {
        using var transaction = await _context.BeginTransactionAsync(ct);
        try
        {
            var prtn = await _context.PurchaseReturns
                .Include(x => x.Items)
                .FirstOrDefaultAsync(x => x.Id == id, ct);

            if (prtn == null)
                throw new PurchaseDomainException("RETURN_NOT_FOUND", $"Không tìm thấy phiếu trả hàng ID {id}.");

            var po = await _context.PurchaseOrders
                .Include(x => x.Items)
                .FirstOrDefaultAsync(x => x.Id == prtn.PurchaseOrderId, ct);

            if (po == null)
                throw new PurchaseDomainException("PO_NOT_FOUND", $"Không tìm thấy đơn mua hàng ID {prtn.PurchaseOrderId}.");

            // Chuyển trạng thái xuất kho
            prtn.Dispatch();

            // Trừ giảm số lượng đã nhận trên từng dòng PO
            foreach (var rItem in prtn.Items)
            {
                var poItem = po.Items.FirstOrDefault(x => x.Id == rItem.PurchaseOrderItemId);
                if (poItem != null)
                {
                    poItem.QuantityReceived = Math.Max(0, poItem.QuantityReceived - rItem.QuantityReturned);
                }
            }

            // Cập nhật lại tiến độ PO
            po.RefreshFulfillmentStatus();
            po.UpdatedAt = DateTime.UtcNow;

            await _context.SaveChangesAsync(ct);

            // Ghi sự kiện Outbox để giảm tồn OnHand trong inventory-service
            if (_outboxService != null)
            {
                var payload = new
                {
                    ReturnId = prtn.Code,
                    PoCode = po.Code,
                    WarehouseId = prtn.WarehouseId,
                    SupplierId = prtn.SupplierId,
                    DispatchedAt = prtn.DispatchedAt,
                    Items = prtn.Items.Select(i => new
                    {
                        ProductId = i.ProductId,
                        Sku = i.Sku,
                        QuantityReturned = i.QuantityReturned
                    }).ToList()
                };

                await _outboxService.SaveEventAsync(
                    routingKey: "purchase.return.dispatched",
                    eventType: "PurchaseReturnDispatched",
                    aggregateType: "PurchaseReturn",
                    aggregateId: prtn.Code,
                    payload: payload,
                    ct: ct
                );
            }

            await transaction.CommitAsync(ct);

            return MapToDto(prtn);
        }
        catch
        {
            await transaction.RollbackAsync(ct);
            throw;
        }
    }

    public async Task<PurchaseReturnDto> CancelAsync(long id, CancellationToken ct = default)
    {
        var prtn = await _context.PurchaseReturns
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        if (prtn == null)
            throw new PurchaseDomainException("RETURN_NOT_FOUND", $"Không tìm thấy phiếu trả hàng ID {id}.");

        prtn.Cancel();
        await _context.SaveChangesAsync(ct);

        return MapToDto(prtn);
    }

    private static PurchaseReturnDto MapToDto(PurchaseReturn entity)
    {
        return new PurchaseReturnDto
        {
            Id = entity.Id,
            Code = entity.Code,
            PurchaseOrderId = entity.PurchaseOrderId,
            PoCode = entity.PurchaseOrder?.Code ?? string.Empty,
            GoodsReceiptId = entity.GoodsReceiptId,
            GoodsReceiptCode = entity.GoodsReceipt?.Code,
            SupplierId = entity.SupplierId,
            WarehouseId = entity.WarehouseId,
            RmaNumber = entity.RmaNumber,
            Status = entity.Status.ToString(),
            Reason = entity.Reason.ToString(),
            Notes = entity.Notes,
            TotalAmount = entity.TotalAmount,
            CreatedBy = entity.CreatedBy,
            ApprovedBy = entity.ApprovedBy,
            ApprovedAt = entity.ApprovedAt,
            DispatchedAt = entity.DispatchedAt,
            CreatedAt = entity.CreatedAt,
            Items = entity.Items.Select(i => new PurchaseReturnItemDto
            {
                Id = i.Id,
                PurchaseOrderItemId = i.PurchaseOrderItemId,
                ProductId = i.ProductId,
                Sku = i.Sku,
                ProductName = i.ProductName,
                Unit = i.Unit,
                QuantityReturned = i.QuantityReturned,
                UnitCost = i.UnitCost,
                Subtotal = i.Subtotal,
                ReasonDetail = i.ReasonDetail
            }).ToList()
        };
    }
}
