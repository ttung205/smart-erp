using Purchase.Application.DTOs.PurchaseOrders;
using Purchase.Application.DTOs.Requisitions;
using Purchase.Application.Services;
using Purchase.Domain.Exceptions;
using Xunit;

namespace Purchase.Tests;

public class PurchaseOrderAllocationTests
{
    private async Task<(PurchaseRequisitionService prService, PurchaseOrderService poService, long prId, long prItemId)> SetupApprovedPrAsync(
        Purchase.Infrastructure.Persistence.PurchaseDbContext context, int requestedQty = 100, int approvedQty = 80)
    {
        var prService = new PurchaseRequisitionService(context);
        var poService = new PurchaseOrderService(context);

        var pr = await prService.CreateAsync(new CreatePurchaseRequisitionDto
        {
            WarehouseId = 1,
            RequestedBy = 10,
            Items = new List<CreatePurchaseRequisitionItemDto>
            {
                new()
                {
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityRequested = requestedQty,
                    EstimatedUnitCost = 15000000m
                }
            }
        });

        await prService.SubmitAsync(pr.Id);

        var approved = await prService.ApproveAsync(pr.Id, new ApprovePurchaseRequisitionDto
        {
            ApprovedBy = 99,
            Items = new List<ApprovePrItemAdjustDto>
            {
                new() { ItemId = pr.Items[0].Id, QuantityApproved = approvedQty }
            }
        });

        return (prService, poService, approved.Id, approved.Items[0].Id);
    }

    [Fact]
    public async Task CreatePurchaseOrder_WithPrAllocation_ShouldUpdatePrQuantityOrderedAndStatus()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (prService, poService, prId, prItemId) = await SetupApprovedPrAsync(context, 100, 80);

        var poDto = new CreatePurchaseOrderDto
        {
            SupplierId = 1,
            WarehouseId = 1,
            CreatedBy = 20,
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new()
                {
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityOrdered = 50,
                    UnitCost = 14800000m,
                    Allocations = new List<CreatePrAllocationInputDto>
                    {
                        new()
                        {
                            PrId = prId,
                            PrItemId = prItemId,
                            AllocatedQuantity = 50
                        }
                    }
                }
            }
        };

        var po = await poService.CreateAsync(poDto);

        Assert.NotNull(po);
        Assert.StartsWith("PO-", po.Code);
        Assert.Single(po.Allocations);
        Assert.Equal(50, po.Allocations[0].AllocatedQuantity);

        // Kiểm tra PR item đã được tăng quantity_ordered lên 50 và order_status là PartiallyOrdered
        var updatedPr = await prService.GetByIdAsync(prId);
        Assert.NotNull(updatedPr);
        Assert.Equal(50, updatedPr.Items[0].QuantityOrdered);
        Assert.Equal("PartiallyOrdered", updatedPr.OrderStatus);
    }

    [Fact]
    public async Task CreatePurchaseOrder_ExceedingApprovedPrQuantity_ShouldThrowException()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (_, poService, prId, prItemId) = await SetupApprovedPrAsync(context, 100, 80);

        var poDto = new CreatePurchaseOrderDto
        {
            SupplierId = 1,
            WarehouseId = 1,
            CreatedBy = 20,
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new()
                {
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityOrdered = 90, // Vượt quá 80 đã duyệt
                    UnitCost = 14800000m,
                    Allocations = new List<CreatePrAllocationInputDto>
                    {
                        new()
                        {
                            PrId = prId,
                            PrItemId = prItemId,
                            AllocatedQuantity = 90
                        }
                    }
                }
            }
        };

        var ex = await Assert.ThrowsAsync<PurchaseDomainException>(() => poService.CreateAsync(poDto));
        Assert.Equal("CONCURRENT_CONFLICT", ex.ErrorCode);
    }

    [Fact]
    public async Task CancelPurchaseOrder_ShouldReleaseAllocationsAndRestorePrQuantity()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (prService, poService, prId, prItemId) = await SetupApprovedPrAsync(context, 100, 80);

        var poDto = new CreatePurchaseOrderDto
        {
            SupplierId = 1,
            WarehouseId = 1,
            CreatedBy = 20,
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new()
                {
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityOrdered = 80,
                    UnitCost = 14800000m,
                    Allocations = new List<CreatePrAllocationInputDto>
                    {
                        new()
                        {
                            PrId = prId,
                            PrItemId = prItemId,
                            AllocatedQuantity = 80
                        }
                    }
                }
            }
        };

        var po = await poService.CreateAsync(poDto);
        var prAfterOrder = await prService.GetByIdAsync(prId);
        Assert.Equal(80, prAfterOrder!.Items[0].QuantityOrdered);
        Assert.Equal("FullyOrdered", prAfterOrder.OrderStatus);

        // Hủy PO -> Giải phóng phân bổ
        var cancelledPo = await poService.CancelAsync(po.Id, new CancelPurchaseOrderDto
        {
            CancelledBy = 20,
            Reason = "Nhà cung cấp hết hàng phân phối"
        });

        Assert.Equal("Cancelled", cancelledPo.Status);
        Assert.All(cancelledPo.Allocations, a => Assert.Equal("Released", a.Status));

        // Kiểm tra PR item đã được hoàn lại số lượng về 0 và trạng thái về NotOrdered
        var prAfterCancel = await prService.GetByIdAsync(prId);
        Assert.NotNull(prAfterCancel);
        Assert.Equal(0, prAfterCancel.Items[0].QuantityOrdered);
        Assert.Equal("NotOrdered", prAfterCancel.OrderStatus);
    }
}
