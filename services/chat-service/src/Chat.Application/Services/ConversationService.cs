using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Application.Dtos;
using Chat.Domain.Entities;
using Chat.Domain.Enums;

namespace Chat.Application.Services;

public class ConversationService(
    IChatRepository repository,
    ICurrentUser currentUser,
    IUserDirectory directory,
    IChatNotifier notifier,
    IFileStorage storage) : ChatServiceBase(repository, currentUser, directory, notifier)
{
    public const int MaxTitleLength = 120;
    public const int MaxGroupMembers = 200;

    // ---------- Đọc ----------

    public async Task<ConversationPageDto> ListAsync(int page, int perPage, string? search, string? filter, CancellationToken ct)
    {
        page = Math.Max(page, 1);
        perPage = Math.Clamp(perPage, 1, 100);

        var conversations = await Repo.ListConversationsOfUserAsync(Me.Id, ct);
        var lastIds = conversations.Where(c => c.LastMessageId != null).Select(c => c.LastMessageId!.Value).ToList();
        var lastMessages = (await Repo.GetMessagesAsync(lastIds, ct)).ToDictionary(m => m.Id);
        var cursors = conversations.Select(c => CursorOf(c, c.Members.First(m => m.UserId == Me.Id))).ToList();
        var unread = await UnreadCountsAsync(Me.Id, cursors, ct);
        await UsersForDisplayAsync(ct);
        var aliases = await Repo.GetAliasesAsync(Me.Id, ct);

        IEnumerable<Conversation> query = conversations;
        if (!string.IsNullOrWhiteSpace(search))
        {
            var keyword = search.Trim();
            query = query.Where(c => DisplayNames(c, aliases).Any(name => name.Contains(keyword, StringComparison.CurrentCultureIgnoreCase)));
        }
        if (filter == "unread") query = query.Where(c => unread.GetValueOrDefault(c.Id) > 0);
        else if (filter == "read") query = query.Where(c => unread.GetValueOrDefault(c.Id) == 0);

        var ordered = query
            .Select(c => (Conversation: c, Me: c.Members.First(m => m.UserId == Me.Id)))
            .OrderByDescending(x => x.Me.PinnedAt.HasValue)
            .ThenByDescending(x => x.Me.PinnedAt)
            .ThenByDescending(x => x.Conversation.LastMessageAt ?? x.Conversation.CreatedAt)
            .ThenByDescending(x => x.Conversation.Id)
            .ToList();

        var data = ordered.Skip((page - 1) * perPage).Take(perPage)
            .Select(x => ToConversationDto(x.Conversation, x.Me,
                x.Conversation.LastMessageId is { } id ? lastMessages.GetValueOrDefault(id) : null,
                unread.GetValueOrDefault(x.Conversation.Id)))
            .ToList();
        return new ConversationPageDto(data, page, perPage, ordered.Count);
    }

    /// <summary>Tên để tìm kiếm: tên nhóm, hoặc tên/tên gợi nhớ của người kia trong hội thoại 1-1.</summary>
    private IEnumerable<string> DisplayNames(Conversation c, IReadOnlyDictionary<long, string> aliases)
    {
        if (c.Type == ConversationType.Group)
        {
            if (c.Title != null) yield return c.Title;
            yield break;
        }
        foreach (var other in c.Members.Where(m => m.UserId != Me.Id))
        {
            yield return NameOf(other.UserId, other.UserName);
            if (aliases.TryGetValue(other.UserId, out var alias)) yield return alias;
        }
    }

    public async Task<ConversationDto> GetAsync(long id, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        return await BuildDtoAsync(conversation, me, ct);
    }

    private async Task<ConversationDto> BuildDtoAsync(Conversation conversation, ConversationMember me, CancellationToken ct)
    {
        await UsersForDisplayAsync(ct);
        var last = conversation.LastMessageId is { } lastId ? await Repo.GetMessageAsync(lastId, ct) : null;
        var unread = (await UnreadCountsAsync(Me.Id, [CursorOf(conversation, me)], ct)).GetValueOrDefault(conversation.Id);
        return ToConversationDto(conversation, me, last, unread);
    }

    public async Task<int> UnreadTotalAsync(CancellationToken ct)
    {
        var cursors = await Repo.GetReadCursorsAsync(Me.Id, ct);
        var muted = cursors.Where(c => c.Muted).Select(c => c.ConversationId).ToHashSet();
        var counts = await UnreadCountsAsync(Me.Id, cursors, ct);
        return counts.Where(kv => !muted.Contains(kv.Key)).Sum(kv => kv.Value);
    }

    // ---------- Tạo hội thoại ----------

    /// <summary>Tìm hoặc tạo hội thoại 1-1. Không báo cho người kia tới khi có tin đầu tiên.</summary>
    public async Task<ConversationDto> OpenDirectAsync(long userId, CancellationToken ct)
    {
        if (userId == Me.Id) throw ChatException.Invalid("Không thể tự chat với chính mình.");
        var users = await UsersForValidationAsync(ct);
        if (!users.TryGetValue(userId, out var other) || !other.IsActive)
            throw ChatException.Invalid("Người dùng không tồn tại hoặc đã ngừng hoạt động.");

        var key = $"{Math.Min(userId, Me.Id)}_{Math.Max(userId, Me.Id)}";
        var conversation = await Repo.FindDirectAsync(key, ct);
        if (conversation == null)
        {
            conversation = new Conversation { Type = ConversationType.Direct, DirectKey = key, CreatedBy = Me.Id };
            conversation.Members.Add(new ConversationMember { UserId = Me.Id, UserName = Me.Name });
            conversation.Members.Add(new ConversationMember { UserId = userId, UserName = other.Name });
            // Hai request tạo cùng lúc: unique DirectKey chặn bản thứ hai, lấy lại bản đã tạo
            if (!await Repo.TryInsertConversationAsync(conversation, ct))
                conversation = await Repo.FindDirectAsync(key, ct) ?? throw ChatException.Invalid("Không tạo được hội thoại.");
        }
        return await BuildDtoAsync(conversation, conversation.Members.First(m => m.UserId == Me.Id), ct);
    }

    /// <summary>Tìm hoặc tạo luồng thảo luận gắn với chứng từ ERP (Work Thread). Thêm người mở vào làm thành viên.</summary>
    public async Task<ConversationDto> OpenWorkThreadAsync(string? subjectType, long subjectId, string? subjectCode, string? subjectTitle, CancellationToken ct)
    {
        subjectType = subjectType?.Trim();
        if (string.IsNullOrEmpty(subjectType)) throw ChatException.Invalid("Loại chứng từ bắt buộc.");
        if (subjectId <= 0) throw ChatException.Invalid("ID chứng từ không hợp lệ.");

        subjectCode = subjectCode?.Trim();
        subjectTitle = subjectTitle?.Trim();

        var conversation = await Repo.FindWorkThreadAsync(subjectType, subjectId, ct);

        if (conversation == null)
        {
            var title = !string.IsNullOrEmpty(subjectCode)
                ? $"{subjectType} #{subjectCode}"
                : $"{subjectType} #{subjectId}";

            conversation = new Conversation
            {
                Type = ConversationType.Work,
                SubjectType = subjectType,
                SubjectId = subjectId,
                SubjectCode = subjectCode,
                SubjectTitle = subjectTitle,
                Title = title,
                CreatedBy = Me.Id
            };
            conversation.Members.Add(new ConversationMember
            {
                UserId = Me.Id,
                UserName = Me.Name,
                Role = MemberRole.Owner
            });
            await Repo.TryInsertConversationAsync(conversation, ct);
        }
        else
        {
            var existingMember = conversation.Members.FirstOrDefault(m => m.UserId == Me.Id);
            if (existingMember == null)
            {
                existingMember = new ConversationMember
                {
                    UserId = Me.Id,
                    UserName = Me.Name,
                    Role = MemberRole.Member
                };
                await Repo.AddMembersAsync(conversation.Id, [existingMember], ct);
                conversation.Members.Add(existingMember);
            }
        }

        return await BuildDtoAsync(conversation, conversation.Members.First(m => m.UserId == Me.Id), ct);
    }

    public async Task<ConversationDto> CreateGroupAsync(string? title, IReadOnlyCollection<long> userIds, CancellationToken ct)
    {
        title = title?.Trim();
        if (string.IsNullOrEmpty(title) || title.Length > MaxTitleLength) throw ChatException.Invalid($"Tên nhóm bắt buộc và tối đa {MaxTitleLength} ký tự.");
        var users = await ValidateNewMembersAsync(userIds.Where(id => id != Me.Id).Distinct().ToList(), ct);
        if (users.Count < 1) throw ChatException.Invalid("Nhóm cần ít nhất một thành viên khác.");
        if (users.Count + 1 > MaxGroupMembers) throw ChatException.Invalid($"Nhóm tối đa {MaxGroupMembers} thành viên.");

        var conversation = new Conversation { Type = ConversationType.Group, Title = title, CreatedBy = Me.Id };
        conversation.Members.Add(new ConversationMember { UserId = Me.Id, UserName = Me.Name, Role = MemberRole.Owner });
        foreach (var user in users) conversation.Members.Add(new ConversationMember { UserId = user.Id, UserName = user.Name });
        await Repo.TryInsertConversationAsync(conversation, ct);
        await PostSystemMessageAsync(conversation, $"{Me.Name} đã tạo nhóm \"{title}\"", ct);

        // Báo ngay cho mọi thành viên để nhóm hiện trong danh sách của họ
        await NotifyConversationAsync(conversation, ct);
        return await BuildDtoAsync(conversation, conversation.Members.First(m => m.UserId == Me.Id), ct);
    }

    private async Task<List<DirectoryUser>> ValidateNewMembersAsync(IReadOnlyCollection<long> userIds, CancellationToken ct)
    {
        var users = await UsersForValidationAsync(ct);
        var result = new List<DirectoryUser>();
        foreach (var id in userIds)
        {
            if (!users.TryGetValue(id, out var user) || !user.IsActive)
                throw ChatException.Invalid($"Người dùng #{id} không tồn tại hoặc đã ngừng hoạt động.");
            result.Add(user);
        }
        return result;
    }

    // ---------- Quản lý nhóm ----------

    public async Task<ConversationDto> RenameAsync(long id, string? title, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        RequireRole(me, MemberRole.Deputy, "Chỉ trưởng/phó nhóm được đổi tên nhóm.");
        title = title?.Trim();
        if (string.IsNullOrEmpty(title) || title.Length > MaxTitleLength) throw ChatException.Invalid($"Tên nhóm bắt buộc và tối đa {MaxTitleLength} ký tự.");
        if (title == conversation.Title) return await BuildDtoAsync(conversation, me, ct);

        conversation.Title = title;
        await Repo.UpdateGroupInfoAsync(conversation, ct);
        await CommitSystemMessageAsync(conversation, $"{Me.Name} đã đổi tên nhóm thành \"{title}\"", ct);
        return await BuildDtoAsync(conversation, me, ct);
    }

    public async Task<ConversationDto> SetAvatarAsync(long id, UploadedFile file, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        RequireRole(me, MemberRole.Deputy, "Chỉ trưởng/phó nhóm được đổi ảnh nhóm.");
        var (extension, contentType) = FileRules.ValidateAvatar(file);

        await using var stream = file.OpenReadStream();
        var path = await storage.SaveAsync(stream, extension, ct);
        var oldPath = conversation.AvatarPath;
        conversation.AvatarPath = path;
        conversation.AvatarContentType = contentType;
        conversation.AvatarVersion++;
        await Repo.UpdateGroupInfoAsync(conversation, ct);
        await CommitSystemMessageAsync(conversation, $"{Me.Name} đã đổi ảnh nhóm", ct);
        if (oldPath != null) storage.Delete(oldPath);
        return await BuildDtoAsync(conversation, me, ct);
    }

    public async Task<ConversationDto> RemoveAvatarAsync(long id, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        RequireRole(me, MemberRole.Deputy, "Chỉ trưởng/phó nhóm được xoá ảnh nhóm.");
        if (conversation.AvatarPath is { } oldPath)
        {
            conversation.AvatarPath = null;
            conversation.AvatarContentType = null;
            conversation.AvatarVersion++;
            await Repo.UpdateGroupInfoAsync(conversation, ct);
            await CommitSystemMessageAsync(conversation, $"{Me.Name} đã xoá ảnh nhóm", ct);
            storage.Delete(oldPath);
        }
        return await BuildDtoAsync(conversation, me, ct);
    }

    public async Task<(Stream Stream, string ContentType)> OpenAvatarAsync(long id, CancellationToken ct)
    {
        var (conversation, _) = await RequireMemberAsync(id, ct);
        if (conversation.AvatarPath == null) throw ChatException.NotFound("Nhóm chưa có ảnh.");
        return (storage.OpenRead(conversation.AvatarPath), conversation.AvatarContentType ?? "image/jpeg");
    }

    public async Task<ConversationDto> AddMembersAsync(long id, IReadOnlyCollection<long> userIds, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        RequireRole(me, MemberRole.Deputy, "Chỉ trưởng/phó nhóm được thêm thành viên.");
        var existing = conversation.Members.Select(m => m.UserId).ToHashSet();
        var users = await ValidateNewMembersAsync(userIds.Distinct().Where(uid => !existing.Contains(uid)).ToList(), ct);
        if (users.Count == 0) return await BuildDtoAsync(conversation, me, ct);
        if (conversation.Members.Count + users.Count > MaxGroupMembers) throw ChatException.Invalid($"Nhóm tối đa {MaxGroupMembers} thành viên.");

        // Người mới thấy từ tin hiện tại trở đi là "đã đọc" để không bị dồn hàng trăm tin chưa đọc
        var newMembers = users.Select(user => new ConversationMember
        {
            UserId = user.Id, UserName = user.Name, LastReadMessageId = conversation.LastMessageId
        }).ToList();
        await Repo.AddMembersAsync(conversation.Id, newMembers, ct);
        conversation.Members.AddRange(newMembers);
        await CommitSystemMessageAsync(conversation, $"{Me.Name} đã thêm {string.Join(", ", users.Select(u => u.Name))} vào nhóm", ct);
        return await BuildDtoAsync(conversation, me, ct);
    }

    public async Task RemoveMemberAsync(long id, long userId, CancellationToken ct)
    {
        if (userId == Me.Id) throw ChatException.Invalid("Dùng chức năng rời nhóm để tự rời.");
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        var target = conversation.Members.FirstOrDefault(m => m.UserId == userId) ?? throw ChatException.NotFound("Người này không ở trong nhóm.");
        // Trưởng nhóm xoá được mọi người; phó nhóm chỉ xoá được thành viên thường
        var allowed = me.Role == MemberRole.Owner || (me.Role == MemberRole.Deputy && target.Role == MemberRole.Member);
        if (!allowed) throw ChatException.Forbidden("Bạn không có quyền xoá người này khỏi nhóm.");

        await Repo.RemoveMemberAsync(conversation.Id, target.UserId, ct);
        conversation.Members.Remove(target);
        await CommitSystemMessageAsync(conversation, $"{Me.Name} đã xoá {NameOf(target.UserId, target.UserName)} khỏi nhóm", ct);
        await NotifyRemovedAsync([userId], conversation.Id, "removed");
    }

    /// <summary>Rời nhóm. Trưởng nhóm phải chỉ định trưởng nhóm mới nếu nhóm còn người khác; rời khi chỉ còn một mình thì nhóm bị giải tán.</summary>
    public async Task LeaveAsync(long id, long? newOwnerId, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        if (conversation.Members.Count == 1)
        {
            await DeleteConversationAsync(conversation, "dissolved", ct);
            return;
        }

        var content = $"{Me.Name} đã rời nhóm";
        if (me.Role == MemberRole.Owner)
        {
            var newOwner = conversation.Members.FirstOrDefault(m => m.UserId == newOwnerId && m.UserId != Me.Id)
                ?? throw ChatException.Invalid("Trưởng nhóm cần chọn trưởng nhóm mới trước khi rời nhóm.");
            await Repo.SetMemberRoleAsync(conversation.Id, newOwner.UserId, MemberRole.Owner, ct);
            newOwner.Role = MemberRole.Owner;
            content = $"{Me.Name} đã rời nhóm và chuyển quyền trưởng nhóm cho {NameOf(newOwner.UserId, newOwner.UserName)}";
        }

        await Repo.RemoveMemberAsync(conversation.Id, me.UserId, ct);
        conversation.Members.Remove(me);
        await CommitSystemMessageAsync(conversation, content, ct);
        await NotifyRemovedAsync([Me.Id], conversation.Id, "left");
    }

    public async Task<ConversationDto> SetRoleAsync(long id, long userId, string? role, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        RequireRole(me, MemberRole.Owner, "Chỉ trưởng nhóm được bổ nhiệm phó nhóm.");
        var newRole = role switch
        {
            "deputy" => MemberRole.Deputy,
            "member" => MemberRole.Member,
            _ => throw ChatException.Invalid("Vai trò chỉ có thể là deputy hoặc member. Dùng chuyển quyền để đổi trưởng nhóm.")
        };
        var target = conversation.Members.FirstOrDefault(m => m.UserId == userId && m.UserId != Me.Id)
            ?? throw ChatException.NotFound("Người này không ở trong nhóm.");
        if (target.Role == newRole) return await BuildDtoAsync(conversation, me, ct);

        await Repo.SetMemberRoleAsync(conversation.Id, target.UserId, newRole, ct);
        target.Role = newRole;
        var name = NameOf(target.UserId, target.UserName);
        await CommitSystemMessageAsync(conversation,
            newRole == MemberRole.Deputy ? $"{Me.Name} đã bổ nhiệm {name} làm phó nhóm" : $"{Me.Name} đã gỡ quyền phó nhóm của {name}", ct);
        return await BuildDtoAsync(conversation, me, ct);
    }

    public async Task<ConversationDto> TransferOwnerAsync(long id, long userId, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        RequireRole(me, MemberRole.Owner, "Chỉ trưởng nhóm được chuyển quyền.");
        var target = conversation.Members.FirstOrDefault(m => m.UserId == userId && m.UserId != Me.Id)
            ?? throw ChatException.NotFound("Người này không ở trong nhóm.");

        await Repo.SetMemberRoleAsync(conversation.Id, target.UserId, MemberRole.Owner, ct);
        await Repo.SetMemberRoleAsync(conversation.Id, me.UserId, MemberRole.Member, ct);
        target.Role = MemberRole.Owner;
        me.Role = MemberRole.Member;
        await CommitSystemMessageAsync(conversation, $"{Me.Name} đã chuyển quyền trưởng nhóm cho {NameOf(target.UserId, target.UserName)}", ct);
        return await BuildDtoAsync(conversation, me, ct);
    }

    public async Task DissolveAsync(long id, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        RequireGroup(conversation);
        RequireRole(me, MemberRole.Owner, "Chỉ trưởng nhóm được giải tán nhóm.");
        await DeleteConversationAsync(conversation, "dissolved", ct);
    }

    /// <summary>Xoá hẳn hội thoại, tin nhắn và tệp; báo cho mọi thành viên gỡ khỏi danh sách.</summary>
    private async Task DeleteConversationAsync(Conversation conversation, string reason, CancellationToken ct)
    {
        var memberIds = conversation.Members.Select(m => m.UserId).ToList();
        var files = await Repo.GetAttachmentPathsAsync(conversation.Id, ct);
        if (conversation.AvatarPath != null) files.Add(conversation.AvatarPath);

        await Repo.DeleteConversationAsync(conversation.Id, ct);

        foreach (var file in files) storage.Delete(file);
        await NotifyRemovedAsync(memberIds, conversation.Id, reason);
    }

    /// <summary>Thêm một tin hệ thống mô tả thay đổi vừa lưu, rồi báo tin mới và hội thoại đã cập nhật cho thành viên.</summary>
    private async Task CommitSystemMessageAsync(Conversation conversation, string content, CancellationToken ct)
    {
        var message = await PostSystemMessageAsync(conversation, content, ct);
        await UsersForDisplayAsync(ct);
        await NotifyMessageAsync(conversation, message, Events.MessageCreated, ct);
        await NotifyConversationAsync(conversation, ct);
    }

    // ---------- Tuỳ chọn riêng từng người ----------

    public async Task<ConversationDto> SetPinnedAsync(long id, bool pinned, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        me.PinnedAt = pinned ? me.PinnedAt ?? DateTime.UtcNow : null;
        await Repo.SetMemberPinnedAsync(id, Me.Id, me.PinnedAt, ct);
        return await NotifySelfAsync(conversation, me, ct);
    }

    public async Task<ConversationDto> SetMutedAsync(long id, bool muted, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        me.Muted = muted;
        await Repo.SetMemberMutedAsync(id, Me.Id, muted, ct);
        return await NotifySelfAsync(conversation, me, ct);
    }

    /// <summary>Đồng bộ thay đổi riêng (ghim, tắt thông báo) sang các tab/thiết bị khác của chính mình.</summary>
    private async Task<ConversationDto> NotifySelfAsync(Conversation conversation, ConversationMember me, CancellationToken ct)
    {
        var dto = await BuildDtoAsync(conversation, me, ct);
        await Notifier.ToUsersAsync([Me.Id], Events.ConversationUpserted, new { conversation = dto });
        return dto;
    }

    /// <summary>Đánh dấu đã đọc tới một tin; con trỏ chỉ tiến. Trả về tổng số chưa đọc mới.</summary>
    public async Task<int> MarkReadAsync(long id, long messageId, CancellationToken ct)
    {
        var (conversation, me) = await RequireMemberAsync(id, ct);
        var exists = await Repo.MessageExistsAsync(id, messageId, ct);
        if (!exists) throw ChatException.Invalid("Tin nhắn không thuộc hội thoại.");

        var now = DateTime.UtcNow;
        if (await Repo.AdvanceReadAsync(id, Me.Id, messageId, now, ct))
        {
            me.LastReadMessageId = messageId;
            me.LastReadAt = now;
            await Notifier.ToUsersAsync(conversation.Members.Select(m => m.UserId), Events.ReadUpdated,
                new { conversation_id = id, user_id = Me.Id, message_id = messageId });
        }
        return await UnreadTotalAsync(ct);
    }

    public async Task<int> MarkAllReadAsync(CancellationToken ct)
    {
        await Repo.MarkAllReadAsync(Me.Id, DateTime.UtcNow, ct);
        return 0;
    }
}
