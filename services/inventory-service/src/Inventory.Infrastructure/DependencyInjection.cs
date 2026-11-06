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
        services.AddDbContext<InventoryDbContext>(options =>
        {
            var mySqlConn = configuration.GetConnectionString("MySqlConnection");
            options.UseMySql(mySqlConn, ServerVersion.AutoDetect(mySqlConn));
        });

        services.AddScoped<IInventoryStockManager, InventoryStockManager>();
        services.AddScoped<IOutboxService, OutboxService>();
        services.AddSingleton<IAccessTokenValidator, HmacJwtTokenValidator>();
        services.AddScoped<IEventConsumerHandler, EventConsumerHandler>();

        // RabbitMQ Connection & Background Workers
        services.AddSingleton<Inventory.Infrastructure.Messaging.RabbitMqConnection>();
        // Nhiều bản sao (HA): chỉ một máy chạy relay (Outbox__RelayEnabled=false ở các máy còn lại) để không phát event trùng.
        if (!string.Equals(configuration["Outbox:RelayEnabled"], "false", StringComparison.OrdinalIgnoreCase))
        {
            services.AddHostedService<Inventory.Infrastructure.Messaging.OutboxPublisherWorker>();
        }
        services.AddHostedService<Inventory.Infrastructure.Messaging.RabbitMqConsumerWorker>();

        return services;
    }
}
