using Chat.Application.Common.Models;
using Chat.Application.Services;
using Microsoft.AspNetCore.Mvc;

namespace Chat.Api.Controllers;

[ApiController]
[Route("api/v1/chat")]
public class UsersController(UserService users) : ControllerBase
{
    public record AliasInput(string? Alias);

    private static OkObjectResult Data<T>(T data) => new(ApiEnvelope<T>.Ok(data));

    [HttpGet("users/search")]
    public async Task<IActionResult> Search([FromQuery] string? q, [FromQuery] int limit = 20, CancellationToken ct = default) =>
        Data(await users.SearchAsync(q, limit, ct));

    [HttpGet("users/{id:long}")]
    public async Task<IActionResult> Get(long id, CancellationToken ct) => Data(await users.GetAsync(id, ct));

    [HttpGet("users/online")]
    public IActionResult Online() => Data(users.OnlineUserIds());

    [HttpPut("users/{id:long}/alias")]
    public async Task<IActionResult> SetAlias(long id, [FromBody] AliasInput input, CancellationToken ct) =>
        Data(new { alias = await users.SetAliasAsync(id, input.Alias, ct) });

    [HttpGet("contacts")]
    public async Task<IActionResult> Contacts(CancellationToken ct) => Data(await users.ContactsAsync(ct));

    [HttpGet("preferences")]
    public async Task<IActionResult> Preferences(CancellationToken ct) => Data(await users.PreferencesAsync(ct));
}
