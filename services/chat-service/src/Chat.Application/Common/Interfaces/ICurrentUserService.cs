namespace Chat.Application.Common.Interfaces;

public interface ICurrentUserService
{
    int? UserId { get; }
    string? UserName { get; }
    string? Email { get; }
    string? DepartmentCode { get; }
    string? Role { get; }
    bool IsAuthenticated { get; }
}
