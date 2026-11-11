using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Purchase.Infrastructure.Persistence;

namespace Purchase.Api.Controllers;

/// <summary>
/// Xuất dòng đơn mua cho kho phân tích của ai-service. Chỉ chấp nhận X-Internal-Service-Key (xem JwtAuthMiddleware).
/// Phân trang theo khóa chính dòng đơn: ?after_id=0&amp;limit=500, next_after_id = null khi hết dữ liệu.
/// </summary>
[ApiController]
[Route("internal/analytics")]
public class InternalAnalyticsController(PurchaseDbContext db) : ControllerBase
{
    private const int DefaultLimit = 500;
    private const int MaxLimit = 1000;

    [HttpGet("po-lines")]
    public async Task<IActionResult> PoLines([FromQuery] long after_id = 0, [FromQuery] int limit = DefaultLimit, CancellationToken ct = default)
    {
        var take = Math.Clamp(limit, 1, MaxLimit);
        var rows = await (
            from i in db.PurchaseOrderItems.AsNoTracking()
            join o in db.PurchaseOrders.AsNoTracking() on i.PurchaseOrderId equals o.Id
            where i.Id > after_id
            orderby i.Id
            select new
            {
                id = i.Id,
                po_id = o.Id,
                po_code = o.Code,
                order_date = o.OrderDate,
                expected_date = o.ExpectedDeliveryDate,
                status = o.Status,
                supplier_id = o.SupplierId,
                currency = o.Currency,
                product_id = i.ProductId,
                qty_ordered = i.QuantityOrdered,
                qty_received = i.QuantityReceived,
                unit_cost = i.UnitCost,
                first_receipt = db.GoodsReceipts.Where(g => g.PurchaseOrderId == o.Id).Min(g => (DateTime?)g.ReceivedAt),
                last_receipt = db.GoodsReceipts.Where(g => g.PurchaseOrderId == o.Id).Max(g => (DateTime?)g.ReceivedAt),
            })
            .Take(take)
            .ToListAsync(ct);

        var items = rows.Select(r => new
        {
            r.id, r.po_id, r.po_code, r.order_date, r.expected_date,
            status = r.status.ToString(),
            r.supplier_id, r.currency, r.product_id, r.qty_ordered, r.qty_received, r.unit_cost,
            r.first_receipt, r.last_receipt,
        }).ToList();

        var nextAfterId = rows.Count == take ? rows[^1].id : (long?)null;
        return Ok(new { success = true, data = new { items, next_after_id = nextAfterId } });
    }
}
