using System.Net;
using System.Text.Json;
using Inventory.Application.Common.Models;
using Inventory.Domain.Exceptions;

namespace Inventory.Api.Middlewares;

public class GlobalExceptionHandlingMiddleware
{
    private readonly RequestDelegate _next;
    private readonly ILogger<GlobalExceptionHandlingMiddleware> _logger;

    public GlobalExceptionHandlingMiddleware(RequestDelegate next, ILogger<GlobalExceptionHandlingMiddleware> logger)
    {
        _next = next;
        _logger = logger;
    }

    public async Task InvokeAsync(HttpContext context)
    {
        try
        {
            await _next(context);
        }
        catch (Exception ex)
        {
            await HandleExceptionAsync(context, ex);
        }
    }

    private async Task HandleExceptionAsync(HttpContext context, Exception exception)
    {
        var correlationId = context.Items["CorrelationId"]?.ToString() 
            ?? context.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";

        HttpStatusCode statusCode;
        string errorCode;
        string message = exception.Message;
        object? details = null;

        switch (exception)
        {
            case InsufficientStockException stockEx:
                statusCode = HttpStatusCode.Conflict; // 409
                errorCode = stockEx.ErrorCode; // INSUFFICIENT_STOCK
                details = new
                {
                    product_id = stockEx.ProductId,
                    warehouse_id = stockEx.WarehouseId,
                    requested = stockEx.RequestedQuantity,
                    available = stockEx.AvailableQuantity
                };
                _logger.LogWarning(exception, "[{CorrelationId}] Insufficient stock: Product {ProductId}, Warehouse {WarehouseId}", 
                    correlationId, stockEx.ProductId, stockEx.WarehouseId);
                break;

            case NegativeStockException negativeEx:
                statusCode = HttpStatusCode.UnprocessableEntity; // 422
                errorCode = negativeEx.ErrorCode; // NEGATIVE_STOCK
                details = new
                {
                    product_id = negativeEx.ProductId,
                    warehouse_id = negativeEx.WarehouseId,
                    field_name = negativeEx.FieldName,
                    attempted_value = negativeEx.AttemptedValue
                };
                _logger.LogWarning(exception, "[{CorrelationId}] Negative stock violation: {Message}", correlationId, negativeEx.Message);
                break;

            case InvalidStockOperationException opEx:
                statusCode = HttpStatusCode.UnprocessableEntity; // 422
                errorCode = opEx.ErrorCode.Equals("INVALID_STOCK_OPERATION", StringComparison.OrdinalIgnoreCase) 
                    ? "VALIDATION_FAILED" 
                    : opEx.ErrorCode;
                _logger.LogWarning(exception, "[{CorrelationId}] Invalid stock operation: {Message}", correlationId, opEx.Message);
                break;

            case KeyNotFoundException notFoundEx:
                statusCode = HttpStatusCode.NotFound; // 404
                errorCode = "RESOURCE_NOT_FOUND";
                _logger.LogInformation(exception, "[{CorrelationId}] Resource not found: {Message}", correlationId, notFoundEx.Message);
                break;

            default:
                statusCode = HttpStatusCode.InternalServerError; // 500
                errorCode = "INTERNAL_SERVER_ERROR";
                message = "Đã xảy ra lỗi máy chủ nội bộ. Vui lòng liên hệ quản trị viên kèm mã phiên.";
                _logger.LogError(exception, "[{CorrelationId}] Unhandled server error occurred.", correlationId);
                break;
        }

        context.Response.ContentType = "application/json";
        context.Response.StatusCode = (int)statusCode;

        var envelope = ApiEnvelope<object>.Fail(errorCode, message, details, correlationId);
        envelope.Meta.Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds();

        var jsonOptions = new JsonSerializerOptions
        {
            PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
            DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull
        };

        var json = JsonSerializer.Serialize(envelope, jsonOptions);
        await context.Response.WriteAsync(json);
    }
}
