using System.Text.Json.Serialization;

namespace Chat.Application.DTOs;

public class CreateConversationRequest
{
    [JsonPropertyName("type")]
    public string Type { get; set; } = "Channel"; // "Channel" or "Direct"

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("code")]
    public string? Code { get; set; }

    [JsonPropertyName("department_code")]
    public string? DepartmentCode { get; set; }

    [JsonPropertyName("description")]
    public string? Description { get; set; }

    [JsonPropertyName("member_ids")]
    public List<int> MemberIds { get; set; } = new();
}
