using System.ComponentModel.DataAnnotations;
using System.Text.Json.Serialization;
using Inventory.Application.Common.Models;
using Inventory.Domain.Entities;
using Inventory.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Api.Controllers;

public class SalesReturnStockItemRequest
{
    [Range(1, long.MaxValue)]
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [Range(1, int.MaxValue)]
    [JsonPropertyName("quantity")]
    public int Quantity { get; set; }
}

public class SalesReturnReceiveRequest
{
    [Required]
    [JsonPropertyName("reference_doc")]
    public string ReferenceDoc { get; set; } = string.Empty;

    [Range(1, long.MaxValue)]
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [Required, MinLength(1)]
    [JsonPropertyName("items")]
    public List<SalesReturnStockItemRequest> Items { get; set; } = [];
}

public class SalesReturnTransferRequest
{
    [Required]
    [JsonPropertyName("reference_doc")]
    public string ReferenceDoc { get; set; } = string.Empty;

    [Range(1, long.MaxValue)]
    [JsonPropertyName("source_warehouse_id")]
    public long SourceWarehouseId { get; set; }

    [Range(1, long.MaxValue)]
    [JsonPropertyName("destination_warehouse_id")]
    public long DestinationWarehouseId { get; set; }

    [Required, MinLength(1)]
    [JsonPropertyName("items")]
    public List<SalesReturnStockItemRequest> Items { get; set; } = [];
}

[ApiController]
[Route("api/v1/inventory/sales-returns")]
public class SalesReturnsController : ControllerBase
{
    private readonly InventoryDbContext _context;

    public SalesReturnsController(InventoryDbContext context)
    {
        _context = context;
    }

    [HttpPost("receive")]
    public async Task<IActionResult> Receive([FromBody] SalesReturnReceiveRequest request, CancellationToken ct)
    {
        var warehouse = await _context.Warehouses.SingleOrDefaultAsync(w => w.Id == request.WarehouseId, ct);
        if (warehouse is null || !warehouse.IsActive || warehouse.CanSell)
        {
            return BadRequest(ApiEnvelope<object>.Fail("INVALID_TECHNICAL_WAREHOUSE", "Kho nhận phải là kho kỹ thuật đang hoạt động."));
        }

        var items = AggregateItems(request.Items);
        await using var transaction = await _context.Database.BeginTransactionAsync(ct);
        try
        {
            foreach (var item in items)
            {
                await ApplyPhysicalAsync(warehouse.Id, item.ProductId, item.Quantity, "SALES_RETURN_RECEIPT", request.ReferenceDoc, "Nhập hàng bán trả lại vào kho kỹ thuật", ct);
                await ApplyAvailableAsync(warehouse.Id, item.ProductId, item.Quantity, "SALES_RETURN_RECEIPT", request.ReferenceDoc, "Tăng tồn khả dụng kho kỹ thuật", ct);
            }
            await _context.SaveChangesAsync(ct);
            await transaction.CommitAsync(ct);

            return Ok(ApiEnvelope<object>.Ok(new { reference_doc = request.ReferenceDoc, warehouse_id = warehouse.Id, items }));
        }
        catch (InvalidOperationException exception)
        {
            await transaction.RollbackAsync(ct);
            return Conflict(ApiEnvelope<object>.Fail("STOCK_OPERATION_FAILED", exception.Message));
        }
    }

    [HttpPost("transfer")]
    public async Task<IActionResult> Transfer([FromBody] SalesReturnTransferRequest request, CancellationToken ct)
    {
        if (request.SourceWarehouseId == request.DestinationWarehouseId)
        {
            return BadRequest(ApiEnvelope<object>.Fail("INVALID_WAREHOUSE", "Kho nguồn và kho đích phải khác nhau."));
        }
        var warehouses = await _context.Warehouses.Where(w => w.Id == request.SourceWarehouseId || w.Id == request.DestinationWarehouseId).ToListAsync(ct);
        var source = warehouses.FirstOrDefault(w => w.Id == request.SourceWarehouseId);
        var destination = warehouses.FirstOrDefault(w => w.Id == request.DestinationWarehouseId);
        if (source is null || !source.IsActive || source.CanSell || destination is null || !destination.IsActive || !destination.CanSell)
        {
            return BadRequest(ApiEnvelope<object>.Fail("INVALID_WAREHOUSE_FLOW", "Luồng điều chuyển phải đi từ kho kỹ thuật sang kho được phép bán."));
        }

        var items = AggregateItems(request.Items);
        await using var transaction = await _context.Database.BeginTransactionAsync(ct);
        try
        {
            foreach (var item in items)
            {
                await ApplyPhysicalAsync(source.Id, item.ProductId, -item.Quantity, "SALES_RETURN_TRANSFER_OUT", request.ReferenceDoc, "Xuất kho kỹ thuật", ct);
                await ApplyAvailableAsync(source.Id, item.ProductId, -item.Quantity, "SALES_RETURN_TRANSFER_OUT", request.ReferenceDoc, "Giảm tồn khả dụng kho kỹ thuật", ct);
                await ApplyPhysicalAsync(destination.Id, item.ProductId, item.Quantity, "SALES_RETURN_TRANSFER_IN", request.ReferenceDoc, "Nhập kho bán", ct);
                await ApplyAvailableAsync(destination.Id, item.ProductId, item.Quantity, "SALES_RETURN_TRANSFER_IN", request.ReferenceDoc, "Tăng tồn khả dụng kho bán", ct);
            }
            await _context.SaveChangesAsync(ct);
            await transaction.CommitAsync(ct);

            return Ok(ApiEnvelope<object>.Ok(new { reference_doc = request.ReferenceDoc, source_warehouse_id = source.Id, destination_warehouse_id = destination.Id, items }));
        }
        catch (InvalidOperationException exception)
        {
            await transaction.RollbackAsync(ct);
            return Conflict(ApiEnvelope<object>.Fail("STOCK_TRANSFER_FAILED", exception.Message));
        }
    }

    private static List<SalesReturnStockItemRequest> AggregateItems(IEnumerable<SalesReturnStockItemRequest> items)
    {
        return items.GroupBy(item => item.ProductId)
            .Select(group => new SalesReturnStockItemRequest { ProductId = group.Key, Quantity = group.Sum(item => item.Quantity) })
            .ToList();
    }

    private async Task ApplyPhysicalAsync(long warehouseId, long productId, int delta, string documentType, string documentId, string notes, CancellationToken ct)
    {
        var existing = await _context.InventoryStockTransactions.AsNoTracking().FirstOrDefaultAsync(item =>
            item.WarehouseId == warehouseId && item.ProductId == productId && item.DocumentType == documentType && item.DocumentId == documentId, ct);
        if (existing is not null)
        {
            if (existing.QuantityDelta != delta) throw new InvalidOperationException("Chứng từ đã ghi sổ với số lượng khác.");
            return;
        }
        var stock = await _context.InventoryStocks.FirstOrDefaultAsync(item => item.WarehouseId == warehouseId && item.ProductId == productId, ct);
        if (stock is null)
        {
            stock = new InventoryStock { WarehouseId = warehouseId, ProductId = productId, Quantity = 0, Origin = "Việt Nam", UpdatedAt = DateTime.UtcNow };
            _context.InventoryStocks.Add(stock);
        }
        if (stock.Quantity + delta < 0) throw new InvalidOperationException($"Tồn thực tế không đủ cho sản phẩm {productId}.");
        var before = stock.Quantity;
        stock.Quantity += delta;
        stock.UpdatedAt = DateTime.UtcNow;
        _context.InventoryStockTransactions.Add(new InventoryStockTransaction
        {
            WarehouseId = warehouseId, ProductId = productId, DocumentType = documentType, DocumentId = documentId,
            BalanceBefore = before, QuantityDelta = delta, BalanceAfter = stock.Quantity, Notes = notes,
            CreatedBy = GetUserId(), CreatedAt = DateTime.UtcNow
        });
    }

    private async Task ApplyAvailableAsync(long warehouseId, long productId, int delta, string documentType, string documentId, string notes, CancellationToken ct)
    {
        var existing = await _context.InventoryStockReservationTransactions.AsNoTracking().FirstOrDefaultAsync(item =>
            item.WarehouseId == warehouseId && item.ProductId == productId && item.DocumentType == documentType && item.DocumentId == documentId, ct);
        if (existing is not null)
        {
            if (existing.QuantityDelta != delta) throw new InvalidOperationException("Chứng từ đã ghi sổ tồn khả dụng với số lượng khác.");
            return;
        }
        var stock = await _context.InventoryStockReservations.FirstOrDefaultAsync(item => item.WarehouseId == warehouseId && item.ProductId == productId, ct);
        if (stock is null)
        {
            stock = new InventoryStockReservation { WarehouseId = warehouseId, ProductId = productId, Quantity = 0, UpdatedAt = DateTime.UtcNow };
            _context.InventoryStockReservations.Add(stock);
        }
        if (stock.Quantity + delta < 0) throw new InvalidOperationException($"Tồn khả dụng không đủ cho sản phẩm {productId}.");
        var before = stock.Quantity;
        stock.Quantity += delta;
        stock.UpdatedAt = DateTime.UtcNow;
        _context.InventoryStockReservationTransactions.Add(new InventoryStockReservationTransaction
        {
            WarehouseId = warehouseId, ProductId = productId, DocumentType = documentType, DocumentId = documentId,
            BalanceBefore = before, QuantityDelta = delta, BalanceAfter = stock.Quantity, Notes = notes,
            CreatedBy = GetUserId(), CreatedAt = DateTime.UtcNow
        });
    }

    private long? GetUserId()
    {
        return long.TryParse(HttpContext.Items["UserId"]?.ToString(), out var id) ? id : null;
    }
}
