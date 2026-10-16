using System.Text.Json.Serialization;

namespace Chat.Application.DTOs;

public class SendChannelMessageRequest
{
    [JsonPropertyName("conversation_id")]
    public Guid? ConversationId { get; set; }

    [JsonPropertyName("department_code")]
    public string? DepartmentCode { get; set; }

    [JsonPropertyName("content")]
    public string Content { get; set; } = string.Empty;

    [JsonPropertyName("attachment_url")]
    public string? AttachmentUrl { get; set; }
}
