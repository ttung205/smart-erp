using System.Collections.Concurrent;
using System.Text.Json;
using Microsoft.AspNetCore.Http.Connections;
using Microsoft.AspNetCore.SignalR.Client;
using Xunit;

namespace Chat.Tests;

public sealed class RealtimeFactory : IDisposable
{
    public ChatApiFactory Api { get; } = new() { RecordEvents = false };
    public void Dispose() => Api.Dispose();
}

/// <summary>Kết nối SignalR thật (long polling qua TestServer): token trên query, sự kiện chỉ tới đúng thành viên.</summary>
public class RealtimeTests(RealtimeFactory fixture) : IClassFixture<RealtimeFactory>
{
    private const string Base = "/api/v1/chat";
    private ChatApiFactory Api => fixture.Api;

    private async Task<(HubConnection Connection, ConcurrentQueue<(string Event, JsonElement Payload)> Received)> ConnectAsync(long userId)
    {
        var received = new ConcurrentQueue<(string, JsonElement)>();
        var connection = new HubConnectionBuilder()
            .WithUrl($"{Api.Server.BaseAddress}api/v1/chat/hub", options =>
            {
                options.Transports = HttpTransportType.LongPolling;
                options.HttpMessageHandlerFactory = _ => Api.Server.CreateHandler();
                options.AccessTokenProvider = () => Task.FromResult<string?>(ChatApiFactory.Token(userId, ChatApiFactory.Secret));
            })
            .Build();
        foreach (var name in new[] { "message.created", "typing", "conversation.upserted" })
            connection.On<JsonElement>(name, payload => received.Enqueue((name, payload)));
        await connection.StartAsync();
        return (connection, received);
    }

    private static async Task<JsonElement> WaitForAsync(ConcurrentQueue<(string Event, JsonElement Payload)> queue, string eventName)
    {
        for (var i = 0; i < 50; i++)
        {
            var hit = queue.FirstOrDefault(x => x.Event == eventName);
            if (hit.Event != null) return hit.Payload;
            await Task.Delay(100);
        }
        throw new Xunit.Sdk.XunitException($"Không nhận được sự kiện {eventName}");
    }

    [Fact]
    public async Task Message_is_pushed_only_to_members_in_snake_case()
    {
        var conversation = await (await Api.As(1).PostJson($"{Base}/conversations/direct", new { user_id = 2 })).DataAsync();
        var id = conversation.GetProperty("id").GetInt64();

        var (member, memberEvents) = await ConnectAsync(2);
        var (outsider, outsiderEvents) = await ConnectAsync(3);
        try
        {
            await (await Api.As(1).SendText(id, "realtime")).DataAsync();

            var payload = await WaitForAsync(memberEvents, "message.created");
            var message = payload.GetProperty("message");
            Assert.Equal(id, message.GetProperty("conversation_id").GetInt64());
            Assert.Equal("realtime", message.GetProperty("content").GetString());

            await Task.Delay(500);
            Assert.DoesNotContain(outsiderEvents, e => e.Event == "message.created");
        }
        finally
        {
            await member.DisposeAsync();
            await outsider.DisposeAsync();
        }
    }

    [Fact]
    public async Task Typing_reaches_other_members_but_outsiders_cannot_send_it()
    {
        var conversation = await (await Api.As(4).PostJson($"{Base}/conversations/direct", new { user_id = 5 })).DataAsync();
        var id = conversation.GetProperty("id").GetInt64();

        var (typer, _) = await ConnectAsync(4);
        var (partner, partnerEvents) = await ConnectAsync(5);
        var (outsider, _) = await ConnectAsync(1);
        try
        {
            await outsider.InvokeAsync("Typing", id);
            await Task.Delay(500);
            Assert.DoesNotContain(partnerEvents, e => e.Event == "typing");

            await typer.InvokeAsync("Typing", id);
            var typing = await WaitForAsync(partnerEvents, "typing");
            Assert.Equal(4, typing.GetProperty("user_id").GetInt64());
        }
        finally
        {
            await typer.DisposeAsync();
            await partner.DisposeAsync();
            await outsider.DisposeAsync();
        }
    }

    [Fact]
    public async Task Hub_rejects_connection_without_token()
    {
        var connection = new HubConnectionBuilder()
            .WithUrl($"{Api.Server.BaseAddress}api/v1/chat/hub", options =>
            {
                options.Transports = HttpTransportType.LongPolling;
                options.HttpMessageHandlerFactory = _ => Api.Server.CreateHandler();
            })
            .Build();
        await Assert.ThrowsAnyAsync<Exception>(() => connection.StartAsync());
    }
}
