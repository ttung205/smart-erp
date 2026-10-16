using Chat.Api.Hubs;
using Chat.Application.Common.Interfaces;
using Chat.Application.DTOs;
using Microsoft.AspNetCore.SignalR;

namespace Chat.Api.Services;

public class ChatHubService : IChatHubService
{
    private readonly IHubContext<ChatHub, IChatClient> _hubContext;

    public ChatHubService(IHubContext<ChatHub, IChatClient> hubContext)
    {
        _hubContext = hubContext;
    }

    public async Task SendMessageToConversationAsync(Guid conversationId, ChatMessageDto message)
    {
        await _hubContext.Clients.Group($"conv_{conversationId}").ReceiveMessage(message);
    }

    public async Task SendMessageToChannelAsync(string departmentCode, ChatMessageDto message)
    {
        await _hubContext.Clients.Group($"dept_{departmentCode.ToLower()}").ReceiveMessage(message);
    }

    public async Task SendMessageToUserAsync(int userId, ChatMessageDto message)
    {
        await _hubContext.Clients.Group($"user_{userId}").ReceiveMessage(message);
    }

    public async Task BroadcastUserStatusAsync(int userId, bool isOnline)
    {
        await _hubContext.Clients.All.UserOnlineStatus(userId, isOnline);
    }

    public async Task NotifyMessageReadAsync(Guid messageId, int userId, DateTime readAt, int senderId)
    {
        await _hubContext.Clients.Group($"user_{senderId}").MessageRead(messageId, userId, readAt);
    }
}
