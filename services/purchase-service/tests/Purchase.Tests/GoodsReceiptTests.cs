using Purchase.Application.DTOs.GoodsReceipts;
using Purchase.Application.DTOs.PurchaseOrders;
using Purchase.Application.Services;
using Purchase.Domain.Exceptions;
using Xunit;

namespace Purchase.Tests;

public class GoodsReceiptTests
{
    private async Task<(PurchaseOrderService poService, GoodsReceiptService grService, long poId, long poItemId)> SetupApprovedPoAsync(
        Purchase.Infrastructure.Persistence.PurchaseDbContext context, int orderedQty = 50)
    {
        var poService = new PurchaseOrderService(context);
        var grService = new GoodsReceiptService(context);

        var po = await poService.CreateAsync(new CreatePurchaseOrderDto
        {
            SupplierId = 1,
            WarehouseId = 1,
            CreatedBy = 10,
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new()
                {
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityOrdered = orderedQty,
                    UnitCost = 14500000m
                }
            }
        });

        await poService.SubmitAsync(po.Id);
        var approvedPo = await poService.ApproveAsync(po.Id, 99);

        return (poService, grService, approvedPo.Id, approvedPo.Items[0].Id);
    }

    [Fact]
    public async Task CreateGoodsReceipt_WithValidQuantity_ShouldSucceed()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (_, grService, poId, poItemId) = await SetupApprovedPoAsync(context, 50);

        var grnDto = new CreateGoodsReceiptDto
        {
            PurchaseOrderId = poId,
            WarehouseId = 1,
            ReceivedBy = 101,
            DeliveryNoteNumber = "DN-2026-001",
            Items = new List<CreateGoodsReceiptItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = poItemId,
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityReceived = 30,
                    QuantityRejected = 0,
                    UnitCost = 14500000m
                }
            }
        };

        var grn = await grService.CreateAsync(grnDto);

        Assert.NotNull(grn);
        Assert.StartsWith("GR-", grn.Code);
        Assert.Equal("Draft", grn.Status);
        Assert.Single(grn.Items);
        Assert.Equal(30, grn.Items[0].QuantityReceived);
    }

    [Fact]
    public async Task CreateGoodsReceipt_ExceedingOrderedQuantity_ShouldThrowOverReceiptException()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (_, grService, poId, poItemId) = await SetupApprovedPoAsync(context, 50);

        var grnDto = new CreateGoodsReceiptDto
        {
            PurchaseOrderId = poId,
            WarehouseId = 1,
            ReceivedBy = 101,
            Items = new List<CreateGoodsReceiptItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = poItemId,
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityReceived = 60 // Vượt quá 50
                }
            }
        };

        var ex = await Assert.ThrowsAsync<PurchaseDomainException>(() => grService.CreateAsync(grnDto));
        Assert.Equal("OVER_RECEIPT_NOT_ALLOWED", ex.ErrorCode);
    }

    [Fact]
    public async Task ConfirmGoodsReceipt_ShouldUpdatePoReceivedQuantityAndStatus()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (poService, grService, poId, poItemId) = await SetupApprovedPoAsync(context, 50);

        // Nhận đợt 1: 30 cái -> PO PartiallyReceived
        var grn1 = await grService.CreateAsync(new CreateGoodsReceiptDto
        {
            PurchaseOrderId = poId,
            WarehouseId = 1,
            ReceivedBy = 101,
            Items = new List<CreateGoodsReceiptItemDto>
            {
                new() { PurchaseOrderItemId = poItemId, ProductId = 1, Sku = "XQ530-LG", QuantityReceived = 30 }
            }
        });

        var confirmed1 = await grService.ConfirmAsync(grn1.Id);
        Assert.Equal("Confirmed", confirmed1.Status);

        var poAfterGrn1 = await poService.GetByIdAsync(poId);
        Assert.NotNull(poAfterGrn1);
        Assert.Equal(30, poAfterGrn1.Items[0].QuantityReceived);
        Assert.Equal("PartiallyReceived", poAfterGrn1.Status);

        // Nhận đợt 2: 20 cái còn lại -> PO Received
        var grn2 = await grService.CreateAsync(new CreateGoodsReceiptDto
        {
            PurchaseOrderId = poId,
            WarehouseId = 1,
            ReceivedBy = 101,
            Items = new List<CreateGoodsReceiptItemDto>
            {
                new() { PurchaseOrderItemId = poItemId, ProductId = 1, Sku = "XQ530-LG", QuantityReceived = 20 }
            }
        });

        var confirmed2 = await grService.ConfirmAsync(grn2.Id);
        Assert.Equal("Confirmed", confirmed2.Status);

        var poAfterGrn2 = await poService.GetByIdAsync(poId);
        Assert.NotNull(poAfterGrn2);
        Assert.Equal(50, poAfterGrn2.Items[0].QuantityReceived);
        Assert.Equal("Received", poAfterGrn2.Status);
    }
}
