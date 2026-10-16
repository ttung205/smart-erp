using System.Collections.Concurrent;

namespace Chat.Infrastructure.Realtime;

public class HubConnectionTracker
{
    private readonly ConcurrentDictionary<int, HashSet<string>> _userConnections = new();
    private readonly object _lock = new();

    public bool AddConnection(int userId, string connectionId)
    {
        lock (_lock)
        {
            if (!_userConnections.TryGetValue(userId, out var connections))
            {
                connections = new HashSet<string>();
                _userConnections[userId] = connections;
            }

            var isFirstConnection = connections.Count == 0;
            connections.Add(connectionId);
            return isFirstConnection;
        }
    }

    public bool RemoveConnection(int userId, string connectionId)
    {
        lock (_lock)
        {
            if (!_userConnections.TryGetValue(userId, out var connections))
            {
                return false;
            }

            connections.Remove(connectionId);

            if (connections.Count == 0)
            {
                _userConnections.TryRemove(userId, out _);
                return true; // Last connection removed -> user offline
            }

            return false;
        }
    }

    public List<string> GetConnections(int userId)
    {
        lock (_lock)
        {
            return _userConnections.TryGetValue(userId, out var connections)
                ? connections.ToList()
                : new List<string>();
        }
    }

    public bool IsUserOnline(int userId)
    {
        lock (_lock)
        {
            return _userConnections.ContainsKey(userId);
        }
    }

    public List<int> GetOnlineUsers()
    {
        lock (_lock)
        {
            return _userConnections.Keys.ToList();
        }
    }
}
