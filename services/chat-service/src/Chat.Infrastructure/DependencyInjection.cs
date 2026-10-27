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
        // Polly: timeout 5s mỗi lần, retry 2 lần, circuit breaker mở 30s khi identity-service lỗi liên tục
        services.AddHttpClient<IUserDirectory, IdentityUserDirectory>()
            .AddStandardResilienceHandler(options =>
            {
                options.AttemptTimeout.Timeout = TimeSpan.FromSeconds(5);
                options.TotalRequestTimeout.Timeout = TimeSpan.FromSeconds(15);
                options.Retry.MaxRetryAttempts = 2;
                options.CircuitBreaker.SamplingDuration = TimeSpan.FromSeconds(30);
                options.CircuitBreaker.MinimumThroughput = 5;
                options.CircuitBreaker.BreakDuration = TimeSpan.FromSeconds(30);
            });
        services.AddSingleton<IFileStorage, LocalFileStorage>();
        services.AddSingleton<IAccessTokenValidator, HmacJwtTokenValidator>();

        services.AddSingleton<PresenceTracker>();
        services.AddSingleton<IPresenceTracker>(provider => provider.GetRequiredService<PresenceTracker>());

        return services;
    }
}
