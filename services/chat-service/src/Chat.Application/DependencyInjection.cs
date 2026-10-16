using Chat.Application.Services;
using Microsoft.Extensions.DependencyInjection;

namespace Chat.Application;

public static class DependencyInjection
{
    public static IServiceCollection AddApplication(this IServiceCollection services)
    {
        services.AddScoped<ConversationService>();
        services.AddScoped<MessageService>();
        services.AddScoped<UserService>();
        return services;
    }
}
