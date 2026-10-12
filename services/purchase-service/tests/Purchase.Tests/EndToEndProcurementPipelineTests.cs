using System.Text.Json;
using Microsoft.EntityFrameworkCore;
using Purchase.Application.Common.Events;
using Purchase.Application.DTOs.GoodsReceipts;
using Purchase.Application.DTOs.Invoices;
using Purchase.Application.DTOs.PurchaseOrders;
using Purchase.Application.DTOs.Requisitions;
using Purchase.Application.Services;
using Purchase.Domain.Exceptions;
using Purchase.Infrastructure.Services;
using Xunit;

namespace Purchase.Tests;

/// <summary>
/// Pha 7: Kiểm Thử Tích Hợp End-to-End & Đối Soát SCM
/// Thực thi trọn vẹn kịch bản chuỗi cung ứng mua hàng 6 bước / 8 nghiệp vụ khép kín:
/// 1. PR xin mua (100 cái)
/// 2. Duyệt PR (80 cái)
/// 3. Atomic Allocation Concurrency Guard: 2 nhân viên tranh chấp -> Chỉ 1 thành công
/// 4. Quản lý duyệt PO -> Sinh Outbox 'PurchaseOrderApproved'
/// 5. Thử nghiệm Hủy PO -> Giải phóng phân bổ, phục hồi PR về NOT_ORDERED
/// 6. Lập lại PO & NCC giao đợt 1 (50 cái) -> Thủ kho lập GRN
/// 7. Xác nhận GRN -> Sinh Outbox 'GoodsReceived' khớp Schema inventory-service
/// 8. Lập Hóa đơn VAT -> 3-Way Matching (PO - GRN - Invoice) khớp & Ghi sổ (POSTED)
/// </summary>
public class EndToEndProcurementPipelineTests
{
    [Fact]
    public async Task CompleteProcurementLifecycle_EndToEnd_ShouldExecuteSuccessfully()
    {
        // ARRANGE & SETUP
        var (context, connection) = TestDbContextFactory.CreateInMemoryDbContext();
        using var _ = connection;

        var outboxService = new OutboxService(context);
        var prService = new PurchaseRequisitionService(context);
        var poService = new PurchaseOrderService(context, outboxService);
        var grService = new GoodsReceiptService(context, outboxService);
        var invService = new ThreeWayMatchingService(context);

        const long warehouseId = 1; // Kho Hà Nội
        const long supplierId = 1;  // NCC Long Sơn
        const long productId = 1;
        const string sku = "SKU-001";
        const string productName = "Tủ lạnh LG Inverter 519 lít";
        const decimal estimatedPrice = 15000000m;
        const decimal actualPoPrice = 14500000m;

        // =========================================================================
        // BƯỚC 1: Thủ kho thấy tồn kho thấp, lập PR xin mua 100 cái
        // =========================================================================
        var pr = await prService.CreateAsync(new CreatePurchaseRequisitionDto
        {
            WarehouseId = warehouseId,
            Priority = "High",
            RequestedBy = 101, // Thủ kho
            Notes = "Tồn kho SKU-001 tại Kho Hà Nội còn 20 (MinStock = 30), xin mua bổ sung 100 cái",
            Items = new List<CreatePurchaseRequisitionItemDto>
            {
                new()
                {
                    ProductId = productId,
                    Sku = sku,
                    ProductName = productName,
                    Unit = "Cái",
                    QuantityRequested = 100,
                    EstimatedUnitCost = estimatedPrice
                }
            }
        });

        Assert.NotNull(pr);
        Assert.StartsWith("PR-", pr.Code);
        Assert.Equal("Draft", pr.ApprovalStatus);
        Assert.Equal("NotOrdered", pr.OrderStatus);
        Assert.Equal(100, pr.Items[0].QuantityRequested);

        // Gửi đề nghị duyệt (SUBMIT)
        var submittedPr = await prService.SubmitAsync(pr.Id);
        Assert.Equal("Submitted", submittedPr.ApprovalStatus);

        // =========================================================================
        // BƯỚC 2: Quản lý phê duyệt điều chỉnh 80 cái (quantity_approved = 80)
        // =========================================================================
        var prItemId = submittedPr.Items[0].Id;
        var approvedPr = await prService.ApproveAsync(pr.Id, new ApprovePurchaseRequisitionDto
        {
            ApprovedBy = 99, // Quản lý thu mua
            Items = new List<ApprovePrItemAdjustDto>
            {
                new() { ItemId = prItemId, QuantityApproved = 80 }
            }
        });

        Assert.Equal("Approved", approvedPr.ApprovalStatus);
        Assert.Equal(80, approvedPr.Items[0].QuantityApproved);
        Assert.Equal(0, approvedPr.Items[0].QuantityOrdered);
        Assert.Equal("NotOrdered", approvedPr.OrderStatus);

        // =========================================================================
        // BƯỚC 3: Kiểm tra Atomic Concurrency Guard: 2 nhân viên cùng bấm tạo PO cho 80 cái
        // -> Nhân viên 1 thành công, Nhân viên 2 bị chặn tranh chấp (CONCURRENT_CONFLICT)
        // =========================================================================
        var poDtoBuyer1 = new CreatePurchaseOrderDto
        {
            SupplierId = supplierId,
            WarehouseId = warehouseId,
            CreatedBy = 201, // Nhân viên Thu Mua 1
            Notes = "PO gom nhu cầu từ PR đã duyệt bởi Buyer 1",
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new()
                {
                    ProductId = productId,
                    Sku = sku,
                    ProductName = productName,
                    QuantityOrdered = 80,
                    UnitCost = actualPoPrice,
                    Allocations = new List<CreatePrAllocationInputDto>
                    {
                        new()
                        {
                            PrId = pr.Id,
                            PrItemId = prItemId,
                            AllocatedQuantity = 80
                        }
                    }
                }
            }
        };

        var po1 = await poService.CreateAsync(poDtoBuyer1);
        Assert.NotNull(po1);
        Assert.StartsWith("PO-", po1.Code);
        Assert.Equal("Việt Nam", po1.Items[0].Origin);
        Assert.Single(po1.Allocations);
        Assert.Equal(80, po1.Allocations[0].AllocatedQuantity);

        // Kiểm tra PR item đã được tăng quantity_ordered lên 80 và order_status = FullyOrdered
        var prAfterPo1 = await prService.GetByIdAsync(pr.Id);
        Assert.NotNull(prAfterPo1);
        Assert.Equal(80, prAfterPo1.Items[0].QuantityOrdered);
        Assert.Equal("FullyOrdered", prAfterPo1.OrderStatus);

        // Nhân viên 2 bấm đồng thời tạo PO cho cùng 80 cái
        var poDtoBuyer2 = new CreatePurchaseOrderDto
        {
            SupplierId = supplierId,
            WarehouseId = warehouseId,
            CreatedBy = 202, // Nhân viên Thu Mua 2
            Notes = "PO gom nhu cầu bởi Buyer 2 (tranh chấp)",
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new()
                {
                    ProductId = productId,
                    Sku = sku,
                    ProductName = productName,
                    QuantityOrdered = 80,
                    UnitCost = actualPoPrice,
                    Allocations = new List<CreatePrAllocationInputDto>
                    {
                        new()
                        {
                            PrId = pr.Id,
                            PrItemId = prItemId,
                            AllocatedQuantity = 80
                        }
                    }
                }
            }
        };

        var conflictEx = await Assert.ThrowsAsync<PurchaseDomainException>(() => poService.CreateAsync(poDtoBuyer2));
        Assert.Equal("CONCURRENT_CONFLICT", conflictEx.ErrorCode);

        // =========================================================================
        // BƯỚC 4: Quản lý duyệt đơn PO1 -> Outbox ghi nhận sự kiện PurchaseOrderApproved
        // =========================================================================
        await poService.SubmitAsync(po1.Id);
        var approvedPo1 = await poService.ApproveAsync(po1.Id, 99);
        Assert.Equal("Approved", approvedPo1.Status);

        // Xác minh Outbox event PurchaseOrderApproved
        var poApprovedOutbox = await context.OutboxEvents
            .FirstOrDefaultAsync(e => e.EventType == "PurchaseOrderApproved" && e.AggregateId == approvedPo1.Code);
        Assert.NotNull(poApprovedOutbox);
        Assert.Equal("purchase.order.approved", poApprovedOutbox.RoutingKey);
        Assert.Equal("PENDING", poApprovedOutbox.Status);

        // =========================================================================
        // BƯỚC 5: Thử nghiệm Hủy PO1 -> Phân bổ được hoàn trả, PR quay về NOT_ORDERED
        // =========================================================================
        var cancelledPo1 = await poService.CancelAsync(po1.Id, new CancelPurchaseOrderDto
        {
            CancelledBy = 99,
            Reason = "Thử nghiệm hủy đơn để kiểm tra giải phóng hạn mức phân bổ"
        });

        Assert.Equal("Cancelled", cancelledPo1.Status);
        Assert.All(cancelledPo1.Allocations, a => Assert.Equal("Released", a.Status));

        // Kiểm tra PR đã được hoàn trả số lượng về 0 và order_status quay về NotOrdered
        var prRestored = await prService.GetByIdAsync(pr.Id);
        Assert.NotNull(prRestored);
        Assert.Equal(0, prRestored.Items[0].QuantityOrdered);
        Assert.Equal("NotOrdered", prRestored.OrderStatus);

        // =========================================================================
        // BƯỚC 6: Tạo lại PO và NCC giao đợt 1 là 50 cái -> Thủ kho lập GRN
        // =========================================================================
        var poDtoNew = new CreatePurchaseOrderDto
        {
            SupplierId = supplierId,
            WarehouseId = warehouseId,
            CreatedBy = 201,
            Notes = "PO lập lại chính thức sau khi hủy đơn thử nghiệm",
            Items = new List<CreatePurchaseOrderItemDto>
            {
                new()
                {
                    ProductId = productId,
                    Sku = sku,
                    ProductName = productName,
                    QuantityOrdered = 80,
                    UnitCost = actualPoPrice,
                    Allocations = new List<CreatePrAllocationInputDto>
                    {
                        new()
                        {
                            PrId = pr.Id,
                            PrItemId = prItemId,
                            AllocatedQuantity = 80
                        }
                    }
                }
            }
        };

        var po2 = await poService.CreateAsync(poDtoNew);
        await poService.SubmitAsync(po2.Id);
        var approvedPo2 = await poService.ApproveAsync(po2.Id, 99);
        var po2ItemId = approvedPo2.Items[0].Id;

        // NCC Long Sơn giao đợt 1: 50 cái
        var grnDto = new CreateGoodsReceiptDto
        {
            PurchaseOrderId = approvedPo2.Id,
            WarehouseId = warehouseId,
            ReceivedBy = 101, // Thủ kho
            DeliveryNoteNumber = "DN-LONGSON-001",
            Notes = "Nhập kho đợt 1: 50 cái tủ lạnh LG",
            Items = new List<CreateGoodsReceiptItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = po2ItemId,
                    ProductId = productId,
                    Sku = sku,
                    ProductName = productName,
                    QuantityReceived = 50,
                    QuantityRejected = 0,
                    UnitCost = actualPoPrice
                }
            }
        };

        var grn = await grService.CreateAsync(grnDto);
        Assert.NotNull(grn);
        Assert.StartsWith("GR-", grn.Code);
        Assert.Equal("Draft", grn.Status);
        Assert.Equal(50, grn.Items[0].QuantityReceived);

        // =========================================================================
        // BƯỚC 7: Xác nhận GRN -> Outbox bắn 'GoodsReceived' (khớp 100% schema inventory-service)
        // =========================================================================
        var confirmedGrn = await grService.ConfirmAsync(grn.Id);
        Assert.Equal("Confirmed", confirmedGrn.Status);

        // Kiểm tra PO được cập nhật sang PartiallyReceived với số lượng nhận là 50
        var poAfterGrn = await poService.GetByIdAsync(approvedPo2.Id);
        Assert.NotNull(poAfterGrn);
        Assert.Equal(50, poAfterGrn.Items[0].QuantityReceived);
        Assert.Equal("PartiallyReceived", poAfterGrn.Status);

        // Kiểm tra Outbox Event 'GoodsReceived'
        var grnOutbox = await context.OutboxEvents
            .FirstOrDefaultAsync(e => e.EventType == "GoodsReceived" && e.AggregateId == confirmedGrn.Code);
        Assert.NotNull(grnOutbox);
        Assert.Equal("purchase.goods.received", grnOutbox.RoutingKey);
        Assert.Equal("PENDING", grnOutbox.Status);

        // Parse Envelope và xác minh tính toàn vẹn payload
        var cloudEvent = JsonSerializer.Deserialize<CloudEventEnvelope<GoodsReceivedPayload>>(grnOutbox.Payload);
        Assert.NotNull(cloudEvent);
        Assert.Equal("GoodsReceived", cloudEvent.EventType);
        Assert.Equal("purchase-service", cloudEvent.Producer);
        Assert.NotNull(cloudEvent.Payload);
        Assert.Equal(confirmedGrn.Code, cloudEvent.Payload.ReceiptId);
        Assert.Equal(approvedPo2.Code, cloudEvent.Payload.PoId);
        Assert.Equal(warehouseId, cloudEvent.Payload.WarehouseId);
        Assert.Single(cloudEvent.Payload.Items);
        Assert.Equal(productId, cloudEvent.Payload.Items[0].ProductId);
        Assert.Equal(50, cloudEvent.Payload.Items[0].QuantityReceived);

        // =========================================================================
        // BƯỚC 8: Kế toán nhận hóa đơn VAT cho 50 cái -> 3-Way Matching khớp & Ghi sổ (POSTED)
        // =========================================================================
        var grnItemId = confirmedGrn.Items[0].Id;
        var invoiceDto = new CreatePurchaseInvoiceDto
        {
            PurchaseOrderId = approvedPo2.Id,
            GoodsReceiptId = confirmedGrn.Id,
            SupplierId = supplierId,
            SupplierInvoiceNumber = "VAT-LONGSON-8899",
            InvoiceDate = DateTime.UtcNow,
            Notes = "Hóa đơn VAT tài chính cho 50 cái nhập kho đợt 1",
            Items = new List<CreatePurchaseInvoiceItemDto>
            {
                new()
                {
                    PurchaseOrderItemId = po2ItemId,
                    GoodsReceiptItemId = grnItemId,
                    ProductId = productId,
                    Sku = sku,
                    ProductName = productName,
                    QuantityInvoiced = 50,    // Khớp 100% với GRN thực nhận (50)
                    UnitCost = actualPoPrice  // Khớp 100% với PO đơn giá (14.500.000đ)
                }
            }
        };

        var invoice = await invService.CreateInvoiceAsync(invoiceDto);
        Assert.NotNull(invoice);
        Assert.StartsWith("PINV-", invoice.Code);
        Assert.Equal("Matched", invoice.MatchingStatus);
        Assert.Single(invoice.Items);
        Assert.Equal("MATCHED", invoice.Items[0].MatchingStatus);

        // Kế toán Ghi sổ hóa đơn (Post) để xác nhận công nợ phải trả
        var postedInvoice = await invService.PostInvoiceAsync(invoice.Id);
        Assert.Equal("Posted", postedInvoice.Status);

        // Kiểm tra PO đã được cập nhật số lượng ghi sổ hóa đơn
        var finalPo = await poService.GetByIdAsync(approvedPo2.Id);
        Assert.NotNull(finalPo);
        Assert.Equal(50, finalPo.Items[0].QuantityInvoiced);
    }
}
