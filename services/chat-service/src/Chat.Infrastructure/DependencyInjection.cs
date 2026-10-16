using Chat.Application.Common.Interfaces;
using Chat.Infrastructure.Identity;
using Chat.Infrastructure.Persistence;
using Chat.Infrastructure.Realtime;
using Chat.Infrastructure.Security;
using Chat.Infrastructure.Storage;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;

namespace Chat.Infrastructure;

public static class DependencyInjection
{
    public static IServiceCollection AddInfrastructure(this IServiceCollection services, IConfiguration configuration)
    {
        var connection = new SqliteConnectionStringBuilder(configuration.GetConnectionString("ChatDbConnection") ?? "Data Source=chat-messenger.db")
        {
            DefaultTimeout = 30
        };
        services.AddDbContext<ChatDbContext>(options => options.UseSqlite(connection.ToString()));
        services.AddScoped<IChatDbContext>(provider => provider.GetRequiredService<ChatDbContext>());

        services.AddMemoryCache();
        services.AddHttpClient<IUserDirectory, IdentityUserDirectory>(client => client.Timeout = TimeSpan.FromSeconds(10));
        services.AddSingleton<IFileStorage, LocalFileStorage>();
        services.AddSingleton<IAccessTokenValidator, HmacJwtTokenValidator>();

        services.AddSingleton<PresenceTracker>();
        services.AddSingleton<IPresenceTracker>(provider => provider.GetRequiredService<PresenceTracker>());

        return services;
    }
}
