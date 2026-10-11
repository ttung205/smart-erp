using Inventory.Application.Common.Commands;
using Inventory.Application.Common.Models;
using Inventory.Application.DTOs;
using Inventory.Application.Interfaces;
using Inventory.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Api.Controllers;

[ApiController]
[Route("api/v1/inventory/[controller]")]
public class ReservationsController : ControllerBase
{
    private readonly InventoryDbContext _context;
    private readonly IInventoryStockManager _stockManager;

    public ReservationsController(InventoryDbContext context, IInventoryStockManager stockManager)
    {
        _context = context;
        _stockManager = stockManager;
    }

    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    /// <summary>
    /// Tra cứu danh sách tồn kho khả dụng.
    /// </summary>
    [HttpGet]
    public async Task<IActionResult> GetReservations(
        [FromQuery] long? productId,
        [FromQuery] long? warehouseId,
        [FromQuery] int page = 1,
        [FromQuery] int pageSize = 1000,
        CancellationToken ct = default)
    {
        page = Math.Max(1, page);
        pageSize = Math.Clamp(pageSize, 1, 1000);

        var query = _context.InventoryStockReservations
            .AsNoTracking()
            .Include(r => r.Warehouse)
            .AsQueryable();

        if (productId.HasValue)
            query = query.Where(r => r.ProductId == productId.Value);

        if (warehouseId.HasValue)
            query = query.Where(r => r.WarehouseId == warehouseId.Value);

        var totalItems = await query.CountAsync(ct);
        var totalPages = (int)Math.Ceiling(totalItems / (double)pageSize);

        var items = await query
            .OrderBy(r => r.WarehouseId)
            .ThenBy(r => r.ProductId)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .Select(r => new
            {
                id = r.Id,
                warehouse_id = r.WarehouseId,
                warehouse_code = r.Warehouse != null ? r.Warehouse.Code : null,
                warehouse_name = r.Warehouse != null ? r.Warehouse.Name : null,
                product_id = r.ProductId,
                quantity = r.Quantity,
                updated_at = r.UpdatedAt
            })
            .ToListAsync(ct);

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds(),
            Pagination = new PaginationMeta
            {
                CurrentPage = page,
                PerPage = pageSize,
                TotalItems = totalItems,
                TotalPages = totalPages
            }
        };

        return Ok(ApiEnvelope<object>.Ok(items, meta));
    }

    /// <summary>
    /// Thay đổi số dư tồn kho khả dụng (Available Stock Mutation).
    /// Tự động cập nhật bảng inventory_stocks_reservation và ghi sổ inventory_stocks_reservation_transaction.
    /// </summary>
    [HttpPost("change")]
    public async Task<IActionResult> ChangeReservationStock([FromBody] ChangeStockRequest request, CancellationToken ct)
    {
        var command = new ChangeStockCommand(
            WarehouseId: request.WarehouseId,
            ProductId: request.ProductId,
            QuantityDelta: request.QuantityDelta,
            DocumentType: request.DocumentType,
            DocumentId: request.DocumentId,
            Notes: request.Notes,
            CreatedBy: request.CreatedBy
        );

        var result = await _stockManager.ChangeReservationStockAsync(command, ct);

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<object>.Ok(new
        {
            warehouse_id = result.WarehouseId,
            product_id = result.ProductId,
            available_after = result.QuantityReserved,
            quantity_delta = result.QuantityDelta,
            document_type = result.OperationType,
            document_id = result.ReferenceDoc,
            transaction_id = result.MovementId,
            timestamp = result.Timestamp
        }, meta));
    }

    /// <summary>
    /// Sổ cái lịch sử giao dịch tồn kho khả dụng (Audit Ledger).
    /// </summary>
    [HttpGet("transactions")]
    public async Task<IActionResult> GetReservationTransactions(
        [FromQuery] long? productId,
        [FromQuery] long? warehouseId,
        [FromQuery] string? documentType,
        [FromQuery] string? documentId,
        [FromQuery] int page = 1,
        [FromQuery] int pageSize = 500,
        CancellationToken ct = default)
    {
        page = Math.Max(1, page);
        pageSize = Math.Clamp(pageSize, 1, 1000);

        var query = _context.InventoryStockReservationTransactions
            .AsNoTracking()
            .Include(t => t.Warehouse)
            .AsQueryable();

        if (productId.HasValue)
            query = query.Where(t => t.ProductId == productId.Value);

        if (warehouseId.HasValue)
            query = query.Where(t => t.WarehouseId == warehouseId.Value);

        if (!string.IsNullOrWhiteSpace(documentType))
            query = query.Where(t => t.DocumentType == documentType);

        if (!string.IsNullOrWhiteSpace(documentId))
            query = query.Where(t => t.DocumentId == documentId);

        var totalItems = await query.CountAsync(ct);
        var totalPages = (int)Math.Ceiling(totalItems / (double)pageSize);

        var transactions = await query
            .OrderByDescending(t => t.CreatedAt)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .Select(t => new
            {
                id = t.Id,
                warehouse_id = t.WarehouseId,
                warehouse_name = t.Warehouse != null ? t.Warehouse.Name : null,
                product_id = t.ProductId,
                document_type = t.DocumentType,
                document_id = t.DocumentId,
                balance_before = t.BalanceBefore,
                quantity_delta = t.QuantityDelta,
                balance_after = t.BalanceAfter,
                notes = t.Notes,
                created_by = t.CreatedBy,
                created_at = t.CreatedAt
            })
            .ToListAsync(ct);

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds(),
            Pagination = new PaginationMeta
            {
                CurrentPage = page,
                PerPage = pageSize,
                TotalItems = totalItems,
                TotalPages = totalPages
            }
        };

        return Ok(ApiEnvelope<object>.Ok(transactions, meta));
    }
}
