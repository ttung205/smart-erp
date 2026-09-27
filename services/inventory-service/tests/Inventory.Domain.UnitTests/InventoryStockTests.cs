using Inventory.Domain.Entities;
using Inventory.Domain.Exceptions;
using Xunit;

namespace Inventory.Domain.UnitTests;

public class InventoryStockTests
{
    [Fact]
    public void Available_ShouldEqual_QuantityOnHandMinusQuantityReserved()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 100);

        // Act & Assert
        Assert.Equal(100, stock.Available);

        stock.Reserve(30);
        Assert.Equal(100, stock.QuantityOnHand);
        Assert.Equal(30, stock.QuantityReserved);
        Assert.Equal(70, stock.Available);
    }

    [Fact]
    public void Reserve_WithSufficientStock_ShouldIncreaseReservedAndDecreaseAvailable()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 50);

        // Act
        stock.Reserve(20);

        // Assert
        Assert.Equal(50, stock.QuantityOnHand);
        Assert.Equal(20, stock.QuantityReserved);
        Assert.Equal(30, stock.Available);
    }

    [Fact]
    public void Reserve_WithExcessiveQuantity_ShouldThrowInsufficientStockException()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 20);

        // Act & Assert
        var ex = Assert.Throws<InsufficientStockException>(() => stock.Reserve(25));
        Assert.Equal(101, ex.ProductId);
        Assert.Equal(1, ex.WarehouseId);
        Assert.Equal(25, ex.RequestedQuantity);
        Assert.Equal(20, ex.AvailableQuantity);
        Assert.Equal("INSUFFICIENT_STOCK", ex.ErrorCode);
    }

    [Theory]
    [InlineData(0)]
    [InlineData(-5)]
    public void Reserve_WithInvalidQuantity_ShouldThrowInvalidStockOperationException(int invalidQty)
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 50);

        // Act & Assert
        Assert.Throws<InvalidStockOperationException>(() => stock.Reserve(invalidQty));
    }

    [Fact]
    public void Release_WithValidQuantity_ShouldDecreaseReservedAndIncreaseAvailable()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 100);
        stock.Reserve(40);

        // Act
        stock.Release(15);

        // Assert
        Assert.Equal(100, stock.QuantityOnHand);
        Assert.Equal(25, stock.QuantityReserved);
        Assert.Equal(75, stock.Available);
    }

    [Fact]
    public void Release_MoreThanReserved_ShouldThrowInvalidStockOperationException()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 50);
        stock.Reserve(10);

        // Act & Assert
        Assert.Throws<InvalidStockOperationException>(() => stock.Release(15));
    }

    [Fact]
    public void Receive_WithValidQuantity_ShouldIncreaseOnHandAndAvailable()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 50);
        stock.Reserve(10);

        // Act
        stock.Receive(30);

        // Assert
        Assert.Equal(80, stock.QuantityOnHand);
        Assert.Equal(10, stock.QuantityReserved);
        Assert.Equal(70, stock.Available);
    }

    [Fact]
    public void Issue_WithValidQuantity_ShouldDecreaseBothOnHandAndReserved()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 100);
        stock.Reserve(40);

        // Act
        stock.Issue(40);

        // Assert
        Assert.Equal(60, stock.QuantityOnHand);
        Assert.Equal(0, stock.QuantityReserved);
        Assert.Equal(60, stock.Available);
    }

    [Fact]
    public void DirectIssue_WithSufficientStock_ShouldDecreaseOnHandAndAvailable()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 50);

        // Act
        stock.DirectIssue(15);

        // Assert
        Assert.Equal(35, stock.QuantityOnHand);
        Assert.Equal(0, stock.QuantityReserved);
        Assert.Equal(35, stock.Available);
    }

    [Fact]
    public void DirectIssue_WithInsufficientAvailable_ShouldThrowInsufficientStockException()
    {
        // Arrange
        var stock = new InventoryStock(productId: 101, warehouseId: 1, initialOnHand: 50);
        stock.Reserve(40); // Available = 10

        // Act & Assert
        Assert.Throws<InsufficientStockException>(() => stock.DirectIssue(15));
    }

    [Fact]
    public void Constructor_WithNegativeOnHand_ShouldThrowNegativeStockException()
    {
        // Act & Assert
        var ex = Assert.Throws<NegativeStockException>(() => new InventoryStock(productId: 10, warehouseId: 1, initialOnHand: -10));
        Assert.Equal("NEGATIVE_STOCK", ex.ErrorCode);
    }
}
