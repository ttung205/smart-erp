using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Application.Dtos;
using Chat.Domain.Entities;
using Chat.Domain.Enums;
using Microsoft.EntityFrameworkCore;

namespace Chat.Application.Services;

public class UserService(
    IChatDbContext db,
    ICurrentUser me,
    IUserDirectory directory,
    IPresenceTracker presence)
{
    public const int MaxAliasLength = 60;

    /// <summary>Tìm nhân viên đang hoạt động theo tên/email (trừ chính mình).</summary>
    public async Task<IReadOnlyList<ChatUserDto>> SearchAsync(string? q, int limit, CancellationToken ct)
    {
        var users = await directory.GetUsersAsync(ct);
        var keyword = q?.Trim() ?? string.Empty;
        return users.Values
            .Where(u => u.IsActive && u.Id != me.Id)
            .Where(u => keyword.Length == 0
                        || u.Name.Contains(keyword, StringComparison.CurrentCultureIgnoreCase)
                        || (u.Email?.Contains(keyword, StringComparison.OrdinalIgnoreCase) ?? false))
            .OrderBy(u => u.Name)
            .Take(Math.Clamp(limit, 1, 50))
            .Select(ToDto)
            .ToList();
    }

    /// <summary>Danh bạ: đồng nghiệp cùng phòng ban và những người đã chat 1-1 với mình.</summary>
    public async Task<IReadOnlyList<ChatUserDto>> ContactsAsync(CancellationToken ct)
    {
        var users = await directory.GetUsersAsync(ct);
        var myDepartment = users.TryGetValue(me.Id, out var self) ? self.DepartmentId : null;
        var directPartners = await db.ConversationMembers
            .Where(m => m.UserId != me.Id
                        && m.Conversation!.Type == ConversationType.Direct
                        && m.Conversation.Members.Any(x => x.UserId == me.Id))
            .Select(m => m.UserId)
            .ToListAsync(ct);
        var partners = directPartners.ToHashSet();

        return users.Values
            .Where(u => u.IsActive && u.Id != me.Id && ((myDepartment != null && u.DepartmentId == myDepartment) || partners.Contains(u.Id)))
            .OrderBy(u => u.Name)
            .Select(ToDto)
            .ToList();
    }

    public async Task<ChatUserDto> GetAsync(long userId, CancellationToken ct)
    {
        var users = await directory.GetUsersAsync(ct);
        return users.TryGetValue(userId, out var user) ? ToDto(user) : throw ChatException.NotFound("Không tìm thấy người dùng.");
    }

    public IReadOnlyCollection<long> OnlineUserIds() => presence.OnlineUserIds();

    public async Task<PreferencesDto> PreferencesAsync(CancellationToken ct)
    {
        var aliases = await db.ContactAliases.Where(a => a.OwnerId == me.Id).ToDictionaryAsync(a => a.TargetId, a => a.Alias, ct);
        return new PreferencesDto(aliases);
    }

    /// <summary>Đặt tên gợi nhớ; chuỗi rỗng là xoá.</summary>
    public async Task<string?> SetAliasAsync(long targetId, string? alias, CancellationToken ct)
    {
        if (targetId == me.Id) throw ChatException.Invalid("Không thể đặt tên gợi nhớ cho chính mình.");
        alias = alias?.Trim();
        if (alias?.Length > MaxAliasLength) throw ChatException.Invalid($"Tên gợi nhớ tối đa {MaxAliasLength} ký tự.");

        var existing = await db.ContactAliases.FirstOrDefaultAsync(a => a.OwnerId == me.Id && a.TargetId == targetId, ct);
        if (string.IsNullOrEmpty(alias))
        {
            if (existing != null) db.ContactAliases.Remove(existing);
            alias = null;
        }
        else if (existing == null) db.ContactAliases.Add(new ContactAlias { OwnerId = me.Id, TargetId = targetId, Alias = alias });
        else existing.Alias = alias;
        await db.SaveChangesAsync(ct);
        return alias;
    }

    private ChatUserDto ToDto(DirectoryUser u) => new(u.Id, u.Name, u.Email, u.DepartmentName, presence.IsOnline(u.Id));
}
