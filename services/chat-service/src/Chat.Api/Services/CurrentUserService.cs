using System.Security.Claims;
using Chat.Application.Common.Interfaces;

namespace Chat.Api.Services;

public class CurrentUserService : ICurrentUserService
{
    private readonly IHttpContextAccessor _httpContextAccessor;

    public CurrentUserService(IHttpContextAccessor httpContextAccessor)
    {
        _httpContextAccessor = httpContextAccessor;
    }

    public int? UserId
    {
        get
        {
            var user = _httpContextAccessor.HttpContext?.User;
            var sub = user?.FindFirst(ClaimTypes.NameIdentifier)?.Value 
                      ?? _httpContextAccessor.HttpContext?.Items["UserId"]?.ToString();

            return int.TryParse(sub, out var id) ? id : null;
        }
    }

    public string? UserName
    {
        get
        {
            return _httpContextAccessor.HttpContext?.User?.FindFirst(ClaimTypes.Name)?.Value
                   ?? _httpContextAccessor.HttpContext?.Items["UserName"]?.ToString();
        }
    }

    public string? Email
    {
        get
        {
            return _httpContextAccessor.HttpContext?.User?.FindFirst(ClaimTypes.Email)?.Value
                   ?? _httpContextAccessor.HttpContext?.Items["Email"]?.ToString();
        }
    }

    public string? DepartmentCode
    {
        get
        {
            return _httpContextAccessor.HttpContext?.User?.FindFirst("dept")?.Value
                   ?? _httpContextAccessor.HttpContext?.Items["DepartmentCode"]?.ToString();
        }
    }

    public string? Role
    {
        get
        {
            return _httpContextAccessor.HttpContext?.User?.FindFirst(ClaimTypes.Role)?.Value;
        }
    }

    public bool IsAuthenticated => _httpContextAccessor.HttpContext?.User?.Identity?.IsAuthenticated ?? false;
}
