using Inventory.Domain.Entities;
using Inventory.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Inventory.Infrastructure.Tests;

/// <summary>Bốn kho chuẩn được tạo khi database trống, kể cả khi không bật AUTO_SEED.</summary>
public class DbInitializerWarehouseTests : IDisposable
{
    private readonly TestDatabase _database = new();

    public void Dispose() => _database.Dispose();

    [Fact]
    public async Task EnsureBaseWarehouses_KhiBangTrong_TaoBonKhoVoiIdCoDinh()
    {
        await using var context = _database.CreateContext();

        await DbInitializer.EnsureBaseWarehousesAsync(context);

        var warehouses = await context.Warehouses.AsNoTracking().OrderBy(w => w.Id).ToListAsync();
        Assert.Equal(["WH-MAIN", "WH-TECH", "WH-PENDING", "WH-TECH-01"], warehouses.Select(w => w.Code));
        Assert.Equal([1L, 2L, 3L, 4L], warehouses.Select(w => w.Id));
        Assert.Equal("Kho Chính", warehouses[0].Name);
        Assert.True(warehouses[0].CanSell);
        Assert.All(warehouses.Skip(1), w => Assert.False(w.CanSell));
        Assert.All(warehouses, w => Assert.True(w.IsActive));
    }

    [Fact]
    public async Task EnsureBaseWarehouses_GoiHaiLan_KhongTaoTrung()
    {
        await using var context = _database.CreateContext();

        await DbInitializer.EnsureBaseWarehousesAsync(context);
        await DbInitializer.EnsureBaseWarehousesAsync(context);

        Assert.Equal(4, await context.Warehouses.CountAsync());
    }

    [Fact]
    public async Task EnsureBaseWarehouses_KhiDaCoKho_KhongDungToiMoiTruongDangChay()
    {
        await using var context = _database.CreateContext();
        context.Warehouses.Add(new Warehouse { Id = 1, Code = "WH-HN-01", Name = "Kho Hà Nội", IsActive = true });
        await context.SaveChangesAsync();

        await DbInitializer.EnsureBaseWarehousesAsync(context);

        var warehouses = await context.Warehouses.AsNoTracking().ToListAsync();
        Assert.Single(warehouses);
        Assert.Equal("WH-HN-01", warehouses[0].Code);
    }

    [Fact]
    public async Task SeedAsync_KhongBatSeedData_VanTaoKhoChuan_VaKhongTaoTonKhoMau()
    {
        await using var context = _database.CreateContext();

        await DbInitializer.SeedAsync(context, seedData: false);

        Assert.Equal(4, await context.Warehouses.CountAsync());
        Assert.False(await context.InventoryStocks.AnyAsync());
    }

    [Fact]
    public async Task SeedAsync_BatSeedData_TaoTonKhoMauTrenBonKhoChuan()
    {
        await using var context = _database.CreateContext();

        await DbInitializer.SeedAsync(context, seedData: true);

        Assert.Equal(4, await context.Warehouses.CountAsync());
        var stockWarehouseIds = await context.InventoryStocks.Select(s => s.WarehouseId).Distinct().OrderBy(id => id).ToListAsync();
        Assert.Equal([1L, 2L, 3L, 4L], stockWarehouseIds);
    }
}
