using System.Security.Claims;
using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;

namespace Chat.Api.Realtime;

/// <summary>
/// Người dùng hiện tại lấy từ ClaimsPrincipal do JwtAuthMiddleware gắn.
/// Trong hub, HttpContext của lời gọi không chắc là của kết nối (long polling) nên hub gán User qua UseConnection.
/// </summary>
public sealed class HttpCurrentUser(IHttpContextAccessor accessor) : ICurrentUser
{
    private ClaimsPrincipal? _connectionUser;

    private ClaimsPrincipal User => _connectionUser ?? accessor.HttpContext?.User ?? new ClaimsPrincipal();

    public void UseConnection(ClaimsPrincipal? user) => _connectionUser = user;

    public long Id => long.TryParse(User.FindFirstValue(ClaimTypes.NameIdentifier), out var id) && id > 0
        ? id
        : throw new ChatException(401, "UNAUTHORIZED", "Chưa xác thực danh tính người dùng.");

    public string Name => User.FindFirstValue(ClaimTypes.Name) is { Length: > 0 } name ? name : $"User #{Id}";
}
