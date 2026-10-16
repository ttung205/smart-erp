using System.Text.Json.Serialization;

namespace Chat.Application.DTOs;

public class UserStatusDto
{
    [JsonPropertyName("user_id")]
    public int UserId { get; set; }

    [JsonPropertyName("is_online")]
    public bool IsOnline { get; set; }

    [JsonPropertyName("last_seen_at")]
    public DateTime? LastSeenAt { get; set; }
}
