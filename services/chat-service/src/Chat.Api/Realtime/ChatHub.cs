using System.Security.Claims;
using Chat.Application.Services;
using Chat.Infrastructure.Realtime;
using Microsoft.AspNetCore.SignalR;

namespace Chat.Api.Realtime;

/// <summary>
/// Hub chỉ dùng cho chiều server → client (sự kiện gửi theo nhóm "user_{id}") và tín hiệu đang soạn tin.
/// Mọi thao tác ghi đều đi qua REST để kiểm tra quyền ở một chỗ.
/// Client không tự vào được nhóm của hội thoại, nên người bị xoá khỏi nhóm thôi nhận tin ngay lập tức.
/// </summary>
public class ChatHub(PresenceTracker presence, MessageService messages, HttpCurrentUser currentUser) : Hub
{
    public static string UserGroup(long userId) => $"user_{userId}";

    private long UserId => long.TryParse(Context.User?.FindFirstValue(ClaimTypes.NameIdentifier), out var id) ? id : 0;

    public override async Task OnConnectedAsync()
    {
        if (UserId <= 0)
        {
            Context.Abort();
            return;
        }
        await Groups.AddToGroupAsync(Context.ConnectionId, UserGroup(UserId));
        if (presence.Add(UserId, Context.ConnectionId))
            await Clients.Others.SendAsync(ChatServiceBase.Events.Presence, new { user_id = UserId, online = true });
        await base.OnConnectedAsync();
    }

    public override async Task OnDisconnectedAsync(Exception? exception)
    {
        if (UserId > 0 && presence.Remove(UserId, Context.ConnectionId))
            await Clients.Others.SendAsync(ChatServiceBase.Events.Presence, new { user_id = UserId, online = false });
        await base.OnDisconnectedAsync(exception);
    }

    /// <summary>Báo đang soạn tin cho các thành viên khác; người ngoài hội thoại bị bỏ qua.</summary>
    public async Task Typing(long conversationId)
    {
        currentUser.UseConnection(Context.User);
        var recipients = await messages.TypingRecipientsAsync(conversationId, Context.ConnectionAborted);
        if (recipients == null || recipients.Count == 0) return;
        var name = Context.User?.FindFirstValue(ClaimTypes.Name) ?? $"User #{UserId}";
        await Clients.Groups(recipients.Select(UserGroup).ToList())
            .SendAsync(ChatServiceBase.Events.Typing, new { conversation_id = conversationId, user_id = UserId, user_name = name });
    }
}

public sealed class SignalRChatNotifier(IHubContext<ChatHub> hub) : Chat.Application.Common.Interfaces.IChatNotifier
{
    public Task ToUsersAsync(IEnumerable<long> userIds, string eventName, object payload)
    {
        var groups = userIds.Distinct().Select(ChatHub.UserGroup).ToList();
        return groups.Count == 0 ? Task.CompletedTask : hub.Clients.Groups(groups).SendAsync(eventName, payload);
    }

    public Task ToAllAsync(string eventName, object payload) => hub.Clients.All.SendAsync(eventName, payload);
}
