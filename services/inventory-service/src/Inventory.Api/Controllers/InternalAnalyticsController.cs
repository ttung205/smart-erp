using Inventory.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Api.Controllers;

/// <summary>
/// Xuất dữ liệu tồn kho cho kho phân tích của ai-service. Chỉ chấp nhận X-Internal-Service-Key (xem JwtAuthMiddleware).
/// Phân trang theo khóa chính: ?after_id=0&amp;limit=500, next_after_id = null khi hết dữ liệu.
/// Chỉ tính kho được phép bán (can_sell = 1), giống cách tính tồn bán được trước đây.
/// </summary>
[ApiController]
[Route("internal/analytics")]
public class InternalAnalyticsController(InventoryDbContext db) : ControllerBase
{
    private const int DefaultLimit = 500;
    private const int MaxLimit = 1000;

    [HttpGet("stock-balances")]
    public async Task<IActionResult> StockBalances([FromQuery] long after_id = 0, [FromQuery] int limit = DefaultLimit, CancellationToken ct = default)
    {
        var take = Math.Clamp(limit, 1, MaxLimit);
        var rows = await (
            from s in db.InventoryStocks.AsNoTracking()
            join w in db.Warehouses.AsNoTracking() on s.WarehouseId equals w.Id
            where w.CanSell && s.Id > after_id
            orderby s.Id
            select new { id = s.Id, warehouse_id = s.WarehouseId, product_id = s.ProductId, quantity = s.Quantity })
            .Take(take).ToListAsync(ct);

        return Page(rows, rows.Count == take ? rows[^1].id : null);
    }

    [HttpGet("reservation-balances")]
    public async Task<IActionResult> ReservationBalances([FromQuery] long after_id = 0, [FromQuery] int limit = DefaultLimit, CancellationToken ct = default)
    {
        var take = Math.Clamp(limit, 1, MaxLimit);
        var rows = await (
            from r in db.InventoryStockReservations.AsNoTracking()
            join w in db.Warehouses.AsNoTracking() on r.WarehouseId equals w.Id
            where w.CanSell && r.Id > after_id
            orderby r.Id
            select new { id = r.Id, warehouse_id = r.WarehouseId, product_id = r.ProductId, quantity = r.Quantity })
            .Take(take).ToListAsync(ct);

        return Page(rows, rows.Count == take ? rows[^1].id : null);
    }

    /// <summary>Sổ cái tồn thực tế: mỗi dòng là một biến động (bất biến).</summary>
    [HttpGet("stock-moves")]
    public async Task<IActionResult> StockMoves([FromQuery] long after_id = 0, [FromQuery] int limit = DefaultLimit, CancellationToken ct = default)
    {
        var take = Math.Clamp(limit, 1, MaxLimit);
        var rows = await db.InventoryStockTransactions.AsNoTracking()
            .Where(m => m.Id > after_id)
            .OrderBy(m => m.Id)
            .Take(take)
            .Select(m => new
            {
                id = m.Id,
                created_at = m.CreatedAt,
                product_id = m.ProductId,
                document_type = m.DocumentType,
                document_id = m.DocumentId,
                quantity_delta = m.QuantityDelta,
                warehouse_id = m.WarehouseId,
                balance_after = m.BalanceAfter,
                created_by = m.CreatedBy,
            })
            .ToListAsync(ct);

        return Page(rows, rows.Count == take ? rows[^1].id : null);
    }

    private IActionResult Page<T>(IReadOnlyList<T> items, long? nextAfterId)
        => Ok(new { success = true, data = new { items, next_after_id = nextAfterId } });
}
