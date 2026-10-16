using System.Text.Json.Serialization;

namespace Chat.Application.DTOs;

public class ConversationMemberDto
{
    [JsonPropertyName("id")]
    public Guid Id { get; set; }

    [JsonPropertyName("user_id")]
    public int UserId { get; set; }

    [JsonPropertyName("user_name")]
    public string? UserName { get; set; }

    [JsonPropertyName("user_email")]
    public string? UserEmail { get; set; }

    [JsonPropertyName("user_avatar")]
    public string? UserAvatar { get; set; }

    [JsonPropertyName("role")]
    public string Role { get; set; } = "Member";

    [JsonPropertyName("joined_at")]
    public DateTime JoinedAt { get; set; }

    [JsonPropertyName("is_online")]
    public bool IsOnline { get; set; }
}
