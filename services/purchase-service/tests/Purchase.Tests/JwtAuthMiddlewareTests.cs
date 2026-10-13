using System.Net;
using System.Security.Claims;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging.Abstractions;
using Purchase.Api.Middlewares;
using Purchase.Application.Common.Models;
using Xunit;

namespace Purchase.Tests;

public class JwtAuthMiddlewareTests
{
    private const string TestSecret = "smart_erp_super_secure_jwt_secret_key_2026";

    private static IConfiguration CreateConfiguration(string? secret = TestSecret, bool enabled = true)
    {
        var inMemorySettings = new Dictionary<string, string?>
        {
            { "Jwt:Secret", secret },
            { "Jwt:Enabled", enabled.ToString() }
        };

        return new ConfigurationBuilder()
            .AddInMemoryCollection(inMemorySettings)
            .Build();
    }

    private static string GenerateJwt(
        string sub = "1",
        string name = "Admin User",
        string email = "admin@smarterp.local",
        string[]? roles = null,
        string[]? perms = null,
        long? exp = null,
        string secret = TestSecret)
    {
        roles ??= new[] { "ADMIN", "PURCHASE_MANAGER" };
        perms ??= new[] { "po:create", "po:approve" };
        exp ??= DateTimeOffset.UtcNow.AddHours(1).ToUnixTimeSeconds();

        var headerJson = JsonSerializer.Serialize(new { typ = "JWT", alg = "HS256" });
        var payloadJson = JsonSerializer.Serialize(new
        {
            iss = "identity-service",
            sub = sub,
            name = name,
            email = email,
            roles = roles,
            permissions = perms,
            iat = DateTimeOffset.UtcNow.ToUnixTimeSeconds(),
            exp = exp.Value
        });

        var headerB64 = Base64Url(Encoding.UTF8.GetBytes(headerJson));
        var payloadB64 = Base64Url(Encoding.UTF8.GetBytes(payloadJson));

        using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret));
        var sigB64 = Base64Url(hmac.ComputeHash(Encoding.UTF8.GetBytes($"{headerB64}.{payloadB64}")));

        return $"{headerB64}.{payloadB64}.{sigB64}";
    }

    private static string Base64Url(byte[] data)
    {
        return Convert.ToBase64String(data)
            .TrimEnd('=')
            .Replace('+', '-')
            .Replace('/', '_');
    }

    [Fact]
    public async Task InvokeAsync_PublicPath_ShouldPassWithoutToken()
    {
        var isNextCalled = false;
        RequestDelegate next = (ctx) =>
        {
            isNextCalled = true;
            return Task.CompletedTask;
        };

        var middleware = new JwtAuthMiddleware(next, NullLogger<JwtAuthMiddleware>.Instance, CreateConfiguration());
        var context = new DefaultHttpContext();
        context.Request.Path = "/api/v1/purchase/health";

        await middleware.InvokeAsync(context);

        Assert.True(isNextCalled);
    }

    [Fact]
    public async Task InvokeAsync_MissingAuthorizationHeader_ShouldReturn401Unauthorized()
    {
        var isNextCalled = false;
        RequestDelegate next = (ctx) =>
        {
            isNextCalled = true;
            return Task.CompletedTask;
        };

        var middleware = new JwtAuthMiddleware(next, NullLogger<JwtAuthMiddleware>.Instance, CreateConfiguration());
        var context = new DefaultHttpContext();
        context.Request.Path = "/api/v1/purchase/orders";
        context.Response.Body = new MemoryStream();

        await middleware.InvokeAsync(context);

        Assert.False(isNextCalled);
        Assert.Equal((int)HttpStatusCode.Unauthorized, context.Response.StatusCode);

        context.Response.Body.Seek(0, SeekOrigin.Begin);
        var responseBody = await new StreamReader(context.Response.Body).ReadToEndAsync();
        var envelope = JsonSerializer.Deserialize<ApiEnvelope<object>>(responseBody, new JsonSerializerOptions { PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower });
        Assert.NotNull(envelope);
        Assert.False(envelope.Success);
        Assert.Equal("UNAUTHORIZED", envelope.Error?.Code);
    }

    [Fact]
    public async Task InvokeAsync_InvalidSignature_ShouldReturn401InvalidToken()
    {
        var isNextCalled = false;
        RequestDelegate next = (ctx) =>
        {
            isNextCalled = true;
            return Task.CompletedTask;
        };

        var middleware = new JwtAuthMiddleware(next, NullLogger<JwtAuthMiddleware>.Instance, CreateConfiguration());
        var context = new DefaultHttpContext();
        context.Request.Path = "/api/v1/purchase/orders";
        context.Response.Body = new MemoryStream();

        // Tạo token với secret khác
        var tokenWithWrongSecret = GenerateJwt(secret: "wrong_secret_key_1234567890");
        context.Request.Headers["Authorization"] = $"Bearer {tokenWithWrongSecret}";

        await middleware.InvokeAsync(context);

        Assert.False(isNextCalled);
        Assert.Equal((int)HttpStatusCode.Unauthorized, context.Response.StatusCode);

        context.Response.Body.Seek(0, SeekOrigin.Begin);
        var responseBody = await new StreamReader(context.Response.Body).ReadToEndAsync();
        var envelope = JsonSerializer.Deserialize<ApiEnvelope<object>>(responseBody, new JsonSerializerOptions { PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower });
        Assert.Equal("INVALID_TOKEN", envelope?.Error?.Code);
    }

    [Fact]
    public async Task InvokeAsync_ExpiredToken_ShouldReturn401TokenExpired()
    {
        var isNextCalled = false;
        RequestDelegate next = (ctx) =>
        {
            isNextCalled = true;
            return Task.CompletedTask;
        };

        var middleware = new JwtAuthMiddleware(next, NullLogger<JwtAuthMiddleware>.Instance, CreateConfiguration());
        var context = new DefaultHttpContext();
        context.Request.Path = "/api/v1/purchase/orders";
        context.Response.Body = new MemoryStream();

        // Expired 10 phút trước
        var expiredToken = GenerateJwt(exp: DateTimeOffset.UtcNow.AddMinutes(-10).ToUnixTimeSeconds());
        context.Request.Headers["Authorization"] = $"Bearer {expiredToken}";

        await middleware.InvokeAsync(context);

        Assert.False(isNextCalled);
        Assert.Equal((int)HttpStatusCode.Unauthorized, context.Response.StatusCode);

        context.Response.Body.Seek(0, SeekOrigin.Begin);
        var responseBody = await new StreamReader(context.Response.Body).ReadToEndAsync();
        var envelope = JsonSerializer.Deserialize<ApiEnvelope<object>>(responseBody, new JsonSerializerOptions { PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower });
        Assert.Equal("TOKEN_EXPIRED", envelope?.Error?.Code);
    }

    [Fact]
    public async Task InvokeAsync_ValidToken_ShouldPassAndPopulateUserClaims()
    {
        var isNextCalled = false;
        RequestDelegate next = (ctx) =>
        {
            isNextCalled = true;
            return Task.CompletedTask;
        };

        var middleware = new JwtAuthMiddleware(next, NullLogger<JwtAuthMiddleware>.Instance, CreateConfiguration());
        var context = new DefaultHttpContext();
        context.Request.Path = "/api/v1/purchase/orders";

        var validToken = GenerateJwt(sub: "99", name: "Trưởng phòng Mua hàng");
        context.Request.Headers["Authorization"] = $"Bearer {validToken}";

        await middleware.InvokeAsync(context);

        Assert.True(isNextCalled);
        Assert.Equal("99", context.User.FindFirst(ClaimTypes.NameIdentifier)?.Value);
        Assert.Equal("Trưởng phòng Mua hàng", context.User.FindFirst(ClaimTypes.Name)?.Value);
        Assert.Equal("99", context.Items["UserId"]);
        Assert.True(context.User.IsInRole("PURCHASE_MANAGER"));
    }
}
