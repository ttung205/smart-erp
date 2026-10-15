using Microsoft.EntityFrameworkCore;
using Purchase.Application.Common.Events;
using Purchase.Application.DTOs.PurchaseOrders;
using Purchase.Application.Interfaces;
using Purchase.Domain.Entities;
using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Application.Services;

public class PurchaseOrderService : IPurchaseOrderService
{
    private readonly IPurchaseDbContext _context;
    private readonly IOutboxService? _outboxService;

    public PurchaseOrderService(IPurchaseDbContext context, IOutboxService? outboxService = null)
    {
        _context = context;
        _outboxService = outboxService;
    }

    public async Task<PurchaseOrderDto> CreateAsync(CreatePurchaseOrderDto dto, CancellationToken ct = default)
    {
        if (dto.SupplierId <= 0)
            throw new PurchaseDomainException("INVALID_SUPPLIER", "Nhà cung cấp không hợp lệ.");

        if (dto.WarehouseId <= 0)
            throw new PurchaseDomainException("INVALID_WAREHOUSE", "Kho nhận hàng không hợp lệ.");

        if (!dto.Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Đơn mua hàng phải có ít nhất 1 dòng mặt hàng.");

        using var transaction = await _context.BeginTransactionAsync(ct);
        try
        {
            var count = await _context.PurchaseOrders.CountAsync(ct);
            var datePrefix = DateTime.UtcNow.ToString("yyyyMM");
            var code = $"PO-{datePrefix}-{(count + 1):D4}";

            var po = new PurchaseOrder
            {
                Code = code,
                SupplierId = dto.SupplierId,
                WarehouseId = dto.WarehouseId,
                Status = PurchaseOrderStatus.Draft,
                OrderDate = dto.OrderDate ?? DateTime.UtcNow,
                ExpectedDeliveryDate = dto.ExpectedDeliveryDate,
                Currency = string.IsNullOrWhiteSpace(dto.Currency) ? "VND" : dto.Currency,
                PaymentTerms = dto.PaymentTerms,
                Notes = dto.Notes,
                CreatedBy = dto.CreatedBy,
                CreatedAt = DateTime.UtcNow,
                UpdatedAt = DateTime.UtcNow
            };

            var affectedPrIds = new HashSet<long>();

            foreach (var itemDto in dto.Items)
            {
                if (itemDto.QuantityOrdered <= 0)
                    throw new PurchaseDomainException("INVALID_QUANTITY", $"Số lượng đặt của mặt hàng {itemDto.Sku} phải lớn hơn 0.");

                if (itemDto.UnitCost < 0)
                    throw new PurchaseDomainException("INVALID_PRICE", $"Đơn giá của mặt hàng {itemDto.Sku} không được âm.");

                var poItem = new PurchaseOrderItem
                {
                    ProductId = itemDto.ProductId,
                    Sku = itemDto.Sku,
                    ProductName = itemDto.ProductName,
                    Unit = string.IsNullOrWhiteSpace(itemDto.Unit) ? "Cái" : itemDto.Unit,
                    Origin = string.IsNullOrWhiteSpace(itemDto.Origin) ? "Việt Nam" : itemDto.Origin,
                    UnitCost = itemDto.UnitCost,
                    QuantityOrdered = itemDto.QuantityOrdered,
                    QuantityReceived = 0,
                    QuantityInvoiced = 0,
                    TaxRate = itemDto.TaxRate,
                    DiscountPercent = itemDto.DiscountPercent,
                    Notes = itemDto.Notes
                };
                poItem.CalculateSubtotal();
                po.Items.Add(poItem);

                // Xử lý phân bổ nhu cầu từ các dòng PR (nếu có gom từ PR)
                if (itemDto.Allocations != null && itemDto.Allocations.Any())
                {
                    var totalAllocated = itemDto.Allocations.Sum(a => a.AllocatedQuantity);
                    if (totalAllocated != itemDto.QuantityOrdered)
                        throw new PurchaseDomainException("ALLOCATION_MISMATCH",
                            $"Tổng số lượng phân bổ từ PR ({totalAllocated}) phải khớp với số lượng đặt mua ({itemDto.QuantityOrdered}) của mặt hàng {itemDto.Sku}.");

                    foreach (var alloc in itemDto.Allocations)
                    {
                        if (alloc.AllocatedQuantity <= 0) continue;

                        // ATOMIC ALLOCATION GUARD: Cập nhật nguyên tử có điều kiện chặn (Chống 2 nhân viên đặt trùng cùng 1 PR)
                        var rowsAffected = await _context.ExecuteSqlInterpolatedAsync(
                            $"UPDATE purchase_requisition_items SET quantity_ordered = quantity_ordered + {alloc.AllocatedQuantity} WHERE id = {alloc.PrItemId} AND (quantity_approved - quantity_ordered) >= {alloc.AllocatedQuantity}",
                            ct);

                        if (rowsAffected == 0)
                        {
                            throw new PurchaseDomainException("CONCURRENT_CONFLICT",
                                $"Mặt hàng {itemDto.Sku} (PR Item ID = {alloc.PrItemId}) không còn đủ số lượng duyệt khả dụng hoặc vừa được nhân viên khác tạo đơn. Vui lòng tải lại trang.");
                        }

                        // Đồng bộ trạng thái entity trong bộ nhớ nếu đang được EF theo dõi
                        var trackedItem = _context.PurchaseRequisitionItems.Local.FirstOrDefault(x => x.Id == alloc.PrItemId);
                        if (trackedItem != null)
                        {
                            trackedItem.QuantityOrdered += alloc.AllocatedQuantity;
                        }

                        affectedPrIds.Add(alloc.PrId);

                        po.Allocations.Add(new PurchaseOrderPrAllocation
                        {
                            PurchaseOrder = po,
                            PurchaseOrderItem = poItem,
                            PurchaseRequisitionId = alloc.PrId,
                            PurchaseRequisitionItemId = alloc.PrItemId,
                            AllocatedQuantity = alloc.AllocatedQuantity,
                            Status = PrAllocationStatus.Active,
                            CreatedAt = DateTime.UtcNow,
                            UpdatedAt = DateTime.UtcNow
                        });
                    }
                }
            }

            po.RecalculateTotals();
            await _context.PurchaseOrders.AddAsync(po, ct);
            await _context.SaveChangesAsync(ct);

            // Cập nhật lại OrderStatus của các PR bị ảnh hưởng
            foreach (var prId in affectedPrIds)
            {
                var pr = await _context.PurchaseRequisitions
                    .Include(x => x.Items)
                    .FirstOrDefaultAsync(x => x.Id == prId, ct);
                pr?.RefreshOrderStatus();
            }
            await _context.SaveChangesAsync(ct);

            await transaction.CommitAsync(ct);
            return MapToDto(po);
        }
        catch
        {
            await transaction.RollbackAsync(ct);
            throw;
        }
    }

    public async Task<PurchaseOrderDto> SubmitAsync(long id, CancellationToken ct = default)
    {
        var po = await _context.PurchaseOrders
            .Include(x => x.Items)
            .Include(x => x.Allocations)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        if (po == null)
            throw new KeyNotFoundException($"Không tìm thấy đơn mua hàng ID = {id}.");

        po.Submit();
        await _context.SaveChangesAsync(ct);

        return MapToDto(po);
    }

    public async Task<PurchaseOrderDto> ApproveAsync(long id, long approvedBy, CancellationToken ct = default)
    {
        var po = await _context.PurchaseOrders
            .Include(x => x.Items)
            .Include(x => x.Allocations)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        if (po == null)
            throw new KeyNotFoundException($"Không tìm thấy đơn mua hàng ID = {id}.");

        po.Approve(approvedBy);
        await _context.SaveChangesAsync(ct);

        // Ghi sự kiện PurchaseOrderApproved vào Transactional Outbox
        if (_outboxService != null)
        {
            var payload = new PurchaseOrderApprovedPayload
            {
                PoId = po.Id,
                PoCode = po.Code,
                SupplierId = po.SupplierId,
                WarehouseId = po.WarehouseId,
                GrandTotal = po.GrandTotal,
                ApprovedBy = po.ApprovedBy,
                ApprovedAt = po.ApprovedAt,
                Items = po.Items.Select(i => new PurchaseOrderApprovedItemPayload
                {
                    ProductId = i.ProductId,
                    Sku = i.Sku,
                    ProductName = i.ProductName,
                    QuantityOrdered = i.QuantityOrdered,
                    UnitCost = i.UnitCost
                }).ToList()
            };

            await _outboxService.SaveEventAsync(
                routingKey: "purchase.order.approved",
                eventType: "PurchaseOrderApproved",
                aggregateType: "PurchaseOrder",
                aggregateId: po.Code,
                payload: payload,
                ct: ct
            );
        }

        return MapToDto(po);
    }

    public async Task<PurchaseOrderDto> CancelAsync(long id, CancelPurchaseOrderDto dto, CancellationToken ct = default)
    {
        using var transaction = await _context.BeginTransactionAsync(ct);
        try
        {
            var po = await _context.PurchaseOrders
                .Include(x => x.Items)
                .Include(x => x.Allocations)
                .FirstOrDefaultAsync(x => x.Id == id, ct);

            if (po == null)
                throw new KeyNotFoundException($"Không tìm thấy đơn mua hàng ID = {id}.");

            po.Cancel();

            // GIẢI PHÓNG PHÂN BỔ NHU CẦU: Hoàn trả số lượng đặt cho các dòng PR nguồn
            var affectedPrIds = new HashSet<long>();
            foreach (var alloc in po.Allocations.Where(a => a.Status == PrAllocationStatus.Active))
            {
                alloc.Release();
                affectedPrIds.Add(alloc.PurchaseRequisitionId);

                await _context.ExecuteSqlInterpolatedAsync(
                    $"UPDATE purchase_requisition_items SET quantity_ordered = MAX(0, quantity_ordered - {alloc.AllocatedQuantity}) WHERE id = {alloc.PurchaseRequisitionItemId}",
                    ct);

                // Đồng bộ entity trong bộ nhớ nếu đang được EF theo dõi
                var trackedItem = _context.PurchaseRequisitionItems.Local.FirstOrDefault(x => x.Id == alloc.PurchaseRequisitionItemId);
                if (trackedItem != null)
                {
                    trackedItem.QuantityOrdered = Math.Max(0, trackedItem.QuantityOrdered - alloc.AllocatedQuantity);
                }
            }

            await _context.SaveChangesAsync(ct);

            // Cập nhật lại OrderStatus cho PR
            foreach (var prId in affectedPrIds)
            {
                var pr = await _context.PurchaseRequisitions
                    .Include(x => x.Items)
                    .FirstOrDefaultAsync(x => x.Id == prId, ct);
                pr?.RefreshOrderStatus();
            }
            await _context.SaveChangesAsync(ct);

            // Ghi sự kiện PurchaseOrderCancelled vào Transactional Outbox
            if (_outboxService != null)
            {
                var payload = new PurchaseOrderCancelledPayload
                {
                    PoId = po.Id,
                    PoCode = po.Code,
                    CancelledBy = dto.CancelledBy,
                    Reason = dto.Reason,
                    CancelledAt = DateTime.UtcNow
                };

                await _outboxService.SaveEventAsync(
                    routingKey: "purchase.order.cancelled",
                    eventType: "PurchaseOrderCancelled",
                    aggregateType: "PurchaseOrder",
                    aggregateId: po.Code,
                    payload: payload,
                    ct: ct
                );
            }

            await transaction.CommitAsync(ct);
            return MapToDto(po);
        }
        catch
        {
            await transaction.RollbackAsync(ct);
            throw;
        }
    }

    public async Task<PurchaseOrderDto> CloseAsync(long id, ClosePurchaseOrderDto dto, CancellationToken ct = default)
    {
        var po = await _context.PurchaseOrders
            .Include(x => x.Items)
            .Include(x => x.Allocations)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        if (po == null)
            throw new KeyNotFoundException($"Không tìm thấy đơn mua hàng ID = {id}.");

        po.Close(dto.Reason);

        // Giải phóng phân bổ cho phần chênh lệch chưa nhận đủ để PR không bị kẹt
        var affectedPrIds = new HashSet<long>();
        foreach (var item in po.Items)
        {
            var unreceived = Math.Max(0, item.QuantityOrdered - item.QuantityReceived);
            if (unreceived > 0)
            {
                var allocs = po.Allocations
                    .Where(a => a.PurchaseOrderItemId == item.Id && a.Status == PrAllocationStatus.Active)
                    .ToList();

                var remainingToRelease = unreceived;
                foreach (var alloc in allocs)
                {
                    if (remainingToRelease <= 0) break;
                    var releaseQty = Math.Min(alloc.AllocatedQuantity, remainingToRelease);
                    alloc.AllocatedQuantity -= releaseQty;
                    if (alloc.AllocatedQuantity <= 0)
                    {
                        alloc.Release();
                    }
                    remainingToRelease -= releaseQty;
                    affectedPrIds.Add(alloc.PurchaseRequisitionId);

                    await _context.ExecuteSqlInterpolatedAsync(
                        $"UPDATE purchase_requisition_items SET quantity_ordered = MAX(0, quantity_ordered - {releaseQty}) WHERE id = {alloc.PurchaseRequisitionItemId}",
                        ct);

                    var trackedItem = _context.PurchaseRequisitionItems.Local.FirstOrDefault(x => x.Id == alloc.PurchaseRequisitionItemId);
                    if (trackedItem != null)
                    {
                        trackedItem.QuantityOrdered = Math.Max(0, trackedItem.QuantityOrdered - releaseQty);
                    }
                }
            }
        }

        await _context.SaveChangesAsync(ct);

        foreach (var prId in affectedPrIds)
        {
            var pr = await _context.PurchaseRequisitions
                .Include(x => x.Items)
                .FirstOrDefaultAsync(x => x.Id == prId, ct);

            if (pr != null)
            {
                pr.RefreshOrderStatus();
            }
        }

        await _context.SaveChangesAsync(ct);
        return MapToDto(po);
    }

    public async Task<PurchaseOrderDto?> GetByIdAsync(long id, CancellationToken ct = default)
    {
        var po = await _context.PurchaseOrders
            .AsNoTracking()
            .Include(x => x.Items)
            .Include(x => x.Allocations)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        return po != null ? MapToDto(po) : null;
    }

    public async Task<List<PurchaseOrderDto>> GetListAsync(long? supplierId = null, long? warehouseId = null, string? status = null, CancellationToken ct = default)
    {
        var query = _context.PurchaseOrders
            .AsNoTracking()
            .Include(x => x.Items)
            .Include(x => x.Allocations)
            .AsQueryable();

        if (supplierId.HasValue)
            query = query.Where(x => x.SupplierId == supplierId.Value);

        if (warehouseId.HasValue)
            query = query.Where(x => x.WarehouseId == warehouseId.Value);

        if (!string.IsNullOrWhiteSpace(status) && Enum.TryParse<PurchaseOrderStatus>(status, true, out var orderStatus))
            query = query.Where(x => x.Status == orderStatus);

        var list = await query.OrderByDescending(x => x.CreatedAt).ToListAsync(ct);
        return list.Select(MapToDto).ToList();
    }

    private static PurchaseOrderDto MapToDto(PurchaseOrder entity)
    {
        return new PurchaseOrderDto
        {
            Id = entity.Id,
            Code = entity.Code,
            SupplierId = entity.SupplierId,
            WarehouseId = entity.WarehouseId,
            Status = entity.Status.ToString(),
            OrderDate = entity.OrderDate,
            ExpectedDeliveryDate = entity.ExpectedDeliveryDate,
            Currency = entity.Currency,
            SubtotalAmount = entity.SubtotalAmount,
            TaxAmount = entity.TaxAmount,
            DiscountAmount = entity.DiscountAmount,
            GrandTotal = entity.GrandTotal,
            PaymentTerms = entity.PaymentTerms,
            Notes = entity.Notes,
            CreatedBy = entity.CreatedBy,
            ApprovedBy = entity.ApprovedBy,
            ApprovedAt = entity.ApprovedAt,
            CreatedAt = entity.CreatedAt,
            Items = entity.Items.Select(i => new PurchaseOrderItemDto
            {
                Id = i.Id,
                ProductId = i.ProductId,
                Sku = i.Sku,
                ProductName = i.ProductName,
                Unit = i.Unit,
                Origin = i.Origin,
                UnitCost = i.UnitCost,
                QuantityOrdered = i.QuantityOrdered,
                QuantityReceived = i.QuantityReceived,
                QuantityInvoiced = i.QuantityInvoiced,
                TaxRate = i.TaxRate,
                DiscountPercent = i.DiscountPercent,
                Subtotal = i.Subtotal,
                Notes = i.Notes
            }).ToList(),
            Allocations = entity.Allocations.Select(a => new PurchaseOrderPrAllocationDto
            {
                Id = a.Id,
                PurchaseOrderId = a.PurchaseOrderId,
                PurchaseOrderItemId = a.PurchaseOrderItemId,
                PurchaseRequisitionId = a.PurchaseRequisitionId,
                PurchaseRequisitionItemId = a.PurchaseRequisitionItemId,
                AllocatedQuantity = a.AllocatedQuantity,
                Status = a.Status.ToString()
            }).ToList()
        };
    }
}
