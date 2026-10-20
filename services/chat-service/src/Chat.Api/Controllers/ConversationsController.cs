using Chat.Application.Common.Models;
using Chat.Application.Dtos;
using Chat.Application.Services;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.RateLimiting;

namespace Chat.Api.Controllers;

[ApiController]
[Route("api/v1/chat/conversations")]
public class ConversationsController(ConversationService conversations, MessageService messages) : ControllerBase
{
    private const long MaxMessageRequestBytes = 210L * 1024 * 1024;

    public record DirectInput(long UserId);
    public record WorkInput(string SubjectType, long SubjectId, string? SubjectCode, string? SubjectTitle);
    public record GroupInput(string? Title, List<long>? UserIds);
    public record TitleInput(string? Title);
    public record UserIdsInput(List<long>? UserIds);
    public record LeaveInput(long? NewOwnerId);
    public record RoleInput(string? Role);
    public record ReadInput(long MessageId);

    private static OkObjectResult Data<T>(T data) => new(ApiEnvelope<T>.Ok(data));

    // ---------- Hội thoại ----------

    [HttpGet]
    public async Task<IActionResult> List([FromQuery] int page = 1, [FromQuery(Name = "per_page")] int perPage = 30,
        [FromQuery] string? search = null, [FromQuery] string? filter = null, CancellationToken ct = default) =>
        Data(await conversations.ListAsync(page, perPage, search, filter, ct));

    [HttpGet("{id:long}")]
    public async Task<IActionResult> Get(long id, CancellationToken ct) => Data(await conversations.GetAsync(id, ct));

    [HttpGet("unread-count")]
    public async Task<IActionResult> UnreadCount(CancellationToken ct) => Data(new { count = await conversations.UnreadTotalAsync(ct) });

    [HttpPost("direct")]
    public async Task<IActionResult> OpenDirect([FromBody] DirectInput input, CancellationToken ct) =>
        Data(await conversations.OpenDirectAsync(input.UserId, ct));

    [HttpPost("work")]
    public async Task<IActionResult> OpenWorkThread([FromBody] WorkInput input, CancellationToken ct) =>
        Data(await conversations.OpenWorkThreadAsync(input.SubjectType, input.SubjectId, input.SubjectCode, input.SubjectTitle, ct));

    [HttpPost("group")]
    public async Task<IActionResult> CreateGroup([FromBody] GroupInput input, CancellationToken ct) =>
        Data(await conversations.CreateGroupAsync(input.Title, input.UserIds ?? [], ct));

    [HttpPost("read-all")]
    public async Task<IActionResult> ReadAll(CancellationToken ct) => Data(new { unread_total = await conversations.MarkAllReadAsync(ct) });

    [HttpPost("{id:long}/read")]
    public async Task<IActionResult> Read(long id, [FromBody] ReadInput input, CancellationToken ct) =>
        Data(new { unread_total = await conversations.MarkReadAsync(id, input.MessageId, ct) });

    [HttpPost("{id:long}/pin")]
    public async Task<IActionResult> Pin(long id, CancellationToken ct) => Data(await conversations.SetPinnedAsync(id, true, ct));

    [HttpDelete("{id:long}/pin")]
    public async Task<IActionResult> Unpin(long id, CancellationToken ct) => Data(await conversations.SetPinnedAsync(id, false, ct));

    [HttpPost("{id:long}/mute")]
    public async Task<IActionResult> Mute(long id, CancellationToken ct) => Data(await conversations.SetMutedAsync(id, true, ct));

    [HttpDelete("{id:long}/mute")]
    public async Task<IActionResult> Unmute(long id, CancellationToken ct) => Data(await conversations.SetMutedAsync(id, false, ct));

    // ---------- Quản lý nhóm ----------

    [HttpPatch("{id:long}")]
    public async Task<IActionResult> Rename(long id, [FromBody] TitleInput input, CancellationToken ct) =>
        Data(await conversations.RenameAsync(id, input.Title, ct));

    [HttpPost("{id:long}/avatar")]
    [RequestSizeLimit(6 * 1024 * 1024)]
    public async Task<IActionResult> SetAvatar(long id, IFormFile? avatar, CancellationToken ct)
    {
        if (avatar == null) throw ChatException.Invalid("Chưa chọn ảnh.");
        return Data(await conversations.SetAvatarAsync(id, new UploadedFile(avatar.FileName, avatar.Length, avatar.OpenReadStream), ct));
    }

    [HttpDelete("{id:long}/avatar")]
    public async Task<IActionResult> RemoveAvatar(long id, CancellationToken ct) => Data(await conversations.RemoveAvatarAsync(id, ct));

    [HttpGet("{id:long}/avatar")]
    public async Task<IActionResult> Avatar(long id, CancellationToken ct)
    {
        var (stream, contentType) = await conversations.OpenAvatarAsync(id, ct);
        Response.Headers.CacheControl = "private, max-age=604800";
        return File(stream, contentType);
    }

    [HttpPost("{id:long}/members")]
    public async Task<IActionResult> AddMembers(long id, [FromBody] UserIdsInput input, CancellationToken ct) =>
        Data(await conversations.AddMembersAsync(id, input.UserIds ?? [], ct));

    [HttpDelete("{id:long}/members/{userId:long}")]
    public async Task<IActionResult> RemoveMember(long id, long userId, CancellationToken ct)
    {
        await conversations.RemoveMemberAsync(id, userId, ct);
        return Data(new { removed = true });
    }

    [HttpPut("{id:long}/members/{userId:long}/role")]
    public async Task<IActionResult> SetRole(long id, long userId, [FromBody] RoleInput input, CancellationToken ct) =>
        Data(await conversations.SetRoleAsync(id, userId, input.Role, ct));

    [HttpPost("{id:long}/transfer-owner/{userId:long}")]
    public async Task<IActionResult> TransferOwner(long id, long userId, CancellationToken ct) =>
        Data(await conversations.TransferOwnerAsync(id, userId, ct));

    [HttpPost("{id:long}/leave")]
    public async Task<IActionResult> Leave(long id, [FromBody] LeaveInput? input, CancellationToken ct)
    {
        await conversations.LeaveAsync(id, input?.NewOwnerId, ct);
        return Data(new { left = true });
    }

    [HttpDelete("{id:long}")]
    public async Task<IActionResult> Dissolve(long id, CancellationToken ct)
    {
        await conversations.DissolveAsync(id, ct);
        return Data(new { dissolved = true });
    }

    // ---------- Tin nhắn trong hội thoại ----------

    [HttpGet("{id:long}/messages")]
    public async Task<IActionResult> Messages(long id, [FromQuery(Name = "before_id")] long? beforeId,
        [FromQuery(Name = "after_id")] long? afterId, [FromQuery] int limit = 50, CancellationToken ct = default) =>
        Data(await messages.ListAsync(id, beforeId, afterId, limit, ct));

    [HttpPost("{id:long}/messages")]
    [EnableRateLimiting("send")]
    [RequestSizeLimit(MaxMessageRequestBytes)]
    [RequestFormLimits(MultipartBodyLengthLimit = MaxMessageRequestBytes)]
    public async Task<IActionResult> Send(long id,
        [FromForm] string? content,
        [FromForm(Name = "reply_to_id")] long? replyToId,
        [FromForm(Name = "mentions[]")] List<long>? mentions,
        [FromForm(Name = "mention_all")] bool mentionAll,
        [FromForm(Name = "attachments[]")] List<IFormFile>? attachments,
        CancellationToken ct)
    {
        var files = (attachments ?? []).Select(f => new UploadedFile(f.FileName, f.Length, f.OpenReadStream)).ToList();
        return Data(await messages.SendAsync(id, new SendMessageInput(content, replyToId, mentions, mentionAll, files), ct));
    }

    [HttpGet("{id:long}/pins")]
    public async Task<IActionResult> Pins(long id, CancellationToken ct) => Data(await messages.PinsAsync(id, ct));

    [HttpGet("{id:long}/messages/search")]
    public async Task<IActionResult> Search(long id, [FromQuery] string? q, CancellationToken ct) => Data(await messages.SearchAsync(id, q, ct));
}
