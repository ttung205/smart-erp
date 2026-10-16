using System.Net;
using System.Net.Http.Json;
using System.Text.Json;
using Xunit;

namespace Chat.Tests;

public class ChatApiTests(ChatApiFactory factory) : IClassFixture<ChatApiFactory>
{
    private const string Base = "/api/v1/chat";

    private async Task<long> OpenDirect(long me, long other) =>
        (await (await factory.As(me).PostJson($"{Base}/conversations/direct", new { user_id = other })).DataAsync()).GetProperty("id").GetInt64();

    private async Task<long> CreateGroup(long owner, params long[] members) =>
        (await (await factory.As(owner).PostJson($"{Base}/conversations/group", new { title = "Nhóm test", user_ids = members })).DataAsync())
        .GetProperty("id").GetInt64();

    private async Task<long> Send(long user, long conversationId, string text) =>
        (await (await factory.As(user).SendText(conversationId, text)).DataAsync()).GetProperty("id").GetInt64();

    private async Task<JsonElement[]> Messages(long user, long conversationId) =>
        (await (await factory.As(user).GetAsync($"{Base}/conversations/{conversationId}/messages")).DataAsync())
        .GetProperty("data").EnumerateArray().ToArray();

    // ---------- Xác thực ----------

    [Fact]
    public async Task Requests_without_valid_token_are_rejected()
    {
        Assert.Equal(HttpStatusCode.Unauthorized, (await factory.CreateClient().GetAsync($"{Base}/conversations")).StatusCode);

        var forged = factory.CreateClient();
        forged.DefaultRequestHeaders.Authorization = new("Bearer", ChatApiFactory.Token(1, "wrong-secret"));
        Assert.Equal(HttpStatusCode.Unauthorized, (await forged.GetAsync($"{Base}/conversations")).StatusCode);
    }

    // ---------- Chat 1-1 ----------

    [Fact]
    public async Task Direct_conversation_is_reused_and_private()
    {
        var id = await OpenDirect(1, 2);
        Assert.Equal(id, await OpenDirect(2, 1));
        await Send(1, id, "xin chào");

        // Người ngoài không đọc, không gửi, không tải tệp được (404 để không lộ hội thoại tồn tại)
        var outsider = factory.As(3);
        Assert.Equal(HttpStatusCode.NotFound, (await outsider.GetAsync($"{Base}/conversations/{id}/messages")).StatusCode);
        Assert.Equal(HttpStatusCode.NotFound, (await outsider.SendText(id, "chen ngang")).StatusCode);
    }

    [Fact]
    public async Task Cannot_chat_with_self_or_inactive_user()
    {
        Assert.Equal(HttpStatusCode.UnprocessableEntity, (await factory.As(1).PostJson($"{Base}/conversations/direct", new { user_id = 1 })).StatusCode);
        Assert.Equal(HttpStatusCode.UnprocessableEntity, (await factory.As(1).PostJson($"{Base}/conversations/direct", new { user_id = 6 })).StatusCode);
    }

    [Fact]
    public async Task Mentions_are_only_allowed_in_groups()
    {
        var id = await OpenDirect(1, 4);
        var response = await factory.As(1).SendText(id, "@Nhân viên 4", f => f.Add(new StringContent("4"), "mentions[]"));
        Assert.Equal(HttpStatusCode.UnprocessableEntity, response.StatusCode);
    }

    // ---------- Nhóm ----------

    [Fact]
    public async Task Creating_group_notifies_every_member()
    {
        factory.Notifier.Clear();
        var id = await CreateGroup(1, 2, 3);
        var upserts = factory.Notifier.All.Where(s => s.Event == "conversation.upserted").SelectMany(s => s.UserIds).ToHashSet();
        Assert.True(upserts.SetEquals([1L, 2L, 3L]));

        var system = (await Messages(2, id)).Single();
        Assert.Equal("system", system.GetProperty("type").GetString());
    }

    [Fact]
    public async Task Only_owner_and_deputy_manage_group()
    {
        var id = await CreateGroup(1, 2, 3);
        var member = factory.As(2);
        Assert.Equal(HttpStatusCode.Forbidden, (await member.PatchAsJsonAsync($"{Base}/conversations/{id}", new { title = "Đổi" })).StatusCode);

        // Trưởng nhóm bổ nhiệm 2 làm phó; phó được đổi tên nhưng không xoá được trưởng nhóm
        await (await factory.As(1).PutAsJsonAsync($"{Base}/conversations/{id}/members/2/role", new { role = "deputy" })).DataAsync();
        await (await member.PatchAsJsonAsync($"{Base}/conversations/{id}", new { title = "Đổi tên" })).DataAsync();
        Assert.Equal(HttpStatusCode.Forbidden, (await member.DeleteAsync($"{Base}/conversations/{id}/members/1")).StatusCode);
    }

    [Fact]
    public async Task Removed_member_loses_access_and_is_notified()
    {
        var id = await CreateGroup(1, 2, 3);
        factory.Notifier.Clear();
        await (await factory.As(1).DeleteAsync($"{Base}/conversations/{id}/members/3")).DataAsync();

        Assert.Contains(factory.Notifier.All, s => s.Event == "conversation.removed" && s.UserIds.SequenceEqual([3L]));
        // Tin sau khi bị xoá không còn được đẩy tới người đó
        factory.Notifier.Clear();
        await Send(1, id, "sau khi xoá");
        Assert.DoesNotContain(factory.Notifier.All, s => s.UserIds.Contains(3L));
        Assert.Equal(HttpStatusCode.NotFound, (await factory.As(3).GetAsync($"{Base}/conversations/{id}/messages")).StatusCode);
    }

    [Fact]
    public async Task Owner_must_hand_over_before_leaving()
    {
        var id = await CreateGroup(1, 2, 3);
        Assert.Equal(HttpStatusCode.UnprocessableEntity, (await factory.As(1).PostJson($"{Base}/conversations/{id}/leave", new { })).StatusCode);

        await (await factory.As(1).PostJson($"{Base}/conversations/{id}/leave", new { new_owner_id = 2 })).DataAsync();
        var conversation = await (await factory.As(2).GetAsync($"{Base}/conversations/{id}")).DataAsync();
        Assert.Equal("owner", conversation.GetProperty("my_role").GetString());
        Assert.DoesNotContain(conversation.GetProperty("members").EnumerateArray(), m => m.GetProperty("user_id").GetInt64() == 1);
    }

    [Fact]
    public async Task Owner_can_dissolve_group()
    {
        var id = await CreateGroup(1, 2);
        Assert.Equal(HttpStatusCode.Forbidden, (await factory.As(2).DeleteAsync($"{Base}/conversations/{id}")).StatusCode);
        factory.Notifier.Clear();
        await (await factory.As(1).DeleteAsync($"{Base}/conversations/{id}")).DataAsync();
        Assert.Contains(factory.Notifier.All, s => s.Event == "conversation.removed" && s.UserIds.Contains(2L));
        Assert.Equal(HttpStatusCode.NotFound, (await factory.As(1).GetAsync($"{Base}/conversations/{id}")).StatusCode);
    }

    // ---------- Tin nhắn ----------

    [Fact]
    public async Task Recall_rules_follow_sender_and_group_roles()
    {
        var id = await CreateGroup(1, 2, 3);
        var byMember = await Send(3, id, "tin của thành viên");

        // Thành viên khác không thu hồi được
        Assert.Equal(HttpStatusCode.Forbidden, (await factory.As(2).PostAsync($"{Base}/messages/{byMember}/recall", null)).StatusCode);

        // Trưởng nhóm thu hồi được tin của thành viên
        var recalled = await (await factory.As(1).PostAsync($"{Base}/messages/{byMember}/recall", null)).DataAsync();
        Assert.True(recalled.GetProperty("recalled").GetBoolean());
        Assert.Equal(string.Empty, recalled.GetProperty("content").GetString());
    }

    [Fact]
    public async Task Hide_for_me_only_affects_me()
    {
        var id = await OpenDirect(2, 5);
        var messageId = await Send(5, id, "ẩn phía tôi");
        await (await factory.As(2).PostAsync($"{Base}/messages/{messageId}/hide", null)).DataAsync();

        Assert.DoesNotContain(await Messages(2, id), m => m.GetProperty("id").GetInt64() == messageId);
        Assert.Contains(await Messages(5, id), m => m.GetProperty("id").GetInt64() == messageId);
    }

    [Fact]
    public async Task Reaction_toggles_one_per_user()
    {
        var id = await OpenDirect(1, 5);
        var messageId = await Send(5, id, "thả tim");
        var client = factory.As(1);

        var once = await (await client.PostJson($"{Base}/messages/{messageId}/reactions", new { emoji = "❤️" })).DataAsync();
        Assert.Single(once.GetProperty("reactions").EnumerateArray());
        var changed = await (await client.PostJson($"{Base}/messages/{messageId}/reactions", new { emoji = "👍" })).DataAsync();
        Assert.Equal("👍", changed.GetProperty("reactions")[0].GetProperty("emoji").GetString());
        var removed = await (await client.PostJson($"{Base}/messages/{messageId}/reactions", new { emoji = "👍" })).DataAsync();
        Assert.Empty(removed.GetProperty("reactions").EnumerateArray());
    }

    [Fact]
    public async Task At_most_five_pinned_messages()
    {
        var id = await OpenDirect(3, 4);
        for (var i = 0; i < 5; i++)
            await (await factory.As(3).PostAsync($"{Base}/messages/{await Send(3, id, $"ghim {i}")}/pin", null)).DataAsync();
        var sixth = await Send(3, id, "ghim 6");
        Assert.Equal(HttpStatusCode.UnprocessableEntity, (await factory.As(4).PostAsync($"{Base}/messages/{sixth}/pin", null)).StatusCode);
    }

    [Fact]
    public async Task Unread_counts_and_read_pointer()
    {
        var id = await OpenDirect(4, 5);
        await Send(4, id, "một");
        var last = await Send(4, id, "hai");

        var total = await (await factory.As(5).GetAsync($"{Base}/conversations/unread-count")).DataAsync();
        Assert.True(total.GetProperty("count").GetInt32() >= 2);

        var afterRead = await (await factory.As(5).PostJson($"{Base}/conversations/{id}/read", new { message_id = last })).DataAsync();
        var conversation = await (await factory.As(5).GetAsync($"{Base}/conversations/{id}")).DataAsync();
        Assert.Equal(0, conversation.GetProperty("unread_count").GetInt32());
        Assert.Equal(total.GetProperty("count").GetInt32() - 2, afterRead.GetProperty("unread_total").GetInt32());
    }

    [Fact]
    public async Task Svg_upload_is_rejected_and_files_are_member_only()
    {
        var id = await OpenDirect(1, 2);
        var svg = await factory.As(1).SendText(id, "", f => f.Add(new ByteArrayContent("<svg/>"u8.ToArray()), "attachments[]", "x.svg"));
        Assert.Equal(HttpStatusCode.UnprocessableEntity, svg.StatusCode);

        var sent = await (await factory.As(1).SendText(id, "", f => f.Add(new ByteArrayContent("%PDF-1.4"u8.ToArray()), "attachments[]", "bao-gia.pdf"))).DataAsync();
        var attachmentId = sent.GetProperty("attachments")[0].GetProperty("id").GetInt64();
        Assert.Equal("file", sent.GetProperty("type").GetString());

        Assert.Equal(HttpStatusCode.OK, (await factory.As(2).GetAsync($"{Base}/attachments/{attachmentId}")).StatusCode);
        Assert.Equal(HttpStatusCode.NotFound, (await factory.As(3).GetAsync($"{Base}/attachments/{attachmentId}")).StatusCode);
    }

    [Fact]
    public async Task Forward_requires_membership_of_every_target()
    {
        var source = await OpenDirect(1, 2);
        var messageId = await Send(1, source, "chuyển tiếp");
        var mine = await OpenDirect(1, 3);
        var notMine = await OpenDirect(4, 5);

        Assert.Equal(HttpStatusCode.UnprocessableEntity,
            (await factory.As(1).PostJson($"{Base}/messages/{messageId}/forward", new { conversation_ids = new[] { mine, notMine } })).StatusCode);

        await (await factory.As(1).PostJson($"{Base}/messages/{messageId}/forward", new { conversation_ids = new[] { mine } })).DataAsync();
        var forwarded = (await Messages(3, mine)).Last();
        Assert.Equal("Nhân viên 1", forwarded.GetProperty("forwarded_from_name").GetString());
    }

    [Fact]
    public async Task Timestamps_are_returned_as_utc()
    {
        var id = await OpenDirect(2, 3);
        await Send(2, id, "giờ UTC");
        var raw = await (await factory.As(2).GetAsync($"{Base}/conversations/{id}/messages")).Content.ReadAsStringAsync();
        var createdAt = JsonDocument.Parse(raw).RootElement.GetProperty("data").GetProperty("data")[0].GetProperty("created_at").GetString();
        Assert.EndsWith("Z", createdAt);
    }
}
