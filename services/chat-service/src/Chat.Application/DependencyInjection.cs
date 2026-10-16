using Microsoft.Extensions.DependencyInjection;

namespace Chat.Application;

public static class DependencyInjection
{
    public static IServiceCollection AddApplication(this IServiceCollection services)
    {
        // MediatR or other application services if needed
        return services;
    }
}
