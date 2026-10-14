using Microsoft.EntityFrameworkCore;
using Purchase.Application.DTOs.Invoices;
using Purchase.Application.Interfaces;
using Purchase.Domain.Entities;
using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Application.Services;

public class ThreeWayMatchingService : IThreeWayMatchingService
{
    private readonly IPurchaseDbContext _context;

    public ThreeWayMatchingService(IPurchaseDbContext context)
    {
        _context = context;
    }

    public async Task<PurchaseInvoiceDto> CreateInvoiceAsync(CreatePurchaseInvoiceDto dto, CancellationToken ct = default)
    {
        if (dto.PurchaseOrderId <= 0)
            throw new PurchaseDomainException("INVALID_PO", "Đơn mua hàng không hợp lệ.");

        if (dto.SupplierId <= 0)
            throw new PurchaseDomainException("INVALID_SUPPLIER", "Nhà cung cấp không hợp lệ.");

        if (string.IsNullOrWhiteSpace(dto.SupplierInvoiceNumber))
            throw new PurchaseDomainException("MISSING_INVOICE_NUMBER", "Số hóa đơn tài chính của nhà cung cấp không được để trống.");

        if (!dto.Items.Any())
            throw new PurchaseDomainException("EMPTY_ITEMS", "Hóa đơn phải có ít nhất 1 dòng mặt hàng.");

        var po = await _context.PurchaseOrders
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == dto.PurchaseOrderId, ct);

        if (po == null)
            throw new PurchaseDomainException("PO_NOT_FOUND", $"Không tìm thấy đơn mua hàng ID {dto.PurchaseOrderId}.");

        var count = await _context.PurchaseInvoices.CountAsync(ct);
        var datePrefix = DateTime.UtcNow.ToString("yyyyMM");
        var code = $"PINV-{datePrefix}-{(count + 1):D4}";

        var invoice = new PurchaseInvoice
        {
            Code = code,
            PurchaseOrderId = dto.PurchaseOrderId,
            GoodsReceiptId = dto.GoodsReceiptId,
            SupplierId = dto.SupplierId,
            SupplierInvoiceNumber = dto.SupplierInvoiceNumber.Trim(),
            InvoiceDate = dto.InvoiceDate ?? DateTime.UtcNow,
            DueDate = dto.DueDate,
            Status = PurchaseInvoiceStatus.Draft,
            MatchingStatus = ThreeWayMatchingStatus.Unmatched,
            Notes = dto.Notes,
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow
        };

        foreach (var itemDto in dto.Items)
        {
            if (itemDto.QuantityInvoiced <= 0)
                throw new PurchaseDomainException("INVALID_QUANTITY", $"Số lượng trên hóa đơn của {itemDto.Sku} phải lớn hơn 0.");

            var poItem = po.Items.FirstOrDefault(x => x.Id == itemDto.PurchaseOrderItemId)
                         ?? po.Items.FirstOrDefault(x => !string.IsNullOrEmpty(itemDto.Sku) && x.Sku == itemDto.Sku)
                         ?? (itemDto.ProductId > 0 ? po.Items.FirstOrDefault(x => x.ProductId == itemDto.ProductId) : null);

            if (poItem == null)
            {
                throw new PurchaseDomainException("PO_ITEM_NOT_FOUND",
                    $"Dòng mặt hàng ({itemDto.Sku ?? itemDto.PurchaseOrderItemId.ToString()}) không thuộc đơn hàng {po.Code}.");
            }

            var invItem = new PurchaseInvoiceItem
            {
                PurchaseOrderItemId = poItem.Id,
                GoodsReceiptItemId = itemDto.GoodsReceiptItemId,
                ProductId = poItem.ProductId,
                Sku = poItem.Sku,
                ProductName = poItem.ProductName,
                Unit = poItem.Unit,
                QuantityInvoiced = itemDto.QuantityInvoiced,
                UnitCost = itemDto.UnitCost,
                TaxRate = itemDto.TaxRate,
                MatchingStatus = "UNMATCHED"
            };
            invItem.CalculateSubtotal();
            invoice.Items.Add(invItem);
        }

        invoice.RecalculateTotals();
        invoice.PurchaseOrder = po;
        _context.PurchaseInvoices.Add(invoice);
        await _context.SaveChangesAsync(ct);

        // Tự động kích hoạt đối chiếu 3 bên ngay sau khi tạo hóa đơn
        await MatchInvoiceInternalAsync(invoice, ct);
        await _context.SaveChangesAsync(ct);

        return MapToDto(invoice);
    }

    public async Task<ThreeWayMatchingResultDto> MatchInvoiceAsync(long invoiceId, CancellationToken ct = default)
    {
        var invoice = await _context.PurchaseInvoices
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == invoiceId, ct);

        if (invoice == null)
            throw new PurchaseDomainException("INVOICE_NOT_FOUND", $"Không tìm thấy hóa đơn ID {invoiceId}.");

        var result = await MatchInvoiceInternalAsync(invoice, ct);
        await _context.SaveChangesAsync(ct);

        return result;
    }

    private async Task<ThreeWayMatchingResultDto> MatchInvoiceInternalAsync(PurchaseInvoice invoice, CancellationToken ct)
    {
        var po = await _context.PurchaseOrders
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == invoice.PurchaseOrderId, ct);

        if (po == null)
            throw new PurchaseDomainException("PO_NOT_FOUND", $"Không tìm thấy đơn hàng ID {invoice.PurchaseOrderId}.");

        GoodsReceipt? grn = null;
        if (invoice.GoodsReceiptId.HasValue)
        {
            grn = await _context.GoodsReceipts
                .Include(x => x.Items)
                .FirstOrDefaultAsync(x => x.Id == invoice.GoodsReceiptId.Value, ct);
        }

        var discrepancies = new List<MatchingDiscrepancyDto>();
        int matchedCount = 0;

        foreach (var invItem in invoice.Items)
        {
            var poItem = po.Items.FirstOrDefault(x => x.Id == invItem.PurchaseOrderItemId);
            if (poItem == null)
                continue;

            int compareQty;
            if (grn != null)
            {
                var grnItem = grn.Items.FirstOrDefault(x => x.PurchaseOrderItemId == poItem.Id);
                compareQty = grnItem?.QuantityReceived ?? poItem.QuantityReceived;
            }
            else
            {
                // Nếu chưa liên kết phiếu GRN cụ thể, đối chiếu số lượng thực nhận trên PO (hoặc số lượng đặt)
                compareQty = poItem.QuantityReceived > 0 ? poItem.QuantityReceived : poItem.QuantityOrdered;
            }

            bool qtyMismatch = invItem.QuantityInvoiced != compareQty;
            bool priceMismatch = invItem.UnitCost != poItem.UnitCost;

            if (!qtyMismatch && !priceMismatch)
            {
                invItem.MatchingStatus = "MATCHED";
                matchedCount++;
            }
            else
            {
                string issueType = (qtyMismatch && priceMismatch) ? "BOTH" : (qtyMismatch ? "QTY_DIFF" : "PRICE_DIFF");
                invItem.MatchingStatus = issueType;

                discrepancies.Add(new MatchingDiscrepancyDto
                {
                    Sku = invItem.Sku,
                    ProductName = invItem.ProductName,
                    IssueType = issueType,
                    OrderQty = poItem.QuantityOrdered,
                    ReceivedQty = compareQty,
                    InvoicedQty = invItem.QuantityInvoiced,
                    OrderPrice = poItem.UnitCost,
                    InvoicedPrice = invItem.UnitCost
                });
            }
        }

        if (discrepancies.Any())
        {
            invoice.MatchingStatus = ThreeWayMatchingStatus.Discrepancy;
        }
        else
        {
            invoice.MatchingStatus = ThreeWayMatchingStatus.Matched;
        }

        invoice.UpdatedAt = DateTime.UtcNow;

        return new ThreeWayMatchingResultDto
        {
            InvoiceId = invoice.Id,
            OverallStatus = invoice.MatchingStatus.ToString(),
            ItemsMatchedCount = matchedCount,
            ItemsDiscrepancyCount = discrepancies.Count,
            Discrepancies = discrepancies
        };
    }

    public async Task<PurchaseInvoiceDto> PostInvoiceAsync(long invoiceId, CancellationToken ct = default)
    {
        using var transaction = await _context.BeginTransactionAsync(ct);
        try
        {
            var invoice = await _context.PurchaseInvoices
                .Include(x => x.Items)
                .FirstOrDefaultAsync(x => x.Id == invoiceId, ct);

            if (invoice == null)
                throw new PurchaseDomainException("INVOICE_NOT_FOUND", $"Không tìm thấy hóa đơn ID {invoiceId}.");

            if (invoice.Status != PurchaseInvoiceStatus.Draft)
                throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể ghi sổ hóa đơn ở trạng thái Dự thảo (Draft).");

            // Chạy kiểm tra đối chiếu trước khi cho phép ghi sổ
            var matchResult = await MatchInvoiceInternalAsync(invoice, ct);
            if (invoice.MatchingStatus == ThreeWayMatchingStatus.Discrepancy)
            {
                throw new PurchaseDomainException("MATCHING_DISCREPANCY",
                    $"Không thể ghi sổ hóa đơn khi có chênh lệch đối chiếu 3 bên ({matchResult.ItemsDiscrepancyCount} mặt hàng bị lệch). Vui lòng kiểm tra lại đơn giá hoặc số lượng.");
            }

            var po = await _context.PurchaseOrders
                .Include(x => x.Items)
                .FirstOrDefaultAsync(x => x.Id == invoice.PurchaseOrderId, ct);

            if (po == null)
                throw new PurchaseDomainException("PO_NOT_FOUND", $"Không tìm thấy đơn hàng ID {invoice.PurchaseOrderId}.");

            // Ghi sổ hóa đơn
            invoice.Post();

            // Cập nhật số lượng đã hóa đơn trên các dòng PO
            foreach (var invItem in invoice.Items)
            {
                var poItem = po.Items.FirstOrDefault(x => x.Id == invItem.PurchaseOrderItemId);
                if (poItem != null)
                {
                    poItem.QuantityInvoiced += invItem.QuantityInvoiced;
                }
            }

            // Kiểm tra nếu tất cả các dòng PO đã nhận đủ và đã lên hóa đơn đủ thì PO hoàn tất (Completed)
            var totalOrdered = po.Items.Sum(x => x.QuantityOrdered);
            var totalReceived = po.Items.Sum(x => x.QuantityReceived);
            var totalInvoiced = po.Items.Sum(x => x.QuantityInvoiced);

            if (totalReceived >= totalOrdered && totalInvoiced >= totalOrdered)
            {
                po.Status = PurchaseOrderStatus.Completed;
            }

            po.UpdatedAt = DateTime.UtcNow;

            await _context.SaveChangesAsync(ct);
            await transaction.CommitAsync(ct);

            return MapToDto(invoice);
        }
        catch
        {
            await transaction.RollbackAsync(ct);
            throw;
        }
    }

    public async Task<PurchaseInvoiceDto?> GetByIdAsync(long id, CancellationToken ct = default)
    {
        var invoice = await _context.PurchaseInvoices
            .AsNoTracking()
            .Include(x => x.PurchaseOrder)
            .Include(x => x.GoodsReceipt)
            .Include(x => x.Items)
            .FirstOrDefaultAsync(x => x.Id == id, ct);

        return invoice != null ? MapToDto(invoice) : null;
    }

    public async Task<List<PurchaseInvoiceDto>> GetListAsync(long? purchaseOrderId = null, long? supplierId = null, string? status = null, CancellationToken ct = default)
    {
        var query = _context.PurchaseInvoices
            .AsNoTracking()
            .Include(x => x.PurchaseOrder)
            .Include(x => x.GoodsReceipt)
            .Include(x => x.Items)
            .AsQueryable();

        if (purchaseOrderId.HasValue)
            query = query.Where(x => x.PurchaseOrderId == purchaseOrderId.Value);

        if (supplierId.HasValue)
            query = query.Where(x => x.SupplierId == supplierId.Value);

        if (!string.IsNullOrWhiteSpace(status) && Enum.TryParse<PurchaseInvoiceStatus>(status, true, out var invoiceStatus))
            query = query.Where(x => x.Status == invoiceStatus);

        var list = await query.OrderByDescending(x => x.CreatedAt).ToListAsync(ct);
        return list.Select(MapToDto).ToList();
    }

    private static PurchaseInvoiceDto MapToDto(PurchaseInvoice entity)
    {
        return new PurchaseInvoiceDto
        {
            Id = entity.Id,
            Code = entity.Code,
            PurchaseOrderId = entity.PurchaseOrderId,
            PoCode = entity.PurchaseOrder?.Code ?? string.Empty,
            GoodsReceiptId = entity.GoodsReceiptId,
            GoodsReceiptCode = entity.GoodsReceipt?.Code,
            SupplierId = entity.SupplierId,
            SupplierInvoiceNumber = entity.SupplierInvoiceNumber,
            InvoiceDate = entity.InvoiceDate,
            DueDate = entity.DueDate,
            SubtotalAmount = entity.SubtotalAmount,
            TaxAmount = entity.TaxAmount,
            GrandTotal = entity.GrandTotal,
            Status = entity.Status.ToString(),
            MatchingStatus = entity.MatchingStatus.ToString(),
            Notes = entity.Notes,
            CreatedAt = entity.CreatedAt,
            Items = entity.Items.Select(i => new PurchaseInvoiceItemDto
            {
                Id = i.Id,
                PurchaseOrderItemId = i.PurchaseOrderItemId,
                GoodsReceiptItemId = i.GoodsReceiptItemId,
                ProductId = i.ProductId,
                Sku = i.Sku,
                ProductName = i.ProductName,
                Unit = i.Unit,
                QuantityInvoiced = i.QuantityInvoiced,
                UnitCost = i.UnitCost,
                TaxRate = i.TaxRate,
                Subtotal = i.Subtotal,
                MatchingStatus = i.MatchingStatus
            }).ToList()
        };
    }
}
