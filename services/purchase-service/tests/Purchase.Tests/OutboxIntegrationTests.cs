using System.Text.Json;
using Microsoft.EntityFrameworkCore;
using Purchase.Application.Common.Events;
using Purchase.Application.DTOs.GoodsReceipts;
using Purchase.Application.DTOs.PurchaseOrders;
using Purchase.Application.Services;
using Purchase.Infrastructure.Services;
using Xunit;

namespace Purchase.Tests;

public class OutboxIntegrationTests
{
    [Fact]
    public async Task ConfirmGoodsReceipt_ShouldPublishGoodsReceivedOutboxEvent_MatchingInventorySchema()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var outboxService = new OutboxService(context);
        var poService = new PurchaseOrderService(context, outboxService);
        var grService = new GoodsReceiptService(context, outboxService);

        // 1. Tạo và duyệt PO
        var po = await poService.CreateAsync(new CreatePurchaseOrderDto
        {
            SupplierId = 1,
            WarehouseId = 2,
            CreatedBy = 10,
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new()
                {
                    ProductId = 5,
                    Sku = "GC111-ELE",
                    ProductName = "Tủ lạnh Electrolux Inverter 341 lít",
                    QuantityOrdered = 25,
                    UnitCost = 11200000m
                }
            }
        });
        await poService.SubmitAsync(po.Id);
        await poService.ApproveAsync(po.Id, 99);

        // 2. Tạo và xác nhận GRN
        var grn = await grService.CreateAsync(new CreateGoodsReceiptDto
        {
            PurchaseOrderId = po.Id,
            WarehouseId = 2,
            ReceivedBy = 105,
            Items = new List<CreateGoodsReceiptItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = po.Items[0].Id,
                    ProductId = 5,
                    Sku = "GC111-ELE",
                    QuantityReceived = 25
                }
            }
        });

        await grService.ConfirmAsync(grn.Id);

        // 3. Kiểm tra bảng outbox_events
        var outboxEvents = await context.OutboxEvents
            .OrderBy(e => e.Id)
            .ToListAsync();

        // Ít nhất có 2 sự kiện: PurchaseOrderApproved và GoodsReceived
        Assert.Contains(outboxEvents, e => e.RoutingKey == "purchase.order.approved");
        var grnEvent = outboxEvents.FirstOrDefault(e => e.RoutingKey == "purchase.goods.received");
        Assert.NotNull(grnEvent);
        Assert.Equal("PENDING", grnEvent.Status);
        Assert.Equal("GoodsReceipt", grnEvent.AggregateType);
        Assert.Equal(grn.Code, grnEvent.AggregateId);

        // 4. Giải mã payload để kiểm tra cấu trúc CloudEvents và dữ liệu inventory-service nhận
        var cloudEvent = JsonSerializer.Deserialize<CloudEventEnvelope<GoodsReceivedPayload>>(grnEvent.Payload);
        Assert.NotNull(cloudEvent);
        Assert.Equal("GoodsReceived", cloudEvent.EventType);
        Assert.Equal("purchase-service", cloudEvent.Producer);

        var payload = cloudEvent.Payload;
        Assert.NotNull(payload);
        Assert.Equal(grn.Code, payload.ReceiptId);
        Assert.Equal(po.Code, payload.PoId);
        Assert.Equal(2, payload.WarehouseId);
        Assert.Equal(105, payload.ReceivedBy);
        Assert.Single(payload.Items);
        Assert.Equal(5, payload.Items[0].ProductId);
        Assert.Equal(25, payload.Items[0].QuantityReceived);
    }

    [Fact]
    public async Task CancelPurchaseOrder_ShouldPublishPurchaseOrderCancelledOutboxEvent()
    {
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var outboxService = new OutboxService(context);
        var poService = new PurchaseOrderService(context, outboxService);

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

        await poService.CancelAsync(po.Id, new CancelPurchaseOrderDto
        {
            CancelledBy = 10,
            Reason = "NCC dừng sản xuất dòng model này"
        });

        var cancelEvent = await context.OutboxEvents
            .FirstOrDefaultAsync(e => e.RoutingKey == "purchase.order.cancelled");

        Assert.NotNull(cancelEvent);
        Assert.Equal("PENDING", cancelEvent.Status);
        Assert.Equal("PurchaseOrderCancelled", cancelEvent.EventType);

        var cloudEvent = JsonSerializer.Deserialize<CloudEventEnvelope<PurchaseOrderCancelledPayload>>(cancelEvent.Payload);
        Assert.NotNull(cloudEvent);
        Assert.Equal("NCC dừng sản xuất dòng model này", cloudEvent.Payload.Reason);
    }
}
