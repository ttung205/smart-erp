using System.Text.Json.Serialization;

namespace Purchase.Application.Common.Models;

/// <summary>
/// Cấu trúc Envelope chuẩn hóa toàn hệ thống theo docs/contracts/api-spec.md
/// </summary>
public class ApiEnvelope<T>
{
    [JsonPropertyName("success")]
    public bool Success { get; set; }

    [JsonPropertyName("data")]
    public T? Data { get; set; }

    [JsonPropertyName("error")]
    public ApiError? Error { get; set; }

    [JsonPropertyName("meta")]
    public ApiMeta Meta { get; set; } = new();

    public static ApiEnvelope<T> Ok(T data, ApiMeta? meta = null)
    {
        return new ApiEnvelope<T>
        {
            Success = true,
            Data = data,
            Meta = meta ?? new ApiMeta()
        };
    }

    public static ApiEnvelope<T> Fail(string code, string message, object? details = null, string? correlationId = null)
    {
        return new ApiEnvelope<T>
        {
            Success = false,
            Data = default,
            Error = new ApiError
            {
                Code = code,
                Message = message,
                Details = details
            },
            Meta = new ApiMeta
            {
                CorrelationId = correlationId ?? string.Empty
            }
        };
    }
}

public class ApiError
{
    [JsonPropertyName("code")]
    public string Code { get; set; } = string.Empty;

    [JsonPropertyName("message")]
    public string Message { get; set; } = string.Empty;

    [JsonPropertyName("details")]
    public object? Details { get; set; }
}

public class ApiMeta
{
    [JsonPropertyName("correlation_id")]
    public string CorrelationId { get; set; } = string.Empty;

    [JsonPropertyName("timestamp")]
    public long Timestamp { get; set; } = DateTimeOffset.UtcNow.ToUnixTimeSeconds();

    [JsonPropertyName("pagination")]
    public PaginationMeta? Pagination { get; set; }
}

public class PaginationMeta
{
    [JsonPropertyName("current_page")]
    public int CurrentPage { get; set; }

    [JsonPropertyName("per_page")]
    public int PerPage { get; set; }

    [JsonPropertyName("total_items")]
    public int TotalItems { get; set; }

    [JsonPropertyName("total_pages")]
    public int TotalPages { get; set; }
}
