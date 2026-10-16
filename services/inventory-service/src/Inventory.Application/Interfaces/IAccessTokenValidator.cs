using Inventory.Application.Common.Models;

namespace Inventory.Application.Interfaces;

/// <summary>Xác thực access token do identity-service cấp; tầng Api chỉ phụ thuộc vào cổng này.</summary>
public interface IAccessTokenValidator
{
    AccessTokenResult Validate(string token);
}
