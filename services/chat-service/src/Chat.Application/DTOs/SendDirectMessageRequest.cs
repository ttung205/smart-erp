using System.Text.Json.Serialization;

namespace Chat.Application.DTOs;

public class SendDirectMessageRequest
{
    [JsonPropertyName("recipient_id")]
    public int RecipientId { get; set; }

    [JsonPropertyName("recipient_name")]
    public string? RecipientName { get; set; }

    [JsonPropertyName("conversation_id")]
    public Guid? ConversationId { get; set; }

    [JsonPropertyName("content")]
    public string Content { get; set; } = string.Empty;

    [JsonPropertyName("attachment_url")]
    public string? AttachmentUrl { get; set; }
}
