using Chat.Application.Common.Models;

namespace Chat.Application.Common.Interfaces;

/// <summary>Xác thực access token do identity-service cấp; tầng Api chỉ phụ thuộc vào cổng này.</summary>
public interface IAccessTokenValidator
{
    AccessTokenResult Validate(string token);
}
