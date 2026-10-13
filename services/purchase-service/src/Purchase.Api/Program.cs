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

// 6. Khởi tạo Database và Seed dữ liệu mẫu ban đầu
using (var scope = app.Services.CreateScope())
{
    var context = scope.ServiceProvider.GetRequiredService<PurchaseDbContext>();
    await DbInitializer.SeedAsync(context);
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
