using Polly;
using Microsoft.Extensions.Http.Resilience;
using Purchase.Api.Controllers;
using System.Text.Json;
using System.Text.Json.Serialization;
using Purchase.Api.Middlewares;
using Purchase.Application;
using Purchase.Infrastructure;
using Purchase.Infrastructure.Persistence;

var builder = WebApplication.CreateBuilder(args);

// 1. Cấu hình JSON Naming Policy snake_case theo chuẩn hệ thống
builder.Services.AddControllers()
    .AddJsonOptions(options =>
    {
        options.JsonSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower;
        options.JsonSerializerOptions.DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull;
    });

// 2. Đăng ký Application Services & Infrastructure (EF Core hỗ trợ Dual Provider)
builder.Services.AddApplication();
builder.Services.AddInfrastructure(builder.Configuration);
// Gọi catalog / identity / notification-service: timeout, retry (chỉ GET), circuit breaker riêng cho từng service đích
builder.Services.AddHttpClient(ItemInquiriesController.InternalClient)
    .AddStandardResilienceHandler(options =>
    {
        options.AttemptTimeout.Timeout = TimeSpan.FromSeconds(10);
        options.TotalRequestTimeout.Timeout = TimeSpan.FromSeconds(30);
        options.Retry.MaxRetryAttempts = 2;
        // Chỉ retry GET: POST (gửi email, ghi kết quả hỏi hàng) không retry để không gửi trùng
        var shouldRetry = options.Retry.ShouldHandle;
        options.Retry.ShouldHandle = args => args.Context.GetRequestMessage()?.Method == HttpMethod.Get
            ? shouldRetry(args)
            : ValueTask.FromResult(false);
        options.CircuitBreaker.SamplingDuration = TimeSpan.FromSeconds(30);
        options.CircuitBreaker.MinimumThroughput = 5;
        options.CircuitBreaker.BreakDuration = TimeSpan.FromSeconds(30);
    })
    .SelectPipelineByAuthority();

// 3. Cấu hình CORS cho API Gateway (:8000) và Frontend (:5173 / :5174)
builder.Services.AddCors(options =>
{
    options.AddPolicy("AllowAll", policy =>
    {
        policy.AllowAnyOrigin()
              .AllowAnyMethod()
              .AllowAnyHeader();
    });
});

// 4. Cấu hình Swagger OpenAPI qua Swashbuckle kèm bảo mật Bearer Token
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen(c =>
{
    c.SwaggerDoc("v1", new Microsoft.OpenApi.Models.OpenApiInfo
    {
        Title = "Smart ERP - Purchase Service API",
        Version = "v1",
        Description = "Microservice Quản Lý Mua Hàng, Đề Nghị PR, Đơn PO, Nhập Kho GRN & Đối Soát 3 Chiều (.NET 10 Web API)"
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

app.UseCors("AllowAll");

// 5. Đăng ký Middlewares: Correlation ID, Global Exception Handling và JWT Authentication
app.UseMiddleware<CorrelationIdMiddleware>();
app.UseMiddleware<GlobalExceptionHandlingMiddleware>();
app.UseMiddleware<JwtAuthMiddleware>();

// 6. Khởi tạo schema; chỉ nạp dữ liệu mẫu khi được bật rõ ràng.
using (var scope = app.Services.CreateScope())
{
    var context = scope.ServiceProvider.GetRequiredService<PurchaseDbContext>();
    var autoSeed = builder.Configuration.GetValue<bool>("AUTO_SEED");
    await DbInitializer.SeedAsync(context, autoSeed);
}

// 7. Cấu hình Swagger UI trong môi trường Development
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI(c =>
    {
        c.SwaggerEndpoint("/swagger/v1/swagger.json", "Purchase Service API v1");
        c.RoutePrefix = "swagger";
    });
}

app.UseAuthorization();
app.MapControllers();

app.Run();
