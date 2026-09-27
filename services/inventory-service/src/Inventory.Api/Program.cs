using System.Text.Json;
using System.Text.Json.Serialization;
using Inventory.Api.Middlewares;
using Inventory.Infrastructure;
using Inventory.Infrastructure.Persistence;

var builder = WebApplication.CreateBuilder(args);

// 1. Configure JSON Naming Policy (snake_case as required by architecture rules)
builder.Services.AddControllers()
    .AddJsonOptions(options =>
    {
        options.JsonSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower;
        options.JsonSerializerOptions.DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull;
    });

// 2. Add Infrastructure (EF Core with SQLite/MySQL)
builder.Services.AddInfrastructure(builder.Configuration);

// 3. Configure CORS for Gateway (:8000) and Frontend (:5173)
builder.Services.AddCors(options =>
{
    options.AddPolicy("AllowAll", policy =>
    {
        policy.AllowAnyOrigin()
              .AllowAnyMethod()
              .AllowAnyHeader();
    });
});

builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen(c =>
{
    c.SwaggerDoc("v1", new Microsoft.OpenApi.Models.OpenApiInfo
    {
        Title = "Smart ERP - Inventory Service API",
        Version = "v1",
        Description = "High-Performance Stock Engine & Concurrency Control (.NET 8 Web API)"
    });
});

var app = builder.Build();

app.UseCors("AllowAll");

// Register Global Exception Handling and Correlation ID Middlewares
app.UseMiddleware<CorrelationIdMiddleware>();
app.UseMiddleware<GlobalExceptionHandlingMiddleware>();

// Initialize and Seed Database
using (var scope = app.Services.CreateScope())
{
    var context = scope.ServiceProvider.GetRequiredService<InventoryDbContext>();
    await DbInitializer.SeedAsync(context);
}

// Configure the HTTP request pipeline
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI(c =>
    {
        c.SwaggerEndpoint("/swagger/v1/swagger.json", "Inventory Service v1");
    });
}

app.MapControllers();

app.Run();

// Cho phép WebApplicationFactory<Program> tham chiếu từ Test Projects
public partial class Program { }
