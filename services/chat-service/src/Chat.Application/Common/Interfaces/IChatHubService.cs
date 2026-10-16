using Chat.Application.DTOs;

namespace Chat.Application.Common.Interfaces;

public interface IChatHubService
{
    Task SendMessageToConversationAsync(Guid conversationId, ChatMessageDto message);
    Task SendMessageToChannelAsync(string departmentCode, ChatMessageDto message);
    Task SendMessageToUserAsync(int userId, ChatMessageDto message);
    Task BroadcastUserStatusAsync(int userId, bool isOnline);
    Task NotifyMessageReadAsync(Guid messageId, int userId, DateTime readAt, int senderId);
}
