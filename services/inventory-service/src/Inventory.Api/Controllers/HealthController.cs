using Inventory.Application.Common.Models;
using Microsoft.AspNetCore.Mvc;

namespace Inventory.Api.Controllers;

[ApiController]
[Route("[controller]")]
public class HealthController : ControllerBase
{
    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    /// <summary>
    /// Kiểm tra trạng thái hoạt động (Liveness / Readiness Probe) của Inventory Service.
    /// </summary>
    [HttpGet("/health")]
    [HttpGet("/api/v1/inventory/health")]
    public IActionResult CheckHealth()
    {
        var healthInfo = new
        {
            service = "inventory-service",
            status = "healthy",
            port = 8004,
            framework = ".NET 10.0",
            timestamp = DateTime.UtcNow
        };

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<object>.Ok(healthInfo, meta));
    }
}
