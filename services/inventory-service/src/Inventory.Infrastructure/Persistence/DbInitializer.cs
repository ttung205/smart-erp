using Inventory.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Infrastructure.Persistence;

public static class DbInitializer
{
    public static async Task SeedAsync(InventoryDbContext context)
    {
        // 1. Ensure Database & Tables Created
        await context.Database.EnsureCreatedAsync();

        // 2. Seed Warehouses
        if (!await context.Warehouses.AnyAsync())
        {
            var warehouses = new List<Warehouse>
            {
                new()
                {
                    Code = "WH-HN-01",
                    Name = "Kho Tổng Miền Bắc (Hà Nội)",
                    Location = "Cụm Công Nghiệp Nam Từ Liêm, Hà Nội",
                    IsActive = true,
                    CreatedAt = DateTime.UtcNow,
                    UpdatedAt = DateTime.UtcNow
                },
                new()
                {
                    Code = "WH-HCM-01",
                    Name = "Kho Trung Tâm Miền Nam (TP.HCM)",
                    Location = "Khu Công Nghiệp Tân Bình, Tân Phú, TP. Hồ Chí Minh",
                    IsActive = true,
                    CreatedAt = DateTime.UtcNow,
                    UpdatedAt = DateTime.UtcNow
                },
                new()
                {
                    Code = "WH-DN-01",
                    Name = "Kho Trung Chuyển Miền Trung (Đà Nẵng)",
                    Location = "Khu Công Nghiệp Hòa Khánh, Liên Chiểu, Đà Nẵng",
                    IsActive = true,
                    CreatedAt = DateTime.UtcNow,
                    UpdatedAt = DateTime.UtcNow
                }
            };

            await context.Warehouses.AddRangeAsync(warehouses);
            await context.SaveChangesAsync();
        }

        // 3. Seed Initial Inventory Stocks (100 products from Catalog, 100 units each)
        if (!await context.InventoryStocks.AnyAsync())
        {
            var warehouses = await context.Warehouses.ToListAsync();
            if (warehouses.Any())
            {
                var initialStocks = new List<InventoryStock>();
                for (int productId = 1; productId <= 100; productId++)
                {
                    foreach (var w in warehouses)
                    {
                        initialStocks.Add(new InventoryStock
                        {
                            ProductId = productId,
                            WarehouseId = w.Id,
                            QuantityOnHand = 100,
                            QuantityReserved = 0,
                            MinStock = 10,
                            Version = 1,
                            CreatedAt = DateTime.UtcNow,
                            UpdatedAt = DateTime.UtcNow
                        });
                    }
                }

                await context.InventoryStocks.AddRangeAsync(initialStocks);
                await context.SaveChangesAsync();
            }
        }
    }
}
