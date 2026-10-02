using Microsoft.Extensions.DependencyInjection;
using Purchase.Application.Interfaces;
using Purchase.Application.Services;

namespace Purchase.Application;

public static class DependencyInjection
{
    public static IServiceCollection AddApplication(this IServiceCollection services)
    {
        services.AddScoped<IPurchaseRequisitionService, PurchaseRequisitionService>();
        services.AddScoped<IPurchaseOrderService, PurchaseOrderService>();
        services.AddScoped<IGoodsReceiptService, GoodsReceiptService>();
        services.AddScoped<IThreeWayMatchingService, ThreeWayMatchingService>();

        return services;
    }
}
