using System.Security.Claims;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading.RateLimiting;
using Chat.Api.Middlewares;
using Chat.Api.Realtime;
using Chat.Application;
using Chat.Application.Common.Interfaces;
using Chat.Application.Common.Models;
using Chat.Infrastructure;
using Chat.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;

var builder = WebApplication.CreateBuilder(args);

// 1. JSON snake_case theo chuẩn toàn hệ thống (REST và SignalR dùng chung)
builder.Services.AddControllers()
    .AddJsonOptions(options =>
    {
        options.JsonSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower;
        options.JsonSerializerOptions.DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull;
    });

builder.Services.AddSignalR(options =>
    {
        options.EnableDetailedErrors = builder.Environment.IsDevelopment();
        options.KeepAliveInterval = TimeSpan.FromSeconds(15);
        options.ClientTimeoutInterval = TimeSpan.FromSeconds(30);
    })
    .AddJsonProtocol(options => options.PayloadSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower);

// 2. Application, Infrastructure và các adapter của tầng Api
builder.Services.AddHttpContextAccessor();
builder.Services.AddApplication();
builder.Services.AddInfrastructure(builder.Configuration);
builder.Services.AddScoped<HttpCurrentUser>();
builder.Services.AddScoped<ICurrentUser>(provider => provider.GetRequiredService<HttpCurrentUser>());
builder.Services.AddSingleton<IChatNotifier, SignalRChatNotifier>();

// 3. Giới hạn tần suất gửi tin theo từng người dùng (chỉ áp cho gửi/chuyển tiếp, không gộp với các API đọc)
builder.Services.AddRateLimiter(options =>
{
    options.RejectionStatusCode = StatusCodes.Status429TooManyRequests;
    options.AddPolicy("send", context => RateLimitPartition.GetFixedWindowLimiter(
        context.User.FindFirstValue(ClaimTypes.NameIdentifier) ?? context.Connection.RemoteIpAddress?.ToString() ?? "anonymous",
        _ => new FixedWindowRateLimiterOptions { PermitLimit = 30, Window = TimeSpan.FromSeconds(10) }));
    options.OnRejected = async (context, ct) =>
    {
        context.HttpContext.Response.ContentType = "application/json";
        var envelope = ApiEnvelope<object>.Fail("RATE_LIMITED", "Bạn gửi tin quá nhanh, vui lòng chờ một chút.");
        await context.HttpContext.Response.WriteAsync(JsonSerializer.Serialize(envelope), ct);
    };
});

// 4. CORS: chỉ các origin frontend được khai báo (WebSocket cần AllowCredentials)
var allowedOrigins = builder.Configuration.GetSection("Cors:AllowedOrigins").Get<string[]>()
    ?? ["http://localhost:5173", "http://localhost:5174", "http://localhost:5175", "http://localhost:8000"];
builder.Services.AddCors(options => options.AddPolicy("ChatCorsPolicy", policy =>
    policy.WithOrigins(allowedOrigins).AllowAnyMethod().AllowAnyHeader().AllowCredentials()));

builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen(c =>
    c.SwaggerDoc("v1", new Microsoft.OpenApi.Models.OpenApiInfo { Title = "Smart ERP - Chat Service API", Version = "v1" }));

var app = builder.Build();

// 5. Tạo/cập nhật CSDL theo migration
using (var scope = app.Services.CreateScope())
{
    await scope.ServiceProvider.GetRequiredService<ChatDbContext>().Database.MigrateAsync();
}

app.UseCors("ChatCorsPolicy");
app.UseMiddleware<CorrelationIdMiddleware>();
app.UseMiddleware<GlobalExceptionHandlingMiddleware>();
app.UseMiddleware<JwtAuthMiddleware>();
app.UseRateLimiter();

if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI(c => c.SwaggerEndpoint("/swagger/v1/swagger.json", "Chat Service API v1"));
}

app.MapControllers();
app.MapHub<ChatHub>(JwtAuthMiddleware.HubPath);

app.Run();

public partial class Program;
