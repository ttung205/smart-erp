using Inventory.Application.Interfaces;
using Inventory.Infrastructure.Persistence;
using Inventory.Infrastructure.Security;
using Inventory.Infrastructure.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;

namespace Inventory.Infrastructure;

public static class DependencyInjection
{
    public static IServiceCollection AddInfrastructure(this IServiceCollection services, IConfiguration configuration)
    {
        var dbProvider = configuration["DatabaseProvider"] ?? "Sqlite";

        services.AddDbContext<InventoryDbContext>(options =>
        {
            if (dbProvider.Equals("MySql", StringComparison.OrdinalIgnoreCase))
            {
                var mySqlConn = configuration.GetConnectionString("MySqlConnection");
                options.UseMySql(mySqlConn, ServerVersion.AutoDetect(mySqlConn));
            }
            else
            {
                var sqliteConn = configuration.GetConnectionString("SqliteConnection") ?? "Data Source=inventory.db";
                options.UseSqlite(sqliteConn);
            }
        });

        services.AddScoped<IInventoryStockManager, InventoryStockManager>();
        services.AddScoped<IOutboxService, OutboxService>();
        services.AddSingleton<IAccessTokenValidator, HmacJwtTokenValidator>();
        services.AddScoped<IEventConsumerHandler, EventConsumerHandler>();

        // RabbitMQ Connection & Background Workers
        services.AddSingleton<Inventory.Infrastructure.Messaging.RabbitMqConnection>();
        services.AddHostedService<Inventory.Infrastructure.Messaging.OutboxPublisherWorker>();
        services.AddHostedService<Inventory.Infrastructure.Messaging.RabbitMqConsumerWorker>();

        return services;
    }
}
