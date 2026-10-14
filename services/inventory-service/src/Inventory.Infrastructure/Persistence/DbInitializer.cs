using Inventory.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Infrastructure.Persistence;

public static class DbInitializer
{
    private static readonly Dictionary<int, string> ProductOriginMap = new()
    {
        { 1, "Hàn Quốc" },
        { 2, "Thái Lan" },
        { 3, "Nhật Bản" },
        { 4, "Nhật Bản" },
        { 5, "Hàn Quốc" },
        { 6, "Nhật Bản" },
        { 7, "Thái Lan" },
        { 8, "Nhật Bản" },
        { 9, "Nhật Bản" },
        { 10, "Nhật Bản" },
        { 11, "Hàn Quốc" },
        { 12, "Nhật Bản" },
        { 13, "Nhật Bản" },
        { 14, "Hàn Quốc" },
        { 15, "Nhật Bản" },
        { 16, "Nhật Bản" },
        { 17, "Hàn Quốc" },
        { 18, "Nhật Bản" },
        { 19, "Thái Lan" },
        { 20, "Hàn Quốc" },
        { 21, "Thái Lan" },
        { 22, "Nhật Bản" },
        { 23, "Nhật Bản" },
        { 24, "Thái Lan" },
        { 25, "Thái Lan" },
        { 26, "Nhật Bản" },
        { 27, "Hàn Quốc" },
        { 28, "Nhật Bản" },
        { 29, "Nhật Bản" },
        { 30, "Hàn Quốc" },
        { 31, "Nhật Bản" },
        { 32, "Hàn Quốc" },
        { 33, "Hàn Quốc" },
        { 34, "Nhật Bản" },
        { 35, "Hàn Quốc" },
        { 36, "Nhật Bản" },
        { 37, "Nhật Bản" },
        { 38, "Nhật Bản" },
        { 39, "Trung Quốc" },
        { 40, "Nhật Bản" },
        { 41, "Hàn Quốc" },
        { 42, "Hàn Quốc" },
        { 43, "Nhật Bản" },
        { 44, "Nhật Bản" },
        { 45, "Nhật Bản" },
        { 46, "Nhật Bản" },
        { 47, "Nhật Bản" },
        { 48, "Thái Lan" },
        { 49, "Nhật Bản" },
        { 50, "Nhật Bản" },
        { 51, "Hàn Quốc" },
        { 52, "Đức" },
        { 53, "Đức" },
        { 54, "Thái Lan" },
        { 55, "Hàn Quốc" },
        { 56, "Hàn Quốc" },
        { 57, "Hàn Quốc" },
        { 58, "Thái Lan" },
        { 59, "Thái Lan" },
        { 60, "Nhật Bản" },
        { 61, "Trung Quốc" },
        { 62, "Hàn Quốc" },
        { 63, "Nhật Bản" },
        { 64, "Thái Lan" },
        { 65, "Anh" },
        { 66, "Hà Lan" },
        { 67, "Trung Quốc" },
        { 68, "Nhật Bản" },
        { 69, "Hà Lan" },
        { 70, "Hàn Quốc" },
        { 71, "Hà Lan" },
        { 72, "Nhật Bản" },
        { 73, "Nhật Bản" },
        { 74, "Nhật Bản" },
        { 75, "Hàn Quốc" },
        { 76, "Việt Nam" },
        { 77, "Nhật Bản" },
        { 78, "Nhật Bản" },
        { 79, "Nhật Bản" },
        { 80, "Nhật Bản" },
        { 81, "Hàn Quốc" },
        { 82, "Việt Nam" },
        { 83, "Thái Lan" },
        { 84, "Thái Lan" },
        { 85, "Nhật Bản" },
        { 86, "Nhật Bản" },
        { 87, "Nhật Bản" },
        { 88, "Hàn Quốc" },
        { 89, "Nhật Bản" },
        { 90, "Trung Quốc" },
        { 91, "Hàn Quốc" },
        { 92, "Mỹ" },
        { 93, "Trung Quốc" },
        { 94, "Hàn Quốc" },
        { 95, "Trung Quốc" },
        { 96, "Nhật Bản" },
        { 97, "Hà Lan" },
        { 98, "Trung Quốc" },
        { 99, "Hà Lan" },
        { 100, "Nhật Bản" },
    };
    public static async Task SeedAsync(InventoryDbContext context)
    {
        // 1. Ensure Database & Tables Created
        await context.Database.EnsureCreatedAsync();
        await EnsureSqliteSchemaAsync(context);
        const string idempotencyIndex = "ux_inventory_stock_transaction_document";
        const string reservationIdempotencyIndex = "ux_inventory_reservation_transaction_document";
        if (context.Database.IsSqlite())
        {
            await context.Database.ExecuteSqlRawAsync($"""
                CREATE UNIQUE INDEX IF NOT EXISTS {idempotencyIndex}
                ON inventory_stocks_transaction (document_type, document_id, warehouse_id, product_id)
                """);
            await context.Database.ExecuteSqlRawAsync($"""
                CREATE UNIQUE INDEX IF NOT EXISTS {reservationIdempotencyIndex}
                ON inventory_stocks_reservation_transaction (document_type, document_id, warehouse_id, product_id)
                """);
        }
        else
        {
            try
            {
                await context.Database.ExecuteSqlRawAsync($"""
                    CREATE UNIQUE INDEX {idempotencyIndex}
                    ON inventory_stocks_transaction (document_type, document_id, warehouse_id, product_id)
                    """);
                await context.Database.ExecuteSqlRawAsync($"""
                    CREATE UNIQUE INDEX {reservationIdempotencyIndex}
                    ON inventory_stocks_reservation_transaction (document_type, document_id, warehouse_id, product_id)
                    """);
            }
            catch (Exception exception) when (exception.Message.Contains("Duplicate key name", StringComparison.OrdinalIgnoreCase)
                || exception.Message.Contains("already exists", StringComparison.OrdinalIgnoreCase))
            {
                // The deployment already has the idempotency index.
            }
        }

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
                    CanSell = true,
                    CreatedAt = DateTime.UtcNow,
                    UpdatedAt = DateTime.UtcNow
                },
                new()
                {
                    Code = "WH-HCM-01",
                    Name = "Kho Trung Tâm Miền Nam (TP.HCM)",
                    Location = "Khu Công Nghiệp Tân Bình, Tân Phú, TP. Hồ Chí Minh",
                    IsActive = true,
                    CanSell = true,
                    CreatedAt = DateTime.UtcNow,
                    UpdatedAt = DateTime.UtcNow
                },
                new()
                {
                    Code = "WH-DN-01",
                    Name = "Kho Trung Chuyển Miền Trung (Đà Nẵng)",
                    Location = "Khu Công Nghiệp Hòa Khánh, Liên Chiểu, Đà Nẵng",
                    IsActive = true,
                    CanSell = true,
                    CreatedAt = DateTime.UtcNow,
                    UpdatedAt = DateTime.UtcNow
                }
            };

            await context.Warehouses.AddRangeAsync(warehouses);
            await context.SaveChangesAsync();
        }

        if (!await context.Warehouses.AnyAsync(w => w.Code == "WH-TECH-01"))
        {
            context.Warehouses.Add(new Warehouse
            {
                Code = "WH-TECH-01",
                Name = "Kho Kỹ thuật - Hàng chờ kiểm tra",
                Location = "Khu vực kiểm tra chất lượng hàng bán trả lại",
                IsActive = true,
                CanSell = false,
                CreatedAt = DateTime.UtcNow,
                UpdatedAt = DateTime.UtcNow
            });
            await context.SaveChangesAsync();
        }

        // 3. Seed Realistic Enterprise Inventory Stocks & Active Reservations
        if (!await context.InventoryStocks.AnyAsync())
        {
            var warehouses = await context.Warehouses.ToListAsync();
            if (warehouses.Any())
            {
                var initialStocks = new List<InventoryStock>();
                var initialReservations = new List<InventoryStockReservation>();
                var stockTransactions = new List<InventoryStockTransaction>();
                var reservationTransactions = new List<InventoryStockReservationTransaction>();

                var rng = new Random(20261012);

                for (int productId = 1; productId <= 100; productId++)
                {
                    var origin = ProductOriginMap.GetValueOrDefault(productId, "Việt Nam");

                    foreach (var w in warehouses)
                    {
                        // Phân bổ tồn kho theo quy mô và vai trò của từng kho:
                        int baseMin, baseMax;
                        if (w.Code == "WH-HCM-01")
                        {
                            baseMin = 75; baseMax = 320;
                        }
                        else if (w.Code == "WH-HN-01")
                        {
                            baseMin = 45; baseMax = 250;
                        }
                        else
                        {
                            baseMin = 15; baseMax = 110;
                        }

                        // Một vài sản phẩm đặc biệt (ID 9, 23, 71) có mức tồn thấp (cận định mức 6-12 chiếc)
                        // để kích hoạt cảnh báo Min-Stock và kiểm thử tính năng "Đề nghị mua (PR)"
                        int onHand;
                        if (productId == 9 || productId == 23 || productId == 71)
                        {
                            onHand = rng.Next(6, 13);
                        }
                        else
                        {
                            int raw = rng.Next(baseMin, baseMax + 1);
                            onHand = (raw / 5) * 5 + (rng.Next(0, 2) == 0 ? 0 : rng.Next(1, 5));
                        }

                        // Giả lập giữ chỗ thực tế cho các đơn bán hàng đang xử lý (Active Reservations)
                        // Khoảng 25% mặt hàng có giữ chỗ từ 4 đến 28 chiếc
                        int reserved = 0;
                        if (onHand > 25 && (productId % 4 == 0 || productId % 7 == 0))
                        {
                            reserved = rng.Next(4, Math.Min(28, onHand / 3));
                        }

                        int available = Math.Max(0, onHand - reserved);

                        initialStocks.Add(new InventoryStock
                        {
                            ProductId = productId,
                            WarehouseId = w.Id,
                            Quantity = onHand,
                            Origin = origin,
                            UpdatedAt = DateTime.UtcNow
                        });

                        initialReservations.Add(new InventoryStockReservation
                        {
                            ProductId = productId,
                            WarehouseId = w.Id,
                            Quantity = available,
                            UpdatedAt = DateTime.UtcNow
                        });

                        // Ghi nhận sổ nhật ký nhập kho ban đầu
                        stockTransactions.Add(new InventoryStockTransaction
                        {
                            ProductId = productId,
                            WarehouseId = w.Id,
                            DocumentType = "GOODS_RECEIPT",
                            DocumentId = $"GR-202610-{(1000 + productId)}",
                            BalanceBefore = 0,
                            QuantityDelta = onHand,
                            BalanceAfter = onHand,
                            Notes = "Nhập kho ban đầu theo lô hàng kiểm đếm kỳ",
                            CreatedAt = DateTime.UtcNow.AddDays(-rng.Next(3, 15))
                        });

                        // Nếu có giữ chỗ, ghi sổ nhật ký giữ chỗ để khi nhấn vào popup hiển thị đúng đơn hàng SO
                        if (reserved > 0)
                        {
                            reservationTransactions.Add(new InventoryStockReservationTransaction
                            {
                                ProductId = productId,
                                WarehouseId = w.Id,
                                DocumentType = "SALES_ORDER",
                                DocumentId = $"SO-202610-{(5000 + productId * 3 + (int)w.Id)}",
                                BalanceBefore = onHand,
                                QuantityDelta = -reserved,
                                BalanceAfter = available,
                                Notes = "Khóa giữ chỗ cho đơn bán hàng đang xử lý xuất kho",
                                CreatedAt = DateTime.UtcNow.AddHours(-rng.Next(2, 48))
                            });
                        }
                    }
                }

                await context.InventoryStocks.AddRangeAsync(initialStocks);
                await context.InventoryStockReservations.AddRangeAsync(initialReservations);
                await context.InventoryStockTransactions.AddRangeAsync(stockTransactions);
                await context.InventoryStockReservationTransactions.AddRangeAsync(reservationTransactions);
                await context.SaveChangesAsync();
            }
        }
    }

    private static async Task EnsureSqliteSchemaAsync(InventoryDbContext context)
    {
        if (!context.Database.IsSqlite())
        {
            return;
        }

        var connection = context.Database.GetDbConnection();
        await connection.OpenAsync();
        try
        {
            await using var command = connection.CreateCommand();
            command.CommandText = "SELECT COUNT(*) FROM pragma_table_info('inventory_stocks') WHERE name = 'origin'";
            var originColumnExists = Convert.ToInt32(await command.ExecuteScalarAsync()) > 0;
            if (!originColumnExists)
            {
                await context.Database.ExecuteSqlRawAsync(
                    "ALTER TABLE inventory_stocks ADD COLUMN origin TEXT NOT NULL DEFAULT 'Việt Nam'");
            }
        }
        finally
        {
            await connection.CloseAsync();
        }
    }
}
