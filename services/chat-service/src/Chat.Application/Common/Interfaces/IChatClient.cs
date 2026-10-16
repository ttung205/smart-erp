using Chat.Application.DTOs;

namespace Chat.Application.Common.Interfaces;

/// <summary>
/// Strongly-typed SignalR Client interface theo docs/csharp-realtime-chat-roadmap.md
/// </summary>
public interface IChatClient
{
    Task ReceiveMessage(ChatMessageDto message);
    Task UserTyping(string conversationId, int userId, string userName, bool isTyping);
    Task MessageRead(Guid messageId, int userId, DateTime readAt);
    Task UserOnlineStatus(int userId, bool isOnline);
}
