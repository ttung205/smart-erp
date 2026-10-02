using Purchase.Application.DTOs.Requisitions;
using Purchase.Application.Services;
using Purchase.Domain.Exceptions;
using Xunit;

namespace Purchase.Tests;

public class PurchaseRequisitionTests
{
    [Fact]
    public async Task CreateRequisition_WithValidItems_ShouldSucceed()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;
        var service = new PurchaseRequisitionService(context);

        var dto = new CreatePurchaseRequisitionDto
        {
            WarehouseId = 1,
            Priority = "High",
            RequestedBy = 100,
            Notes = "Đề nghị bổ sung Tủ lạnh LG trưng bày",
            Items = new List<CreatePurchaseRequisitionItemDto>
            {
                new()
                {
                    ProductId = 1,
                    Sku = "XQ530-LG",
                    ProductName = "Tủ lạnh LG Inverter 519 lít",
                    Unit = "Cái",
                    QuantityRequested = 10,
                    EstimatedUnitCost = 15500000m
                }
            }
        };

        var result = await service.CreateAsync(dto);

        Assert.NotNull(result);
        Assert.StartsWith("PR-", result.Code);
        Assert.Equal("Draft", result.ApprovalStatus);
        Assert.Equal("NotOrdered", result.OrderStatus);
        Assert.Single(result.Items);
        Assert.Equal(10, result.Items[0].QuantityRequested);
        Assert.Equal(0, result.Items[0].QuantityApproved); // 0 in draft until approved
        Assert.Equal(0, result.Items[0].QuantityOrdered);
    }

    [Fact]
    public async Task SubmitRequisition_WhenDraft_ShouldTransitionToSubmitted()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;
        var service = new PurchaseRequisitionService(context);

        var created = await service.CreateAsync(new CreatePurchaseRequisitionDto
        {
            WarehouseId = 1,
            RequestedBy = 100,
            Items = new List<CreatePurchaseRequisitionItemDto>
            {
                new() { ProductId = 1, Sku = "XQ530-LG", ProductName = "Tủ lạnh LG", QuantityRequested = 5 }
            }
        });

        var submitted = await service.SubmitAsync(created.Id);

        Assert.Equal("Submitted", submitted.ApprovalStatus);
    }

    [Fact]
    public async Task ApproveRequisition_WithAdjustedQuantities_ShouldSetApprovedQuantities()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;
        var service = new PurchaseRequisitionService(context);

        var created = await service.CreateAsync(new CreatePurchaseRequisitionDto
        {
            WarehouseId = 1,
            RequestedBy = 100,
            Items = new List<CreatePurchaseRequisitionItemDto>
            {
                new() { ProductId = 1, Sku = "XQ530-LG", ProductName = "Tủ lạnh LG", QuantityRequested = 100 },
                new() { ProductId = 2, Sku = "GC111-ELE", ProductName = "Tủ lạnh Electrolux", QuantityRequested = 50 }
            }
        });
        await service.SubmitAsync(created.Id);

        var approveDto = new ApprovePurchaseRequisitionDto
        {
            ApprovedBy = 999,
            Items = new List<ApprovePrItemAdjustDto>
            {
                new() { ItemId = created.Items[0].Id, QuantityApproved = 80 },
                new() { ItemId = created.Items[1].Id, QuantityApproved = 40 }
            }
        };

        var approved = await service.ApproveAsync(created.Id, approveDto);

        Assert.Equal("Approved", approved.ApprovalStatus);
        Assert.Equal(80, approved.Items[0].QuantityApproved);
        Assert.Equal(40, approved.Items[1].QuantityApproved);
    }

    [Fact]
    public async Task RejectRequisition_ShouldTransitionToRejected()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;
        var service = new PurchaseRequisitionService(context);

        var created = await service.CreateAsync(new CreatePurchaseRequisitionDto
        {
            WarehouseId = 1,
            RequestedBy = 100,
            Items = new List<CreatePurchaseRequisitionItemDto>
            {
                new() { ProductId = 1, Sku = "XQ530-LG", ProductName = "Tủ lạnh LG", QuantityRequested = 10 }
            }
        });
        await service.SubmitAsync(created.Id);

        var rejected = await service.RejectAsync(created.Id, new RejectPurchaseRequisitionDto
        {
            RejectedBy = 999,
            Reason = "Ngân sách quý này đã hết"
        });

        Assert.Equal("Rejected", rejected.ApprovalStatus);
    }
}
