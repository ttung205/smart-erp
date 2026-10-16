using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Application.Services;
using Chat.Domain.Entities;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.RateLimiting;
using Microsoft.EntityFrameworkCore;
using Microsoft.Net.Http.Headers;

namespace Chat.Api.Controllers;

[ApiController]
[Route("api/v1/chat")]
public class MessagesController(MessageService messages, IChatDbContext db, ICurrentUser me, IFileStorage storage) : ControllerBase
{
    public record ContentInput(string? Content);
    public record EmojiInput(string? Emoji);
    public record ForwardInput(List<long>? ConversationIds);

    private static OkObjectResult Data<T>(T data) => new(ApiEnvelope<T>.Ok(data));

    [HttpPatch("messages/{id:long}")]
    public async Task<IActionResult> Edit(long id, [FromBody] ContentInput input, CancellationToken ct) =>
        Data(await messages.EditAsync(id, input.Content, ct));

    [HttpPost("messages/{id:long}/recall")]
    public async Task<IActionResult> Recall(long id, CancellationToken ct) => Data(await messages.RecallAsync(id, ct));

    [HttpPost("messages/{id:long}/hide")]
    public async Task<IActionResult> Hide(long id, CancellationToken ct)
    {
        await messages.HideAsync(id, ct);
        return Data(new { hidden = true });
    }

    [HttpPost("messages/{id:long}/reactions")]
    public async Task<IActionResult> React(long id, [FromBody] EmojiInput input, CancellationToken ct) =>
        Data(await messages.ReactAsync(id, input.Emoji, ct));

    [HttpPost("messages/{id:long}/pin")]
    public async Task<IActionResult> Pin(long id, CancellationToken ct) => Data(await messages.SetPinnedAsync(id, true, ct));

    [HttpDelete("messages/{id:long}/pin")]
    public async Task<IActionResult> Unpin(long id, CancellationToken ct) => Data(await messages.SetPinnedAsync(id, false, ct));

    [HttpPost("messages/{id:long}/forward")]
    [EnableRateLimiting("send")]
    public async Task<IActionResult> Forward(long id, [FromBody] ForwardInput input, CancellationToken ct) =>
        Data(new { forwarded = await messages.ForwardAsync(id, input.ConversationIds ?? [], ct) });

    /// <summary>
    /// Tải tệp đính kèm: chỉ thành viên hội thoại. Ảnh trả inline để xem trước, loại khác luôn ép tải về;
    /// nosniff để trình duyệt không đoán lại kiểu nội dung.
    /// </summary>
    [HttpGet("attachments/{id:long}")]
    public async Task<IActionResult> Download(long id, CancellationToken ct)
    {
        var attachment = await db.MessageAttachments.AsNoTracking()
            .Where(a => a.Id == id && a.Message!.RecalledAt == null && a.Message.Conversation!.Members.Any(m => m.UserId == me.Id))
            .FirstOrDefaultAsync(ct) ?? throw ChatException.NotFound("Không tìm thấy tệp.");

        Response.Headers.XContentTypeOptions = "nosniff";
        Response.Headers.CacheControl = "private, max-age=86400";
        if (FileRules.IsImage(attachment.ContentType))
        {
            var disposition = new ContentDispositionHeaderValue("inline");
            disposition.SetHttpFileName(attachment.FileName);
            Response.Headers.ContentDisposition = disposition.ToString();
            return File(storage.OpenRead(attachment.StoragePath), attachment.ContentType);
        }
        return File(storage.OpenRead(attachment.StoragePath), attachment.ContentType, attachment.FileName);
    }
}
