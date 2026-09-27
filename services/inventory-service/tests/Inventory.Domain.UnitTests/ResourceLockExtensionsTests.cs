using Inventory.Domain.Common;
using Inventory.Domain.Entities;
using Inventory.Domain.Extensions;
using Xunit;

namespace Inventory.Domain.UnitTests;

public class ResourceLockExtensionsTests
{
    private record SimpleStockItem(long WarehouseId, long ProductId, int Quantity) : IStockResourceIdentifier;

    [Fact]
    public void ToDeterministicLockOrder_ShouldSortByWarehouseIdAscendingThenProductIdAscending()
    {
        // Arrange: Danh sách lộn xộn các cặp kho và sản phẩm
        var items = new List<SimpleStockItem>
        {
            new(WarehouseId: 2, ProductId: 105, Quantity: 2),
            new(WarehouseId: 1, ProductId: 200, Quantity: 1),
            new(WarehouseId: 2, ProductId: 101, Quantity: 5),
            new(WarehouseId: 1, ProductId: 102, Quantity: 3),
            new(WarehouseId: 1, ProductId: 50,  Quantity: 10),
        };

        // Act
        var ordered = items.ToDeterministicLockOrder();

        // Assert
        Assert.Equal(5, ordered.Count);
        
        // Cùng Warehouse 1, ProductId tăng dần
        Assert.Equal(1, ordered[0].WarehouseId);
        Assert.Equal(50, ordered[0].ProductId);

        Assert.Equal(1, ordered[1].WarehouseId);
        Assert.Equal(102, ordered[1].ProductId);

        Assert.Equal(1, ordered[2].WarehouseId);
        Assert.Equal(200, ordered[2].ProductId);

        // Sang Warehouse 2, ProductId tăng dần
        Assert.Equal(2, ordered[3].WarehouseId);
        Assert.Equal(101, ordered[3].ProductId);

        Assert.Equal(2, ordered[4].WarehouseId);
        Assert.Equal(105, ordered[4].ProductId);
    }

    [Fact]
    public void ToDeterministicLockOrder_OppositeOrders_ShouldProduceIdenticalLockSequence()
    {
        // Giả lập 2 Transaction đối nghịch nhau trong kịch bản gây Deadlock kinh điển:
        // Tx1 muốn khóa item A (wh: 1, prod: 10) trước, sau đó khóa item B (wh: 1, prod: 20)
        // Tx2 muốn khóa item B (wh: 1, prod: 20) trước, sau đó khóa item A (wh: 1, prod: 10)
        var tx1Items = new List<SimpleStockItem>
        {
            new(WarehouseId: 1, ProductId: 10, Quantity: 1),
            new(WarehouseId: 1, ProductId: 20, Quantity: 2),
        };

        var tx2Items = new List<SimpleStockItem>
        {
            new(WarehouseId: 1, ProductId: 20, Quantity: 2),
            new(WarehouseId: 1, ProductId: 10, Quantity: 1),
        };

        // Act
        var tx1LockOrder = tx1Items.ToDeterministicLockOrder();
        var tx2LockOrder = tx2Items.ToDeterministicLockOrder();

        // Assert: Cả 2 Transaction đều buộc phải khóa theo thứ tự giống hệt nhau -> Deadlock bị triệt tiêu 100%
        Assert.Equal(tx1LockOrder[0].ProductId, tx2LockOrder[0].ProductId);
        Assert.Equal(tx1LockOrder[1].ProductId, tx2LockOrder[1].ProductId);

        Assert.Equal(10, tx1LockOrder[0].ProductId);
        Assert.Equal(20, tx1LockOrder[1].ProductId);
    }

    [Fact]
    public void ToDeterministicLockOrder_WithSelectors_ShouldOrderCorrectly()
    {
        // Arrange
        var rawTuples = new List<(long wh, long p)>
        {
            (2, 50),
            (1, 100),
            (1, 20)
        };

        // Act
        var ordered = rawTuples.ToDeterministicLockOrder(x => x.wh, x => x.p);

        // Assert
        Assert.Equal((1, 20), ordered[0]);
        Assert.Equal((1, 100), ordered[1]);
        Assert.Equal((2, 50), ordered[2]);
    }

    [Fact]
    public void ToResourceLockKey_ShouldFormatCorrectly()
    {
        // Arrange
        var item = new SimpleStockItem(WarehouseId: 3, ProductId: 42, Quantity: 1);

        // Act
        var keyFromItem = item.ToResourceLockKey();
        var keyFromArgs = ResourceLockExtensions.ToResourceLockKey(3, 42);

        // Assert
        Assert.Equal("wh:3:prod:42", keyFromItem);
        Assert.Equal("wh:3:prod:42", keyFromArgs);
    }
}
