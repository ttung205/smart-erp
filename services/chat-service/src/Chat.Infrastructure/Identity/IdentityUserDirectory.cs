using System.Text.Json;
using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Microsoft.Extensions.Caching.Memory;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;

namespace Chat.Infrastructure.Identity;

/// <summary>
/// Đọc danh bạ từ identity-service (GET /api/users bằng khoá nội bộ), cache 60 giây để không gọi lại mỗi request.
/// </summary>
public sealed class IdentityUserDirectory(
    HttpClient http,
    IMemoryCache cache,
    IConfiguration configuration,
    ILogger<IdentityUserDirectory> logger) : IUserDirectory
{
    private const string CacheKey = "chat:directory";
    private static readonly TimeSpan CacheDuration = TimeSpan.FromSeconds(60);

    public async Task<IReadOnlyDictionary<long, DirectoryUser>> GetUsersAsync(CancellationToken ct)
    {
        if (cache.TryGetValue(CacheKey, out IReadOnlyDictionary<long, DirectoryUser>? cached) && cached != null) return cached;

        var baseUrl = (configuration["Services:Identity"] ?? "http://localhost:8001").TrimEnd('/');
        var key = configuration["Services:InternalKey"];
        if (string.IsNullOrWhiteSpace(key)) throw new InvalidOperationException("Services:InternalKey chưa được cấu hình.");

        try
        {
            using var request = new HttpRequestMessage(HttpMethod.Get, $"{baseUrl}/api/users");
            request.Headers.Add("X-Internal-Service-Key", key);
            using var response = await http.SendAsync(request, ct);
            response.EnsureSuccessStatusCode();
            using var doc = JsonDocument.Parse(await response.Content.ReadAsStringAsync(ct));

            var users = new Dictionary<long, DirectoryUser>();
            foreach (var u in doc.RootElement.GetProperty("data").EnumerateArray())
            {
                var id = u.GetProperty("id").GetInt64();
                users[id] = new DirectoryUser(
                    id,
                    ReadString(u, "name") ?? $"User #{id}",
                    ReadString(u, "email"),
                    u.TryGetProperty("department_id", out var dep) && dep.ValueKind == JsonValueKind.Number ? dep.GetInt64() : null,
                    ReadString(u, "department_name"),
                    ReadBool(u, "is_active"));
            }
            cache.Set(CacheKey, (IReadOnlyDictionary<long, DirectoryUser>)users, CacheDuration);
            return users;
        }
        catch (Exception ex) when (ex is HttpRequestException or TaskCanceledException or JsonException or KeyNotFoundException)
        {
            logger.LogWarning(ex, "Không tải được danh bạ từ identity-service");
            throw new ChatException(503, "DIRECTORY_UNAVAILABLE", "Không tải được danh bạ nhân viên, vui lòng thử lại.");
        }
    }

    private static string? ReadString(JsonElement e, string name) =>
        e.TryGetProperty(name, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() : null;

    // is_active trả về true/false hoặc 0/1 tuỳ CSDL
    private static bool ReadBool(JsonElement e, string name) =>
        !e.TryGetProperty(name, out var v) || v.ValueKind switch
        {
            JsonValueKind.False => false,
            JsonValueKind.Number => v.GetInt32() != 0,
            JsonValueKind.String => v.GetString() is not ("0" or "false"),
            _ => true
        };
}
