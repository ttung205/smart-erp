using Inventory.Application.Common.Models;
using Inventory.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Api.Controllers;

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
        var warehouses = await _context.Warehouses
            .AsNoTracking()
            .Where(w => w.IsActive)
            .OrderBy(w => w.Id)
            .Select(w => new
            {
                id = w.Id,
                code = w.Code,
                name = w.Name,
                location = w.Location,
                is_active = w.IsActive,
                created_at = w.CreatedAt
            })
            .ToListAsync(ct);

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
}
