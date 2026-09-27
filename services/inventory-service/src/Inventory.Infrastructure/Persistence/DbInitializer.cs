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

        // 3. Seed Initial Inventory Stocks
        if (!await context.InventoryStocks.AnyAsync())
        {
            var hnWarehouse = await context.Warehouses.FirstOrDefaultAsync(w => w.Code == "WH-HN-01");
            var hcmWarehouse = await context.Warehouses.FirstOrDefaultAsync(w => w.Code == "WH-HCM-01");

            if (hnWarehouse != null && hcmWarehouse != null)
            {
                var initialStocks = new List<InventoryStock>
                {
                    // Kho Hà Nội
                    new()
                    {
                        ProductId = 1, // Laptop MacBook Pro 14 M3
                        WarehouseId = hnWarehouse.Id,
                        QuantityOnHand = 50,
                        QuantityReserved = 5,
                        MinStock = 10,
                        Version = 1,
                        CreatedAt = DateTime.UtcNow,
                        UpdatedAt = DateTime.UtcNow
                    },
                    new()
                    {
                        ProductId = 2, // iPhone 15 Pro Max 256GB
                        WarehouseId = hnWarehouse.Id,
                        QuantityOnHand = 80,
                        QuantityReserved = 10,
                        MinStock = 15,
                        Version = 1,
                        CreatedAt = DateTime.UtcNow,
                        UpdatedAt = DateTime.UtcNow
                    },
                    new()
                    {
                        ProductId = 3, // Màn hình Dell UltraSharp 27"
                        WarehouseId = hnWarehouse.Id,
                        QuantityOnHand = 35,
                        QuantityReserved = 0,
                        MinStock = 5,
                        Version = 1,
                        CreatedAt = DateTime.UtcNow,
                        UpdatedAt = DateTime.UtcNow
                    },

                    // Kho TP.HCM
                    new()
                    {
                        ProductId = 1,
                        WarehouseId = hcmWarehouse.Id,
                        QuantityOnHand = 40,
                        QuantityReserved = 2,
                        MinStock = 10,
                        Version = 1,
                        CreatedAt = DateTime.UtcNow,
                        UpdatedAt = DateTime.UtcNow
                    },
                    new()
                    {
                        ProductId = 2,
                        WarehouseId = hcmWarehouse.Id,
                        QuantityOnHand = 60,
                        QuantityReserved = 5,
                        MinStock = 15,
                        Version = 1,
                        CreatedAt = DateTime.UtcNow,
                        UpdatedAt = DateTime.UtcNow
                    }
                };

                await context.InventoryStocks.AddRangeAsync(initialStocks);
                await context.SaveChangesAsync();

                // 4. Seed Initial Stock Movement Audit Record
                var movements = new List<StockMovement>
                {
                    new()
                    {
                        ProductId = 1,
                        WarehouseId = hnWarehouse.Id,
                        MovementType = "PO_RECEIPT",
                        QuantityDelta = 50,
                        BalanceOnHandAfter = 50,
                        BalanceReservedAfter = 0,
                        ReferenceDoc = "PO-202609-INIT",
                        CreatedBy = 1,
                        CreatedAt = DateTime.UtcNow.AddDays(-2)
                    },
                    new()
                    {
                        ProductId = 1,
                        WarehouseId = hnWarehouse.Id,
                        MovementType = "SO_RESERVE",
                        QuantityDelta = 5,
                        BalanceOnHandAfter = 50,
                        BalanceReservedAfter = 5,
                        ReferenceDoc = "SO-202609-SAMPLE",
                        CreatedBy = 2,
                        CreatedAt = DateTime.UtcNow.AddDays(-1)
                    }
                };

                await context.StockMovements.AddRangeAsync(movements);
                await context.SaveChangesAsync();
            }
        }
    }
}
