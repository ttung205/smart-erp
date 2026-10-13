using Microsoft.EntityFrameworkCore;
using Purchase.Domain.Entities;
using Purchase.Domain.Enums;

namespace Purchase.Infrastructure.Persistence;

public static class DbInitializer
{
    public static async Task SeedAsync(PurchaseDbContext context)
    {
        // 1. Tự động khởi tạo Database & các Bảng (cho cả SQLite và MySQL)
        await context.Database.EnsureCreatedAsync();

        // 2. Nạp dữ liệu mẫu cho Đề nghị mua hàng (PR) - Khớp 100% với danh mục thiết bị điện tử từ Catalog
        if (!await context.PurchaseRequisitions.AnyAsync())
        {
            var pr1 = new PurchaseRequisition
            {
                Code = "PR-202610-0001",
                WarehouseId = 1, // Kho Tổng Miền Bắc (Hà Nội)
                RequestedBy = 1, // Thủ kho
                RequestDate = DateTime.UtcNow.AddDays(-2),
                ExpectedNeededDate = DateTime.UtcNow.AddDays(5),
                Priority = RequisitionPriority.High,
                Reason = "Tồn kho thực tế dưới ngưỡng an toàn (Min-Stock = 30 cái) trước cao điểm khuyến mãi",
                Notes = "Đề nghị nhập gấp thiết bị điện tử điện máy bổ sung kho Hà Nội",
                ApprovalStatus = RequisitionApprovalStatus.Approved,
                OrderStatus = RequisitionOrderStatus.PartiallyOrdered,
                ApprovedBy = 2, // Quản lý
                ApprovedAt = DateTime.UtcNow.AddDays(-1),
                CreatedAt = DateTime.UtcNow.AddDays(-2),
                UpdatedAt = DateTime.UtcNow.AddDays(-1),
                Items = new List<PurchaseRequisitionItem>
                {
                    new()
                    {
                        ProductId = 1,
                        Sku = "XQ530-LG",
                        ProductName = "Tủ lạnh LG Ngăn đá dưới 350 lít",
                        Unit = "Cái",
                        Origin = "Hàn Quốc",
                        QuantityRequested = 100,
                        QuantityApproved = 80, // Quản lý duyệt 80
                        QuantityOrdered = 50,  // Đã phân bổ lên đơn PO 50
                        EstimatedUnitCost = 8500000m,
                        Notes = "Hàng chính hãng LG Electronics"
                    },
                    new()
                    {
                        ProductId = 2,
                        Sku = "GC111-ELE",
                        ProductName = "Tủ lạnh Electrolux Inverter 256 lít",
                        Unit = "Cái",
                        Origin = "Thái Lan",
                        QuantityRequested = 50,
                        QuantityApproved = 50, // Duyệt đủ 50
                        QuantityOrdered = 0,   // Chưa lên đơn PO
                        EstimatedUnitCost = 5800000m,
                        Notes = "Dòng Inverter tiết kiệm điện"
                    }
                }
            };

            var pr2 = new PurchaseRequisition
            {
                Code = "PR-202610-0002",
                WarehouseId = 2, // Kho Trung Tâm Miền Nam (TP.HCM)
                RequestedBy = 3,
                RequestDate = DateTime.UtcNow,
                ExpectedNeededDate = DateTime.UtcNow.AddDays(7),
                Priority = RequisitionPriority.Medium,
                Reason = "Bổ sung hàng điện tử bán lẻ theo kế hoạch quý 4",
                ApprovalStatus = RequisitionApprovalStatus.Submitted,
                OrderStatus = RequisitionOrderStatus.NotOrdered,
                CreatedAt = DateTime.UtcNow,
                UpdatedAt = DateTime.UtcNow,
                Items = new List<PurchaseRequisitionItem>
                {
                    new()
                    {
                        ProductId = 3,
                        Sku = "ZN178-TOS",
                        ProductName = "Tủ lạnh Toshiba Ngăn đá trên 400 lít",
                        Unit = "Cái",
                        Origin = "Nhật Bản",
                        QuantityRequested = 60,
                        QuantityApproved = 0,
                        QuantityOrdered = 0,
                        EstimatedUnitCost = 10500000m,
                        Notes = "Chờ duyệt ngân sách"
                    }
                }
            };

            await context.PurchaseRequisitions.AddRangeAsync(pr1, pr2);
            await context.SaveChangesAsync();

            // 3. Nạp dữ liệu mẫu Đơn mua hàng (PO) tạo từ PR-202610-0001
            var prItem = pr1.Items.First(i => i.ProductId == 1);
            var po1 = new PurchaseOrder
            {
                Code = "PO-202610-0001",
                SupplierId = 1, // Nhà phân phối LG Electronics
                WarehouseId = 1, // Kho Tổng Miền Bắc (Hà Nội)
                Status = PurchaseOrderStatus.Approved,
                OrderDate = DateTime.UtcNow.AddDays(-1),
                ExpectedDeliveryDate = DateTime.UtcNow.AddDays(3),
                Currency = "VND",
                PaymentTerms = "Net 30 ngày kể từ ngày nhận hàng",
                Notes = "Đơn mua gom 50 chiếc tủ lạnh LG từ đề nghị PR-202610-0001",
                CreatedBy = 2,
                ApprovedBy = 1,
                ApprovedAt = DateTime.UtcNow.AddHours(-12),
                CreatedAt = DateTime.UtcNow.AddDays(-1),
                UpdatedAt = DateTime.UtcNow.AddHours(-12),
                Items = new List<PurchaseOrderItem>
                {
                    new()
                    {
                        ProductId = 1,
                        Sku = "XQ530-LG",
                        ProductName = "Tủ lạnh LG Ngăn đá dưới 350 lít",
                        Unit = "Cái",
                        Origin = "Hàn Quốc",
                        UnitCost = 8500000m,
                        QuantityOrdered = 50,
                        QuantityReceived = 0,
                        QuantityInvoiced = 0,
                        TaxRate = 10.00m,
                        DiscountPercent = 3.00m,
                        Subtotal = 412250000m // 50 * 8,500,000 * 0.97
                    }
                }
            };

            po1.RecalculateTotals();
            await context.PurchaseOrders.AddAsync(po1);
            await context.SaveChangesAsync();

            // 4. Tạo bản ghi phân bổ nhu cầu (PR-to-PO Allocation)
            var poItem = po1.Items.First();
            var allocation = new PurchaseOrderPrAllocation
            {
                PurchaseOrderId = po1.Id,
                PurchaseOrderItemId = poItem.Id,
                PurchaseRequisitionId = pr1.Id,
                PurchaseRequisitionItemId = prItem.Id,
                AllocatedQuantity = 50,
                Status = PrAllocationStatus.Active,
                CreatedAt = DateTime.UtcNow.AddDays(-1),
                UpdatedAt = DateTime.UtcNow.AddDays(-1)
            };

            await context.PurchaseOrderPrAllocations.AddAsync(allocation);
            await context.SaveChangesAsync();
        }
    }
}
