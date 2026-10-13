using Inventory.Application.Common.Models;
using Inventory.Domain.Entities;
using Inventory.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using System.Text.Json.Serialization;

namespace Inventory.Api.Controllers;

public class WarehouseCreateOrUpdateRequest
{
    [JsonPropertyName("code")]
    public string? Code { get; set; }

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("location")]
    public string? Location { get; set; }

    [JsonPropertyName("can_sell")]
    public bool CanSell { get; set; } = true;

    [JsonPropertyName("is_active")]
    public bool IsActive { get; set; } = true;
}

[ApiController]
[Route("api/v1/inventory/[controller]")]
public class WarehousesController : ControllerBase
{
    private readonly InventoryDbContext _context;

    public WarehousesController(InventoryDbContext context)
    {
        _context = context;
    }

    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    [HttpGet]
    public async Task<IActionResult> GetAll(CancellationToken ct)
    {
        var rawWarehouses = await _context.Warehouses
            .AsNoTracking()
            .OrderBy(w => w.Id)
            .ToListAsync(ct);

        var rawStocks = await _context.InventoryStocks
            .AsNoTracking()
            .Select(s => new { s.WarehouseId, s.Quantity })
            .ToListAsync(ct);

        var stockSums = rawStocks
            .GroupBy(s => s.WarehouseId)
            .ToDictionary(g => g.Key, g => g.Sum(s => s.Quantity));

        var rawRes = await _context.InventoryStockReservations
            .AsNoTracking()
            .Select(r => new { r.WarehouseId, r.Quantity })
            .ToListAsync(ct);

        var resSums = rawRes
            .GroupBy(r => r.WarehouseId)
            .ToDictionary(g => g.Key, g => g.Sum(r => r.Quantity));

        var warehouses = rawWarehouses.Select(w => new
        {
            id = w.Id,
            code = w.Code,
            name = w.Name,
            location = w.Location,
            is_active = w.IsActive,
            can_sell = w.CanSell,
            total_on_hand = stockSums.GetValueOrDefault(w.Id, 0),
            total_available = resSums.GetValueOrDefault(w.Id, 0),
            created_at = w.CreatedAt,
            updated_at = w.UpdatedAt
        }).ToList();

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds(),
            Pagination = new PaginationMeta
            {
                CurrentPage = 1,
                PerPage = warehouses.Count,
                TotalItems = warehouses.Count,
                TotalPages = 1
            }
        };

        return Ok(ApiEnvelope<object>.Ok(warehouses, meta));
    }

    [HttpPost]
    public async Task<IActionResult> Create([FromBody] WarehouseCreateOrUpdateRequest request, CancellationToken ct)
    {
        if (string.IsNullOrWhiteSpace(request.Name))
        {
            return BadRequest(ApiEnvelope<object>.Fail("VALIDATION_ERROR", "Tên kho không được để trống.", null, GetCorrelationId()));
        }

        var code = string.IsNullOrWhiteSpace(request.Code) 
            ? $"WH-{Guid.NewGuid():N}"[..8].ToUpperInvariant() 
            : request.Code.Trim();

        if (await _context.Warehouses.AnyAsync(w => w.Code == code, ct))
        {
            return BadRequest(ApiEnvelope<object>.Fail("CONFLICT", $"Mã kho '{code}' đã tồn tại.", null, GetCorrelationId()));
        }

        var warehouse = new Warehouse
        {
            Code = code,
            Name = request.Name.Trim(),
            Location = request.Location,
            CanSell = request.CanSell,
            IsActive = request.IsActive,
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow
        };

        _context.Warehouses.Add(warehouse);
        await _context.SaveChangesAsync(ct);

        return Ok(ApiEnvelope<object>.Ok(warehouse));
    }

    [HttpPut("{id:long}")]
    public async Task<IActionResult> Update(long id, [FromBody] WarehouseCreateOrUpdateRequest request, CancellationToken ct)
    {
        var warehouse = await _context.Warehouses.FindAsync(new object[] { id }, ct);
        if (warehouse == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("NOT_FOUND", "Không tìm thấy kho hàng.", null, GetCorrelationId()));
        }

        if (!string.IsNullOrWhiteSpace(request.Name))
            warehouse.Name = request.Name.Trim();

        if (!string.IsNullOrWhiteSpace(request.Code))
        {
            var newCode = request.Code.Trim();
            if (newCode != warehouse.Code && await _context.Warehouses.AnyAsync(w => w.Code == newCode && w.Id != id, ct))
            {
                return BadRequest(ApiEnvelope<object>.Fail("CONFLICT", $"Mã kho '{newCode}' đã tồn tại.", null, GetCorrelationId()));
            }
            warehouse.Code = newCode;
        }

        warehouse.Location = request.Location;
        warehouse.CanSell = request.CanSell;
        warehouse.IsActive = request.IsActive;
        warehouse.UpdatedAt = DateTime.UtcNow;

        await _context.SaveChangesAsync(ct);

        return Ok(ApiEnvelope<object>.Ok(warehouse));
    }

    [HttpDelete("{id:long}")]
    public async Task<IActionResult> Delete(long id, CancellationToken ct)
    {
        var warehouse = await _context.Warehouses.FindAsync(new object[] { id }, ct);
        if (warehouse == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("NOT_FOUND", "Không tìm thấy kho hàng.", null, GetCorrelationId()));
        }

        var hasStocks = await _context.InventoryStocks.AnyAsync(s => s.WarehouseId == id && s.Quantity > 0, ct);
        if (hasStocks)
        {
            warehouse.IsActive = false;
            warehouse.CanSell = false;
            await _context.SaveChangesAsync(ct);
            return Ok(ApiEnvelope<object>.Ok(new { message = "Kho còn hàng tồn, đã chuyển trạng thái sang ngưng hoạt động." }));
        }

        _context.Warehouses.Remove(warehouse);
        await _context.SaveChangesAsync(ct);

        return Ok(ApiEnvelope<object>.Ok(new { message = "Xóa kho thành công." }));
    }
}
