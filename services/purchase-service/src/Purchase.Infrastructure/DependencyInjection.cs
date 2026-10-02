using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Purchase.Application.Interfaces;
using Purchase.Infrastructure.Messaging;
using Purchase.Infrastructure.Persistence;
using Purchase.Infrastructure.Services;

namespace Purchase.Infrastructure;

public static class DependencyInjection
{
    public static IServiceCollection AddInfrastructure(this IServiceCollection services, IConfiguration configuration)
    {
        var dbProvider = configuration["DatabaseProvider"] ?? "Sqlite";

        services.AddDbContext<PurchaseDbContext>(options =>
        {
            if (dbProvider.Equals("MySql", StringComparison.OrdinalIgnoreCase))
            {
                var mySqlConn = configuration.GetConnectionString("MySqlConnection") 
                    ?? configuration.GetConnectionString("DefaultConnection");
                options.UseMySql(mySqlConn!, ServerVersion.AutoDetect(mySqlConn));
            }
            else
            {
                var sqliteConn = configuration.GetConnectionString("SqliteConnection") 
                    ?? "Data Source=purchase.db";
                options.UseSqlite(sqliteConn);
            }
        });

        // 2. IPurchaseDbContext mapping
        services.AddScoped<IPurchaseDbContext>(sp => sp.GetRequiredService<PurchaseDbContext>());

        // 3. Transactional Outbox Pattern
        services.AddScoped<IOutboxService, OutboxService>();

        // 4. RabbitMQ Broker Connection & Outbox Relay Background Worker
        services.AddSingleton<RabbitMqConnection>();
        services.AddHostedService<OutboxRelayBackgroundService>();

        return services;
    }
}
