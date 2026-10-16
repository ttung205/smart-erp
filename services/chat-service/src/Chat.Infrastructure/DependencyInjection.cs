using Chat.Application.Common.Interfaces;
using Chat.Infrastructure.Messaging;
using Chat.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;

namespace Chat.Infrastructure;

public static class DependencyInjection
{
    public static IServiceCollection AddInfrastructure(this IServiceCollection services, IConfiguration configuration)
    {
        var rawConnectionString = configuration.GetConnectionString("ChatDbConnection") ?? "Data Source=chat.db";
        var sqliteBuilder = new Microsoft.Data.Sqlite.SqliteConnectionStringBuilder(rawConnectionString)
        {
            DefaultTimeout = 30,
            Cache = Microsoft.Data.Sqlite.SqliteCacheMode.Shared
        };

        services.AddDbContext<ChatDbContext>(options =>
        {
            options.UseSqlite(sqliteBuilder.ToString());
        });

        services.AddScoped<IChatDbContext>(provider => provider.GetRequiredService<ChatDbContext>());

        // Đăng ký RabbitMQ Connection & Bot Consumer Worker
        services.AddSingleton<RabbitMqConnection>();
        services.AddHostedService<RabbitMqBotConsumerWorker>();

        return services;
    }
}
