using System.Net;
using System.Text.Json;
using Chat.Application.Common.Models;

namespace Chat.Api.Middlewares;

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
            case KeyNotFoundException:
                statusCode = HttpStatusCode.NotFound;
                errorCode = "RESOURCE_NOT_FOUND";
                _logger.LogWarning(exception, "[{CorrelationId}] Resource not found: {Message}", correlationId, exception.Message);
                break;

            case InvalidOperationException:
                statusCode = HttpStatusCode.Conflict;
                errorCode = "CONCURRENT_CONFLICT";
                _logger.LogWarning(exception, "[{CorrelationId}] Invalid operation: {Message}", correlationId, exception.Message);
                break;

            default:
                statusCode = HttpStatusCode.InternalServerError;
                errorCode = "INTERNAL_SERVER_ERROR";
                message = "Đã xảy ra lỗi hệ thống trong quá trình xử lý yêu cầu chat.";
                _logger.LogError(exception, "[{CorrelationId}] Unhandled server exception: {Message}", correlationId, exception.Message);
                break;
        }

        context.Response.ContentType = "application/json";
        context.Response.StatusCode = (int)statusCode;

        var envelope = ApiEnvelope<object>.Fail(errorCode, message, details, correlationId);
        
        var jsonOptions = new JsonSerializerOptions
        {
            PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
            DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull
        };

        var responseJson = JsonSerializer.Serialize(envelope, jsonOptions);
        await context.Response.WriteAsync(responseJson);
    }
}
