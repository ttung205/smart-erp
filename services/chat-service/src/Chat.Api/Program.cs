using System.Text.Json;
using System.Text.Json.Serialization;
using Chat.Api.Hubs;
using Chat.Api.Middlewares;
using Chat.Api.Services;
using Chat.Application;
using Chat.Application.Common.Interfaces;
using Chat.Infrastructure;
using Chat.Infrastructure.Persistence;
using Chat.Infrastructure.Realtime;

var builder = WebApplication.CreateBuilder(args);

// 1. Cấu hình JSON Naming Policy: snake_case theo chuẩn toàn hệ thống
builder.Services.AddControllers()
    .AddJsonOptions(options =>
    {
        options.JsonSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower;
        options.JsonSerializerOptions.DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull;
    });

// 2. Đăng ký HttpContextAccessor, Tracker kết nối và CurrentUserService
builder.Services.AddHttpContextAccessor();
builder.Services.AddSingleton<HubConnectionTracker>();
builder.Services.AddScoped<ICurrentUserService, CurrentUserService>();
builder.Services.AddScoped<IChatHubService, ChatHubService>();

// 3. Đăng ký Application & Infrastructure Services
builder.Services.AddApplication();
builder.Services.AddInfrastructure(builder.Configuration);

// 4. Đăng ký SignalR Realtime Engine
builder.Services.AddSignalR(options =>
{
    options.EnableDetailedErrors = builder.Environment.IsDevelopment();
    options.KeepAliveInterval = TimeSpan.FromSeconds(15);
    options.ClientTimeoutInterval = TimeSpan.FromSeconds(30);
});

// 5. Cấu hình CORS cho API Gateway (:8000), Vite Frontend (:5173 / :5174 / :5175) kèm WebSocket Credentials
builder.Services.AddCors(options =>
{
    options.AddPolicy("ChatCorsPolicy", policy =>
    {
        policy.SetIsOriginAllowed(_ => true)
              .AllowAnyMethod()
              .AllowAnyHeader()
              .AllowCredentials();
    });
});

// 6. Cấu hình Swagger OpenAPI qua Swashbuckle kèm bảo mật Bearer Token
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen(c =>
{
    c.SwaggerDoc("v1", new Microsoft.OpenApi.Models.OpenApiInfo
    {
        Title = "Smart ERP - Chat Realtime Service API",
        Version = "v1",
        Description = "Microservice Chat Realtime Doanh Nghiệp & Tích Hợp RabbitMQ Bot (.NET 10 Web API + SignalR)"
    });

    c.AddSecurityDefinition("Bearer", new Microsoft.OpenApi.Models.OpenApiSecurityScheme
    {
        Description = "Nhập JWT token theo định dạng: Bearer {token}",
        Name = "Authorization",
        In = Microsoft.OpenApi.Models.ParameterLocation.Header,
        Type = Microsoft.OpenApi.Models.SecuritySchemeType.ApiKey,
        Scheme = "Bearer"
    });

    c.AddSecurityRequirement(new Microsoft.OpenApi.Models.OpenApiSecurityRequirement
    {
        {
            new Microsoft.OpenApi.Models.OpenApiSecurityScheme
            {
                Reference = new Microsoft.OpenApi.Models.OpenApiReference
                {
                    Type = Microsoft.OpenApi.Models.ReferenceType.SecurityScheme,
                    Id = "Bearer"
                }
            },
            Array.Empty<string>()
        }
    });
});

var app = builder.Build();

app.UseCors("ChatCorsPolicy");

// 7. Đăng ký Middlewares: Correlation ID, Global Exception Handling và JWT Authentication
app.UseMiddleware<CorrelationIdMiddleware>();
app.UseMiddleware<GlobalExceptionHandlingMiddleware>();
app.UseMiddleware<JwtAuthMiddleware>();

// 8. Khởi tạo Database SQLite và Seed 6 kênh phòng ban mặc định
using (var scope = app.Services.CreateScope())
{
    var context = scope.ServiceProvider.GetRequiredService<ChatDbContext>();
    await DbInitializer.SeedAsync(context);
}

// 9. Cấu hình Swagger UI trong môi trường Development
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI(c =>
    {
        c.SwaggerEndpoint("/swagger/v1/swagger.json", "Chat Service API v1");
        c.RoutePrefix = "swagger";
    });
}

app.UseAuthorization();
app.MapControllers();

// 10. Map SignalR Hub endpoints cho WebSocket Realtime
app.MapHub<ChatHub>("/api/v1/chat/hub");
app.MapHub<ChatHub>("/hubs/chat");

app.Run();
