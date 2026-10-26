using Chat.Application.Common.Models;
using Chat.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;

namespace Chat.Api.Controllers;

[ApiController]
public class HealthController : ControllerBase
{
    private readonly MongoChatContext _context;

    public HealthController(MongoChatContext context)
    {
        _context = context;
    }

    [HttpGet("health")]
    [HttpGet("api/health")]
    [HttpGet("api/v1/chat/health")]
    public async Task<IActionResult> Check(CancellationToken ct)
    {
        bool dbConnected;
        try
        {
            dbConnected = await _context.PingAsync(ct);
        }
        catch (Exception)
        {
            // Endpoint công khai: không trả chi tiết lỗi CSDL ra ngoài
            dbConnected = false;
        }

        return Ok(ApiEnvelope<object>.Ok(new
        {
            status = dbConnected ? "UP" : "DEGRADED",
            service = "chat-service",
            version = "1.0.0",
            framework = ".NET 10",
            database = dbConnected ? "CONNECTED" : "DISCONNECTED",
            timestamp = DateTime.UtcNow
        }));
    }
}
