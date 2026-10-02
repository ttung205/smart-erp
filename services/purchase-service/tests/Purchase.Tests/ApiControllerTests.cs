using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Purchase.Api.Controllers;
using Purchase.Application.Common.Models;
using Purchase.Application.DTOs.GoodsReceipts;
using Purchase.Application.DTOs.Invoices;
using Purchase.Application.DTOs.PurchaseOrders;
using Purchase.Application.DTOs.Requisitions;
using Purchase.Application.Services;
using Purchase.Infrastructure.Services;
using Xunit;

namespace Purchase.Tests;

public class ApiControllerTests
{
    private static DefaultHttpContext CreateMockHttpContext()
    {
        var context = new DefaultHttpContext();
        context.Request.Headers["X-Correlation-ID"] = "test-corr-id-12345";
        return context;
    }

    [Fact]
    public async Task PurchaseRequisitionsController_Flow_ShouldReturnValidEnvelopes()
    {
        var (dbContext, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var prService = new PurchaseRequisitionService(dbContext);
        var controller = new PurchaseRequisitionsController(prService)
        {
            ControllerContext = new ControllerContext { HttpContext = CreateMockHttpContext() }
        };

        // 1. Create PR
        var createDto = new CreatePurchaseRequisitionDto
        {
            WarehouseId = 1,
            RequestedBy = 100,
            Notes = "Cần bổ sung tủ lạnh",
            Items = new List<CreatePurchaseRequisitionItemDto>
            {
                new() { ProductId = 1, Sku = "XQ530-LG", ProductName = "Tủ lạnh LG", QuantityRequested = 10 }
            }
        };

        var createResult = await controller.Create(createDto) as CreatedAtActionResult;
        Assert.NotNull(createResult);
        var createEnv = createResult.Value as ApiEnvelope<PurchaseRequisitionDto>;
        Assert.NotNull(createEnv);
        Assert.True(createEnv.Success);
        Assert.Equal("Draft", createEnv.Data!.ApprovalStatus);
        var prId = createEnv.Data.Id;

        // 2. Submit PR
        var submitResult = await controller.Submit(prId) as OkObjectResult;
        Assert.NotNull(submitResult);
        var submitEnv = submitResult.Value as ApiEnvelope<PurchaseRequisitionDto>;
        Assert.True(submitEnv!.Success);
        Assert.Equal("Submitted", submitEnv.Data!.ApprovalStatus);

        // 3. Approve PR
        var approveResult = await controller.Approve(prId, new ApprovePurchaseRequisitionDto
        {
            ApprovedBy = 999,
            Items = new List<ApprovePrItemAdjustDto>
            {
                new() { ItemId = submitEnv.Data.Items[0].Id, QuantityApproved = 8 }
            }
        }) as OkObjectResult;
        Assert.NotNull(approveResult);
        var approveEnv = approveResult.Value as ApiEnvelope<PurchaseRequisitionDto>;
        Assert.Equal("Approved", approveEnv!.Data!.ApprovalStatus);
        Assert.Equal(8, approveEnv.Data.Items[0].QuantityApproved);

        // 4. Get Available for order
        var availResult = await controller.GetAvailableForOrder(1) as OkObjectResult;
        Assert.NotNull(availResult);
        var availEnv = availResult.Value as ApiEnvelope<List<ApprovedPrItemForOrderingDto>>;
        Assert.Single(availEnv!.Data!);
        Assert.Equal(8, availEnv.Data![0].AvailableToOrder);

        // 5. Get List
        var listResult = await controller.GetList(1, "Approved", null) as OkObjectResult;
        Assert.NotNull(listResult);
        var listEnv = listResult.Value as ApiEnvelope<List<PurchaseRequisitionDto>>;
        Assert.Single(listEnv!.Data!);
    }

    [Fact]
    public async Task PurchaseOrdersController_Flow_ShouldReturnValidEnvelopes()
    {
        var (dbContext, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var outboxService = new OutboxService(dbContext);
        var poService = new PurchaseOrderService(dbContext, outboxService);
        var controller = new PurchaseOrdersController(poService)
        {
            ControllerContext = new ControllerContext { HttpContext = CreateMockHttpContext() }
        };

        // 1. Create PO
        var createDto = new CreatePurchaseOrderDto
        {
            SupplierId = 1,
            WarehouseId = 1,
            CreatedBy = 10,
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new() { ProductId = 1, Sku = "XQ530-LG", ProductName = "Tủ lạnh LG", QuantityOrdered = 20, UnitCost = 14000000m }
            }
        };

        var createResult = await controller.Create(createDto) as CreatedAtActionResult;
        Assert.NotNull(createResult);
        var createEnv = createResult.Value as ApiEnvelope<PurchaseOrderDto>;
        Assert.True(createEnv!.Success);
        var poId = createEnv.Data!.Id;

        // 2. Submit PO
        var submitResult = await controller.Submit(poId) as OkObjectResult;
        Assert.NotNull(submitResult);
        var submitEnv = submitResult.Value as ApiEnvelope<PurchaseOrderDto>;
        Assert.Equal("PendingApproval", submitEnv!.Data!.Status);

        // 3. Approve PO
        var approveResult = await controller.Approve(poId, new ApprovePurchaseOrderRequest { ApprovedBy = 99 }) as OkObjectResult;
        Assert.NotNull(approveResult);
        var approveEnv = approveResult.Value as ApiEnvelope<PurchaseOrderDto>;
        Assert.Equal("Approved", approveEnv!.Data!.Status);

        // 4. Get By ID
        var getResult = await controller.GetById(poId) as OkObjectResult;
        Assert.NotNull(getResult);
        var getEnv = getResult.Value as ApiEnvelope<PurchaseOrderDto>;
        Assert.Equal(poId, getEnv!.Data!.Id);
    }

    [Fact]
    public async Task GoodsReceiptsController_Flow_ShouldReturnValidEnvelopes()
    {
        var (dbContext, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var outboxService = new OutboxService(dbContext);
        var poService = new PurchaseOrderService(dbContext, outboxService);
        var grService = new GoodsReceiptService(dbContext, outboxService);

        var po = await poService.CreateAsync(new CreatePurchaseOrderDto
        {
            SupplierId = 1,
            WarehouseId = 1,
            CreatedBy = 10,
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new() { ProductId = 1, Sku = "XQ530-LG", ProductName = "Tủ lạnh LG", QuantityOrdered = 10, UnitCost = 15000000m }
            }
        });
        await poService.SubmitAsync(po.Id);
        await poService.ApproveAsync(po.Id, 99);

        var controller = new GoodsReceiptsController(grService)
        {
            ControllerContext = new ControllerContext { HttpContext = CreateMockHttpContext() }
        };

        // Create GRN
        var grnDto = new CreateGoodsReceiptDto
        {
            PurchaseOrderId = po.Id,
            WarehouseId = 1,
            ReceivedBy = 105,
            Items = new List<CreateGoodsReceiptItemDto>
            {
                new() { PurchaseOrderItemId = po.Items[0].Id, ProductId = 1, Sku = "XQ530-LG", QuantityReceived = 10 }
            }
        };

        var createResult = await controller.Create(grnDto) as CreatedAtActionResult;
        Assert.NotNull(createResult);
        var createEnv = createResult.Value as ApiEnvelope<GoodsReceiptDto>;
        Assert.True(createEnv!.Success);
        var grnId = createEnv.Data!.Id;

        // Confirm GRN
        var confirmResult = await controller.Confirm(grnId) as OkObjectResult;
        Assert.NotNull(confirmResult);
        var confirmEnv = confirmResult.Value as ApiEnvelope<GoodsReceiptDto>;
        Assert.Equal("Confirmed", confirmEnv!.Data!.Status);
    }

    [Fact]
    public async Task PurchaseInvoicesController_Flow_ShouldReturnValidEnvelopes()
    {
        var (dbContext, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var poService = new PurchaseOrderService(dbContext);
        var matchingService = new ThreeWayMatchingService(dbContext);

        var po = await poService.CreateAsync(new CreatePurchaseOrderDto
        {
            SupplierId = 1,
            WarehouseId = 1,
            CreatedBy = 10,
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new() { ProductId = 1, Sku = "XQ530-LG", ProductName = "Tủ lạnh LG", QuantityOrdered = 5, UnitCost = 15000000m }
            }
        });
        await poService.SubmitAsync(po.Id);
        await poService.ApproveAsync(po.Id, 99);

        var controller = new PurchaseInvoicesController(matchingService)
        {
            ControllerContext = new ControllerContext { HttpContext = CreateMockHttpContext() }
        };

        // Create Invoice
        var invoiceDto = new CreatePurchaseInvoiceDto
        {
            PurchaseOrderId = po.Id,
            SupplierId = 1,
            SupplierInvoiceNumber = "INV-2026-999",
            Items = new List<CreatePurchaseInvoiceItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = po.Items[0].Id,
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    QuantityInvoiced = 5,
                    UnitCost = 15000000m
                }
            }
        };

        var createResult = await controller.Create(invoiceDto) as CreatedAtActionResult;
        Assert.NotNull(createResult);
        var createEnv = createResult.Value as ApiEnvelope<PurchaseInvoiceDto>;
        Assert.True(createEnv!.Success);
        var invoiceId = createEnv.Data!.Id;

        // Match
        var matchResult = await controller.Match(invoiceId) as OkObjectResult;
        Assert.NotNull(matchResult);
        var matchEnv = matchResult.Value as ApiEnvelope<ThreeWayMatchingResultDto>;
        Assert.Equal("Matched", matchEnv!.Data!.OverallStatus);

        // Post
        var postResult = await controller.Post(invoiceId) as OkObjectResult;
        Assert.NotNull(postResult);
        var postEnv = postResult.Value as ApiEnvelope<PurchaseInvoiceDto>;
        Assert.Equal("Posted", postEnv!.Data!.Status);
    }

    [Fact]
    public void HealthCheckController_ShouldReturnHealthy()
    {
        var controller = new HealthCheckController();
        var result = controller.Get() as OkObjectResult;
        Assert.NotNull(result);
        var env = result.Value as ApiEnvelope<object>;
        Assert.NotNull(env);
        Assert.True(env.Success);
    }
}
