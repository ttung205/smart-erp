using Chat.Application.Common.Interfaces;

namespace Chat.Infrastructure.Realtime;

/// <summary>Đếm kết nối SignalR theo user: một người mở nhiều tab vẫn tính là một người online.</summary>
public sealed class PresenceTracker : IPresenceTracker
{
    private readonly Dictionary<long, HashSet<string>> _connections = new();
    private readonly object _lock = new();

    /// <returns>true nếu đây là kết nối đầu tiên (người dùng vừa online).</returns>
    public bool Add(long userId, string connectionId)
    {
        lock (_lock)
        {
            if (!_connections.TryGetValue(userId, out var set)) _connections[userId] = set = [];
            set.Add(connectionId);
            return set.Count == 1;
        }
    }

    /// <returns>true nếu đây là kết nối cuối cùng (người dùng vừa offline).</returns>
    public bool Remove(long userId, string connectionId)
    {
        lock (_lock)
        {
            if (!_connections.TryGetValue(userId, out var set) || !set.Remove(connectionId)) return false;
            if (set.Count > 0) return false;
            _connections.Remove(userId);
            return true;
        }
    }

    public bool IsOnline(long userId)
    {
        lock (_lock) return _connections.ContainsKey(userId);
    }

    public IReadOnlyCollection<long> OnlineUserIds()
    {
        lock (_lock) return _connections.Keys.ToList();
    }
}
