using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Domain.Entities;
using Chat.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace Chat.Api.Controllers;

[ApiController]
[Route("api/v1/chat/messages")]
public class MessagesController : ControllerBase
{
    private readonly ChatDbContext _context;
    private readonly ICurrentUserService _currentUser;
    private readonly IChatHubService _hubService;

    public MessagesController(
        ChatDbContext context,
        ICurrentUserService currentUser,
        IChatHubService hubService)
    {
        _context = context;
        _currentUser = currentUser;
        _hubService = hubService;
    }

    /// <summary>
    /// Xác nhận đã đọc một tin nhắn
    /// </summary>
    [HttpPost("{id:guid}/read")]
    public async Task<IActionResult> MarkAsRead(Guid id, CancellationToken ct)
    {
        var currentUserId = _currentUser.UserId ?? 0;
        if (currentUserId <= 0)
        {
            return Unauthorized(ApiEnvelope<object>.Fail("UNAUTHORIZED", "Chưa xác thực người dùng."));
        }

        var message = await _context.ChatMessages
            .Include(m => m.Receipts)
            .FirstOrDefaultAsync(m => m.Id == id, ct);

        if (message == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("MESSAGE_NOT_FOUND", "Không tìm thấy tin nhắn."));
        }

        var receipt = message.Receipts.FirstOrDefault(r => r.UserId == currentUserId);
        if (receipt == null)
        {
            receipt = new MessageReceipt
            {
                Id = Guid.NewGuid(),
                MessageId = message.Id,
                UserId = currentUserId,
                ReadAt = DateTime.UtcNow
            };

            await _context.MessageReceipts.AddAsync(receipt, ct);
            await _context.SaveChangesAsync(ct);

            // Bắn tín hiệu realtime cho người gửi tin nhắn
            await _hubService.NotifyMessageReadAsync(message.Id, currentUserId, receipt.ReadAt, message.SenderId);
        }

        return Ok(ApiEnvelope<object>.Ok(new
        {
            message_id = message.Id,
            user_id = currentUserId,
            read_at = receipt.ReadAt
        }));
    }
}
