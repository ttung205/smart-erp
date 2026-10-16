using System.Text.Json.Serialization;

namespace Chat.Application.DTOs;

public class ConversationDto
{
    [JsonPropertyName("id")]
    public Guid Id { get; set; }

    [JsonPropertyName("type")]
    public string Type { get; set; } = "Channel"; // "Channel" or "Direct"

    [JsonPropertyName("code")]
    public string Code { get; set; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("department_code")]
    public string? DepartmentCode { get; set; }

    [JsonPropertyName("description")]
    public string? Description { get; set; }

    [JsonPropertyName("created_at")]
    public DateTime CreatedAt { get; set; }

    [JsonPropertyName("last_message")]
    public ChatMessageDto? LastMessage { get; set; }

    [JsonPropertyName("unread_count")]
    public int UnreadCount { get; set; }

    [JsonPropertyName("members")]
    public List<ConversationMemberDto> Members { get; set; } = new();
}
