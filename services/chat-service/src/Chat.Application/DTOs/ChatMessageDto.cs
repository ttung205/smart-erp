using System.Text.Json.Serialization;

namespace Chat.Application.DTOs;

public class ChatMessageDto
{
    [JsonPropertyName("id")]
    public Guid Id { get; set; }

    [JsonPropertyName("conversation_id")]
    public Guid ConversationId { get; set; }

    [JsonPropertyName("sender_id")]
    public int SenderId { get; set; }

    [JsonPropertyName("sender_name")]
    public string SenderName { get; set; } = string.Empty;

    [JsonPropertyName("sender_avatar")]
    public string? SenderAvatar { get; set; }

    [JsonPropertyName("message_type")]
    public string MessageType { get; set; } = "TEXT";

    [JsonPropertyName("content")]
    public string Content { get; set; } = string.Empty;

    [JsonPropertyName("attachment_url")]
    public string? AttachmentUrl { get; set; }

    [JsonPropertyName("metadata_json")]
    public string? MetadataJson { get; set; }

    [JsonPropertyName("created_at")]
    public DateTime CreatedAt { get; set; }

    [JsonPropertyName("read_by")]
    public List<int> ReadBy { get; set; } = new();
}
