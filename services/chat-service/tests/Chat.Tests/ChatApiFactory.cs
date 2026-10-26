using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc.Testing;
using Microsoft.AspNetCore.TestHost;
using Microsoft.Extensions.DependencyInjection;

namespace Chat.Tests;

/// <summary>Chạy Chat.Api thật trên MongoDB (database riêng cho mỗi factory), danh bạ giả và bộ ghi sự kiện realtime.</summary>
public sealed class ChatApiFactory : WebApplicationFactory<Program>
{
    public const string Secret = "test_secret_for_chat_service_tests_2026";

    /// <summary>Cần MongoDB đang chạy (docker compose up -d mongo); đổi địa chỉ bằng biến môi trường CHAT_TEST_MONGO.</summary>
    private static readonly string MongoUri =
        Environment.GetEnvironmentVariable("CHAT_TEST_MONGO") ?? "mongodb://127.0.0.1:27017/?directConnection=true";

    private readonly string _id = Guid.NewGuid().ToString("N");
    private string _root => Path.Combine(Path.GetTempPath(), "chat-tests-" + _id);
    private string _database => "erp_chat_test_" + _id;

    public RecordingNotifier Notifier { get; } = new();

    /// <summary>false: dùng notifier SignalR thật để test hub.</summary>
    public bool RecordEvents { get; init; } = true;

    protected override void ConfigureWebHost(IWebHostBuilder builder)
    {
        Directory.CreateDirectory(_root);
        builder.UseSetting("ConnectionStrings:ChatMongoConnection", MongoUri);
        builder.UseSetting("Mongo:Database", _database);
        builder.UseSetting("Storage:Root", Path.Combine(_root, "files"));
        builder.UseSetting("Jwt:Secret", Secret);
        builder.UseSetting("Services:InternalKey", "test-internal-key");
        builder.ConfigureTestServices(services =>
        {
            services.AddSingleton<IUserDirectory, FakeDirectory>();
            if (RecordEvents) services.AddSingleton<IChatNotifier>(Notifier);
        });
    }

    public HttpClient As(long userId)
    {
        var client = CreateClient();
        client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", Token(userId, Secret));
        return client;
    }

    public static string Token(long userId, string secret)
    {
        static string B64(byte[] bytes) => Convert.ToBase64String(bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_');
        var header = B64(JsonSerializer.SerializeToUtf8Bytes(new { alg = "HS256", typ = "JWT" }));
        var payload = B64(JsonSerializer.SerializeToUtf8Bytes(new
        {
            iss = "identity-service", sub = userId.ToString(), name = FakeDirectory.NameOf(userId),
            exp = DateTimeOffset.UtcNow.AddHours(1).ToUnixTimeSeconds()
        }));
        var signature = B64(HMACSHA256.HashData(Encoding.UTF8.GetBytes(secret), Encoding.UTF8.GetBytes($"{header}.{payload}")));
        return $"{header}.{payload}.{signature}";
    }

    protected override void Dispose(bool disposing)
    {
        base.Dispose(disposing);
        try { new MongoDB.Driver.MongoClient(MongoUri).DropDatabase(_database); } catch (Exception) { }
        try { Directory.Delete(_root, true); } catch (IOException) { }
    }
}

/// <summary>Nhân viên 1-5 đang làm việc (1-3 cùng phòng 10), nhân viên 6 đã nghỉ.</summary>
public sealed class FakeDirectory : IUserDirectory
{
    public static string NameOf(long id) => $"Nhân viên {id}";

    private static readonly IReadOnlyDictionary<long, DirectoryUser> Users = Enumerable.Range(1, 6)
        .ToDictionary(i => (long)i, i => new DirectoryUser(i, NameOf(i), $"u{i}@erp.local", i <= 3 ? 10 : 20, i <= 3 ? "Kinh doanh" : "Mua hàng", i != 6));

    public Task<IReadOnlyDictionary<long, DirectoryUser>> GetUsersAsync(CancellationToken ct) => Task.FromResult(Users);
}

public sealed class RecordingNotifier : IChatNotifier
{
    public record Sent(long[] UserIds, string Event, JsonElement Payload);

    private readonly List<Sent> _sent = [];

    public IReadOnlyList<Sent> All
    {
        get { lock (_sent) return _sent.ToList(); }
    }

    public void Clear()
    {
        lock (_sent) _sent.Clear();
    }

    public Task ToUsersAsync(IEnumerable<long> userIds, string eventName, object payload)
    {
        var json = JsonSerializer.SerializeToElement(payload, Json.Options);
        lock (_sent) _sent.Add(new Sent(userIds.ToArray(), eventName, json));
        return Task.CompletedTask;
    }

    public Task ToAllAsync(string eventName, object payload) => ToUsersAsync([], eventName, payload);
}

public static class Json
{
    public static readonly JsonSerializerOptions Options = new() { PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower };

    /// <summary>Đọc phần "data" của envelope.</summary>
    public static async Task<JsonElement> DataAsync(this HttpResponseMessage response)
    {
        var body = await response.Content.ReadAsStringAsync();
        if (!response.IsSuccessStatusCode) throw new Xunit.Sdk.XunitException($"{(int)response.StatusCode}: {body}");
        return JsonDocument.Parse(body).RootElement.GetProperty("data").Clone();
    }

    public static Task<HttpResponseMessage> PostJson(this HttpClient client, string url, object body) =>
        client.PostAsJsonAsync(url, body, Options);

    public static Task<HttpResponseMessage> SendText(this HttpClient client, long conversationId, string text, Action<MultipartFormDataContent>? extra = null)
    {
        var form = new MultipartFormDataContent { { new StringContent(text), "content" } };
        extra?.Invoke(form);
        return client.PostAsync($"/api/v1/chat/conversations/{conversationId}/messages", form);
    }
}
