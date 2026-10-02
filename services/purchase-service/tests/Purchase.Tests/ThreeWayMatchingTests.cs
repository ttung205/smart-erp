using Purchase.Application.DTOs.GoodsReceipts;
using Purchase.Application.DTOs.Invoices;
using Purchase.Application.DTOs.PurchaseOrders;
using Purchase.Application.Services;
using Purchase.Domain.Exceptions;
using Xunit;

namespace Purchase.Tests;

public class ThreeWayMatchingTests
{
    private async Task<(PurchaseOrderService poService, GoodsReceiptService grService, ThreeWayMatchingService invService, long poId, long poItemId, long grnId, long grnItemId)> SetupPoAndGrnAsync(
        Purchase.Infrastructure.Persistence.PurchaseDbContext context, int qty = 20, decimal unitCost = 14500000m)
    {
        var poService = new PurchaseOrderService(context);
        var grService = new GoodsReceiptService(context);
        var invService = new ThreeWayMatchingService(context);

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
                    QuantityOrdered = qty,
                    UnitCost = unitCost
                }
            }
        });

        await poService.SubmitAsync(po.Id);
        var approvedPo = await poService.ApproveAsync(po.Id, 99);
        var poItemId = approvedPo.Items[0].Id;

        // Nhận hàng đủ
        var grn = await grService.CreateAsync(new CreateGoodsReceiptDto
        {
            PurchaseOrderId = approvedPo.Id,
            WarehouseId = 1,
            ReceivedBy = 101,
            Items = new List<CreateGoodsReceiptItemDto>
            {
                new() { PurchaseOrderItemId = poItemId, ProductId = 1, Sku = "XQ530-LG", QuantityReceived = qty }
            }
        });

        var confirmedGrn = await grService.ConfirmAsync(grn.Id);
        var grnItemId = confirmedGrn.Items[0].Id;

        return (poService, grService, invService, approvedPo.Id, poItemId, confirmedGrn.Id, grnItemId);
    }

    [Fact]
    public async Task MatchingInvoice_WhenPriceAndQuantityMatch_ShouldBeMatched()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (_, _, invService, poId, poItemId, grnId, grnItemId) = await SetupPoAndGrnAsync(context, 20, 14500000m);

        var invoiceDto = new CreatePurchaseInvoiceDto
        {
            PurchaseOrderId = poId,
            GoodsReceiptId = grnId,
            SupplierId = 1,
            SupplierInvoiceNumber = "VAT-2026-0099",
            Items = new List<CreatePurchaseInvoiceItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = poItemId,
                    GoodsReceiptItemId = grnItemId,
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityInvoiced = 20, // Khớp
                    UnitCost = 14500000m // Khớp
                }
            }
        };

        var invoice = await invService.CreateInvoiceAsync(invoiceDto);

        Assert.Equal("Matched", invoice.MatchingStatus);
        Assert.Equal("MATCHED", invoice.Items[0].MatchingStatus);
    }

    [Fact]
    public async Task MatchingInvoice_WhenPriceDiffers_ShouldReportDiscrepancy()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (_, _, invService, poId, poItemId, grnId, grnItemId) = await SetupPoAndGrnAsync(context, 20, 14500000m);

        var invoiceDto = new CreatePurchaseInvoiceDto
        {
            PurchaseOrderId = poId,
            GoodsReceiptId = grnId,
            SupplierId = 1,
            SupplierInvoiceNumber = "VAT-2026-0100",
            Items = new List<CreatePurchaseInvoiceItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = poItemId,
                    GoodsReceiptItemId = grnItemId,
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    QuantityInvoiced = 20,
                    UnitCost = 15000000m // Lệch giá: Đặt 14.5tr, hóa đơn 15tr
                }
            }
        };

        var invoice = await invService.CreateInvoiceAsync(invoiceDto);

        Assert.Equal("Discrepancy", invoice.MatchingStatus);
        Assert.Equal("PRICE_DIFF", invoice.Items[0].MatchingStatus);

        // Kiểm tra MatchInvoiceAsync
        var matchResult = await invService.MatchInvoiceAsync(invoice.Id);
        Assert.Equal("Discrepancy", matchResult.OverallStatus);
        Assert.Equal(1, matchResult.ItemsDiscrepancyCount);
        Assert.Equal("PRICE_DIFF", matchResult.Discrepancies[0].IssueType);
        Assert.Equal(14500000m, matchResult.Discrepancies[0].OrderPrice);
        Assert.Equal(15000000m, matchResult.Discrepancies[0].InvoicedPrice);
    }

    [Fact]
    public async Task PostInvoice_WithDiscrepancy_ShouldBeRejected()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (_, _, invService, poId, poItemId, grnId, grnItemId) = await SetupPoAndGrnAsync(context, 20, 14500000m);

        var invoice = await invService.CreateInvoiceAsync(new CreatePurchaseInvoiceDto
        {
            PurchaseOrderId = poId,
            GoodsReceiptId = grnId,
            SupplierId = 1,
            SupplierInvoiceNumber = "VAT-2026-0101",
            Items = new List<CreatePurchaseInvoiceItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = poItemId,
                    GoodsReceiptItemId = grnItemId,
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    QuantityInvoiced = 25, // Lệch số lượng: nhận 20 mà tính tiền 25
                    UnitCost = 14500000m
                }
            }
        });

        var ex = await Assert.ThrowsAsync<PurchaseDomainException>(() => invService.PostInvoiceAsync(invoice.Id));
        Assert.Equal("MATCHING_DISCREPANCY", ex.ErrorCode);
    }

    [Fact]
    public async Task PostInvoice_WhenMatched_ShouldPostAndCompletePoWhenFullyReceivedAndInvoiced()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var (poService, _, invService, poId, poItemId, grnId, grnItemId) = await SetupPoAndGrnAsync(context, 20, 14500000m);

        var invoice = await invService.CreateInvoiceAsync(new CreatePurchaseInvoiceDto
        {
            PurchaseOrderId = poId,
            GoodsReceiptId = grnId,
            SupplierId = 1,
            SupplierInvoiceNumber = "VAT-2026-0102",
            Items = new List<CreatePurchaseInvoiceItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = poItemId,
                    GoodsReceiptItemId = grnItemId,
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    QuantityInvoiced = 20,
                    UnitCost = 14500000m
                }
            }
        });

        var posted = await invService.PostInvoiceAsync(invoice.Id);

        Assert.Equal("Posted", posted.Status);

        // Kiểm tra PO đã được cập nhật sang Completed (vì đã nhận đủ 20 và đã ghi nhận hóa đơn đủ 20)
        var po = await poService.GetByIdAsync(poId);
        Assert.NotNull(po);
        Assert.Equal(20, po.Items[0].QuantityInvoiced);
        Assert.Equal("Completed", po.Status);
    }
}
