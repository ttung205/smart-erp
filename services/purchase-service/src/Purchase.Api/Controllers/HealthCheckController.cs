using Microsoft.AspNetCore.Mvc;
using Purchase.Application.Common.Models;

namespace Purchase.Api.Controllers;

[ApiController]
[Route("api/v1/purchase/health")]
[Route("health")]
[Route("api/health")]
[Route("api/v1/purchase/[controller]")]
public class HealthCheckController : ControllerBase
{
    [HttpGet]
    public IActionResult Get()
    {
        var response = new
        {
            service = "purchase-service",
            status = "HEALTHY",
            version = "net10.0",
            timestamp = DateTime.UtcNow
        };

        return Ok(ApiEnvelope<object>.Ok(response));
    }
}
