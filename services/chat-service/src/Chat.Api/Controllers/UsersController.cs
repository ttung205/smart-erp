using Chat.Application.Common.Models;
using Chat.Infrastructure.Realtime;
using Microsoft.AspNetCore.Mvc;

namespace Chat.Api.Controllers;

[ApiController]
[Route("api/v1/chat/users")]
public class UsersController : ControllerBase
{
    private readonly HubConnectionTracker _tracker;

    public UsersController(HubConnectionTracker tracker)
    {
        _tracker = tracker;
    }

    /// <summary>
    /// Lấy danh sách ID các nhân viên đang online realtime
    /// </summary>
    [HttpGet("online")]
    public IActionResult GetOnlineUsers()
    {
        var onlineUserIds = _tracker.GetOnlineUsers();
        return Ok(ApiEnvelope<List<int>>.Ok(onlineUserIds));
    }
}
