using Chat.Application.Common.Interfaces;
using Chat.Infrastructure.Identity;
using Chat.Infrastructure.Persistence;
using Chat.Infrastructure.Realtime;
using Chat.Infrastructure.Security;
using Chat.Infrastructure.Storage;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using MongoDB.Driver;

namespace Chat.Infrastructure;

public static class DependencyInjection
{
    public static IServiceCollection AddInfrastructure(this IServiceCollection services, IConfiguration configuration)
    {
        // Replica set một node: directConnection để chạy được từ máy dev (tên member trong replica set chỉ phân giải trong Docker)
        var connectionString = configuration.GetConnectionString("ChatMongoConnection") ?? "mongodb://127.0.0.1:27017/?directConnection=true";
        var databaseName = configuration["Mongo:Database"] ?? "erp_chat_db";
        services.AddSingleton<IMongoClient>(_ => new MongoClient(connectionString));
        services.AddSingleton(provider => new MongoChatContext(provider.GetRequiredService<IMongoClient>(), databaseName));
        services.AddSingleton<IChatRepository, MongoChatRepository>();

        services.AddMemoryCache();
        services.AddHttpClient<IUserDirectory, IdentityUserDirectory>(client => client.Timeout = TimeSpan.FromSeconds(10));
        services.AddSingleton<IFileStorage, LocalFileStorage>();
        services.AddSingleton<IAccessTokenValidator, HmacJwtTokenValidator>();

        services.AddSingleton<PresenceTracker>();
        services.AddSingleton<IPresenceTracker>(provider => provider.GetRequiredService<PresenceTracker>());

        return services;
    }
}
