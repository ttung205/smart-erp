namespace Inventory.Api.Middlewares;

public class CorrelationIdMiddleware
{
    private const string CorrelationIdHeaderKey = "X-Correlation-ID";
    private readonly RequestDelegate _next;

    public CorrelationIdMiddleware(RequestDelegate _next)
    {
        this._next = _next;
    }

    public async Task InvokeAsync(HttpContext context)
    {
        var correlationId = context.Request.Headers[CorrelationIdHeaderKey].FirstOrDefault();

        if (string.IsNullOrWhiteSpace(correlationId))
        {
            correlationId = $"req_corr_{Guid.NewGuid():N}";
        }

        context.Items["CorrelationId"] = correlationId;

        context.Response.OnStarting(() =>
        {
            context.Response.Headers[CorrelationIdHeaderKey] = correlationId;
            return Task.CompletedTask;
        });

        await _next(context);
    }
}
