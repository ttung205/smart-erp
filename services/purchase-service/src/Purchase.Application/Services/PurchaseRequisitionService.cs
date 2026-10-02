using Microsoft.EntityFrameworkCore;
using Purchase.Application.DTOs.Requisitions;
using Purchase.Application.Interfaces;
using Purchase.Domain.Entities;
using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Application.Services;

public class PurchaseRequisitionService : IPurchaseRequisitionService
{
    private readonly IPurchaseDbContext _context;

    public PurchaseRequisitionService(IPurchaseDbContext context)
    {
        _context = context;
    }

    public async Task<PurchaseRequisitionDto> CreateAsync(CreatePurchaseRequisitionDto dto, CancellationToken ct = default)
    {
        if (dto.WarehouseId <= 0)
            throw new PurchaseDomainException("INVALID_WAREHOUSE", "Mã kho yêu cầu không hợp lệ.");

        if (!dto.Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Phiếu đề nghị mua hàng phải có ít nhất 1 mặt hàng.");

        var count = await _context.PurchaseRequisitions.CountAsync(ct);
        var datePrefix = DateTime.UtcNow.ToString("yyyyMM");
        var code = $"PR-{datePrefix}-{(count + 1):D4}";

        var priority = Enum.TryParse<RequisitionPriority>(dto.Priority, true, out var p) ? p : RequisitionPriority.Medium;

        var pr = new PurchaseRequisition
        {
            Code = code,
            WarehouseId = dto.WarehouseId,
            RequestedBy = dto.RequestedBy,
            RequestDate = DateTime.UtcNow,
            ExpectedNeededDate = dto.ExpectedNeededDate,
            Priority = priority,
            Reason = dto.Reason,
            Notes = dto.Notes,
            ApprovalStatus = RequisitionApprovalStatus.Draft,
            OrderStatus = RequisitionOrderStatus.NotOrdered,
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow
        };

        foreach (var itemDto in dto.Items)
        {
            if (itemDto.QuantityRequested <= 0)
                throw new PurchaseDomainException("INVALID_QUANTITY", $"Số lượng đề nghị của mặt hàng {itemDto.Sku} phải lớn hơn 0.");

            pr.Items.Add(new PurchaseRequisitionItem
            {
                ProductId = itemDto.ProductId,
                Sku = itemDto.Sku,
                ProductName = itemDto.ProductName,
                Unit = string.IsNullOrWhiteSpace(itemDto.Unit) ? "Cái" : itemDto.Unit,
                QuantityRequested = itemDto.QuantityRequested,
                QuantityApproved = 0,
                QuantityOrdered = 0,
                EstimatedUnitCost = itemDto.EstimatedUnitCost,
                Notes = itemDto.Notes
            });
        }

        await _context.PurchaseRequisitions.AddAsync(pr, ct);
        await _context.SaveChangesAsync(ct);

        return MapToDto(pr);
    }

    public async Task<PurchaseRequisitionDto> SubmitAsync(long id, CancellationToken ct = default)
    {
        var pr = await _context.PurchaseRequisitions
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        if (pr == null)
            throw new KeyNotFoundException($"Không tìm thấy phiếu đề nghị mua hàng ID = {id}.");

        pr.Submit();
        await _context.SaveChangesAsync(ct);

        return MapToDto(pr);
    }

    public async Task<PurchaseRequisitionDto> ApproveAsync(long id, ApprovePurchaseRequisitionDto dto, CancellationToken ct = default)
    {
        var pr = await _context.PurchaseRequisitions
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        if (pr == null)
            throw new KeyNotFoundException($"Không tìm thấy phiếu đề nghị mua hàng ID = {id}.");

        if (pr.ApprovalStatus != RequisitionApprovalStatus.Submitted)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể phê duyệt phiếu đang ở trạng thái Chờ duyệt (Submitted).");

        // Cập nhật số lượng phê duyệt theo chỉ định của Quản lý
        if (dto.Items != null && dto.Items.Any())
        {
            foreach (var adjust in dto.Items)
            {
                var item = pr.Items.FirstOrDefault(i => i.Id == adjust.ItemId);
                if (item != null)
                {
                    if (adjust.QuantityApproved < 0)
                        throw new PurchaseDomainException("INVALID_QUANTITY", "Số lượng duyệt không được là số âm.");

                    if (adjust.QuantityApproved > item.QuantityRequested)
                        throw new PurchaseDomainException("QUANTITY_OVER_REQUESTED", 
                            $"Số lượng duyệt ({adjust.QuantityApproved}) không được vượt quá số lượng đề xuất ({item.QuantityRequested}) của mặt hàng {item.Sku}.");

                    item.QuantityApproved = adjust.QuantityApproved;
                }
            }
        }
        else
        {
            // Mặc định duyệt bằng số lượng đề xuất nếu không điều chỉnh
            foreach (var item in pr.Items)
            {
                item.QuantityApproved = item.QuantityRequested;
            }
        }

        pr.Approve(dto.ApprovedBy);
        await _context.SaveChangesAsync(ct);

        return MapToDto(pr);
    }

    public async Task<PurchaseRequisitionDto> RejectAsync(long id, RejectPurchaseRequisitionDto dto, CancellationToken ct = default)
    {
        var pr = await _context.PurchaseRequisitions
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        if (pr == null)
            throw new KeyNotFoundException($"Không tìm thấy phiếu đề nghị mua hàng ID = {id}.");

        pr.Reject(dto.RejectedBy, dto.Reason);
        await _context.SaveChangesAsync(ct);

        return MapToDto(pr);
    }

    public async Task<PurchaseRequisitionDto?> GetByIdAsync(long id, CancellationToken ct = default)
    {
        var pr = await _context.PurchaseRequisitions
            .AsNoTracking()
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        return pr != null ? MapToDto(pr) : null;
    }

    public async Task<List<PurchaseRequisitionDto>> GetListAsync(long? warehouseId = null, string? approvalStatus = null, string? orderStatus = null, CancellationToken ct = default)
    {
        var query = _context.PurchaseRequisitions
            .AsNoTracking()
            .Include(x => x.Items)
            .AsQueryable();

        if (warehouseId.HasValue)
            query = query.Where(x => x.WarehouseId == warehouseId.Value);

        if (!string.IsNullOrWhiteSpace(approvalStatus) && Enum.TryParse<RequisitionApprovalStatus>(approvalStatus, true, out var appStatus))
            query = query.Where(x => x.ApprovalStatus == appStatus);

        if (!string.IsNullOrWhiteSpace(orderStatus) && Enum.TryParse<RequisitionOrderStatus>(orderStatus, true, out var ordStatus))
            query = query.Where(x => x.OrderStatus == ordStatus);

        var list = await query.OrderByDescending(x => x.CreatedAt).ToListAsync(ct);
        return list.Select(MapToDto).ToList();
    }

    public async Task<List<ApprovedPrItemForOrderingDto>> GetApprovedItemsAvailableForOrderingAsync(long? warehouseId = null, CancellationToken ct = default)
    {
        var query = _context.PurchaseRequisitions
            .AsNoTracking()
            .Include(x => x.Items)
            .Where(x => x.ApprovalStatus == RequisitionApprovalStatus.Approved 
                     && x.OrderStatus != RequisitionOrderStatus.FullyOrdered)
            .AsQueryable();

        if (warehouseId.HasValue)
            query = query.Where(x => x.WarehouseId == warehouseId.Value);

        var prs = await query.ToListAsync(ct);

        var availableItems = new List<ApprovedPrItemForOrderingDto>();
        foreach (var pr in prs)
        {
            foreach (var item in pr.Items.Where(i => i.AvailableToOrder > 0))
            {
                availableItems.Add(new ApprovedPrItemForOrderingDto
                {
                    PrId = pr.Id,
                    PrCode = pr.Code,
                    PrItemId = item.Id,
                    WarehouseId = pr.WarehouseId,
                    ProductId = item.ProductId,
                    Sku = item.Sku,
                    ProductName = item.ProductName,
                    Unit = item.Unit,
                    QuantityApproved = item.QuantityApproved,
                    QuantityOrdered = item.QuantityOrdered,
                    AvailableToOrder = item.AvailableToOrder,
                    EstimatedUnitCost = item.EstimatedUnitCost,
                    ExpectedNeededDate = pr.ExpectedNeededDate
                });
            }
        }

        return availableItems;
    }

    private static PurchaseRequisitionDto MapToDto(PurchaseRequisition entity)
    {
        return new PurchaseRequisitionDto
        {
            Id = entity.Id,
            Code = entity.Code,
            WarehouseId = entity.WarehouseId,
            RequestedBy = entity.RequestedBy,
            RequestDate = entity.RequestDate,
            ExpectedNeededDate = entity.ExpectedNeededDate,
            Priority = entity.Priority.ToString(),
            Reason = entity.Reason,
            Notes = entity.Notes,
            ApprovalStatus = entity.ApprovalStatus.ToString(),
            OrderStatus = entity.OrderStatus.ToString(),
            ApprovedBy = entity.ApprovedBy,
            ApprovedAt = entity.ApprovedAt,
            RejectedReason = entity.RejectedReason,
            CreatedAt = entity.CreatedAt,
            Items = entity.Items.Select(i => new PurchaseRequisitionItemDto
            {
                Id = i.Id,
                ProductId = i.ProductId,
                Sku = i.Sku,
                ProductName = i.ProductName,
                Unit = i.Unit,
                QuantityRequested = i.QuantityRequested,
                QuantityApproved = i.QuantityApproved,
                QuantityOrdered = i.QuantityOrdered,
                AvailableToOrder = i.AvailableToOrder,
                EstimatedUnitCost = i.EstimatedUnitCost,
                Notes = i.Notes
            }).ToList()
        };
    }
}
