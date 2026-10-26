using Microsoft.EntityFrameworkCore;
using Purchase.Domain.Entities;
using Purchase.Domain.Enums;

namespace Purchase.Infrastructure.Persistence;

public static class DbInitializer
{
    public static async Task SeedAsync(PurchaseDbContext context, bool seedData = true)
    {
        // 1. Tự động khởi tạo Database & các Bảng (cho cả SQLite và MySQL)
        await context.Database.EnsureCreatedAsync();
        await CreateInquiryTablesAsync(context);
        await EnsurePurchaseReturnTablesAsync(context);

        if (!seedData)
        {
            return;
        }

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

    private static async Task CreateInquiryTablesAsync(PurchaseDbContext context)
    {
        // EnsureCreated does not add tables to an existing database.
        var mysql = context.Database.IsMySql();
        var id = mysql ? "BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
        var text = mysql ? "TEXT" : "TEXT";
        var date = mysql ? "DATETIME(6)" : "TEXT";
        var real = mysql ? "DECIMAL(18,4)" : "REAL";
        var tables = new[]
        {
            $"CREATE TABLE IF NOT EXISTS item_inquiry_requests (id {id}, code VARCHAR(100) NOT NULL UNIQUE, customer_id BIGINT NOT NULL, customer_code VARCHAR(50) NOT NULL, customer_name VARCHAR(200) NOT NULL, requested_by BIGINT NOT NULL, assignee_id BIGINT NOT NULL, watcher_id BIGINT NULL, status VARCHAR(30) NOT NULL, due_date {date} NULL, description {text} NULL, created_at {date} NOT NULL, updated_at {date} NOT NULL)",
            $"CREATE TABLE IF NOT EXISTS item_inquiry_request_items (id {id}, request_id BIGINT NOT NULL, sku VARCHAR(100) NULL, designation VARCHAR(255) NOT NULL, item_description {text} NULL, brand VARCHAR(200) NULL, category VARCHAR(200) NULL, com VARCHAR(100) NULL, unit VARCHAR(50) NULL, quantity {real} NOT NULL, lead_time VARCHAR(100) NULL, note {text} NULL, spec {text} NULL, selected_inquiry_item_id BIGINT NULL, gross_price {real} NULL, catalog_gross_price {real} NULL)",
            $"CREATE TABLE IF NOT EXISTS item_inquiry_attachments (id {id}, request_id BIGINT NOT NULL, file_name VARCHAR(255) NOT NULL, content_type VARCHAR(150) NOT NULL, storage_name VARCHAR(100) NOT NULL, size BIGINT NOT NULL, created_at {date} NOT NULL)",
            $"CREATE TABLE IF NOT EXISTS supplier_inquiries (id {id}, request_id BIGINT NOT NULL, code VARCHAR(100) NOT NULL UNIQUE, supplier_id BIGINT NOT NULL, supplier_code VARCHAR(50) NOT NULL, supplier_name VARCHAR(200) NOT NULL, supplier_email VARCHAR(255) NOT NULL, created_by BIGINT NOT NULL, status VARCHAR(30) NOT NULL, email_job_id VARCHAR(100) NULL, sent_at {date} NULL, completed_at {date} NULL, created_at {date} NOT NULL, updated_at {date} NOT NULL)",
            $"CREATE TABLE IF NOT EXISTS supplier_inquiry_items (id {id}, inquiry_id BIGINT NOT NULL, request_item_id BIGINT NOT NULL, sku VARCHAR(100) NULL, designation VARCHAR(255) NOT NULL, item_description {text} NULL, brand_id BIGINT NOT NULL, brand_name VARCHAR(200) NOT NULL, category VARCHAR(200) NULL, com VARCHAR(100) NULL, unit VARCHAR(50) NULL, quantity {real} NOT NULL, lead_time VARCHAR(100) NULL, note {text} NULL, spec {text} NULL, buying_price {real} NOT NULL, vat {real} NOT NULL, tax {real} NOT NULL, price_index {real} NULL, gross_price {real} NULL)",
            $"CREATE TABLE IF NOT EXISTS supplier_inquiry_attachments (id {id}, inquiry_id BIGINT NOT NULL, request_attachment_id BIGINT NOT NULL)"
        };
        foreach (var sql in tables) await context.Database.ExecuteSqlRawAsync(sql);

        // Cột thêm sau cho bảng đã tồn tại
        var addedColumns = new (string Table, string Column, string Definition)[]
        {
            ("item_inquiry_request_items", "catalog_gross_price", $"{real} NULL"),
            ("purchase_order_items", "origin", "VARCHAR(100) NULL DEFAULT 'Việt Nam'"),
            ("purchase_requisition_items", "origin", "VARCHAR(100) NULL DEFAULT 'Việt Nam'")
        };
        foreach (var (table, column, definition) in addedColumns)
        {
            // Kiểm tra trước để không sinh log lỗi "duplicate column" mỗi lần khởi động
            var exists = mysql
                ? await context.Database.SqlQueryRaw<int>(
                    "SELECT COUNT(*) AS Value FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = {0} AND column_name = {1}",
                    table, column).SingleAsync() > 0
                : await context.Database.SqlQueryRaw<int>(
                    "SELECT COUNT(*) AS Value FROM pragma_table_info({0}) WHERE name = {1}",
                    table, column).SingleAsync() > 0;
            // The identifiers and definitions come only from the static allow-list above.
#pragma warning disable EF1002
            if (!exists) await context.Database.ExecuteSqlRawAsync($"ALTER TABLE {table} ADD COLUMN {column} {definition}");
#pragma warning restore EF1002
        }
    }

    private static async Task EnsurePurchaseReturnTablesAsync(PurchaseDbContext context)
    {
        var isSqlite = context.Database.ProviderName?.Contains("Sqlite", StringComparison.OrdinalIgnoreCase) == true;

        if (isSqlite)
        {
            await context.Database.ExecuteSqlRawAsync(@"
                CREATE TABLE IF NOT EXISTS purchase_returns (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL,
                    purchase_order_id INTEGER NOT NULL,
                    goods_receipt_id INTEGER NULL,
                    supplier_id INTEGER NOT NULL,
                    warehouse_id INTEGER NOT NULL,
                    rma_number TEXT NULL,
                    status TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    notes TEXT NULL,
                    total_amount TEXT NOT NULL DEFAULT '0',
                    created_by INTEGER NOT NULL DEFAULT 1,
                    approved_by INTEGER NULL,
                    approved_at TEXT NULL,
                    dispatched_at TEXT NULL,
                    version INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );

                CREATE UNIQUE INDEX IF NOT EXISTS IX_purchase_returns_code ON purchase_returns (code);
                CREATE INDEX IF NOT EXISTS IX_purchase_returns_purchase_order_id ON purchase_returns (purchase_order_id);
                CREATE INDEX IF NOT EXISTS IX_purchase_returns_supplier_id ON purchase_returns (supplier_id);
                CREATE INDEX IF NOT EXISTS IX_purchase_returns_warehouse_id ON purchase_returns (warehouse_id);
                CREATE INDEX IF NOT EXISTS IX_purchase_returns_status ON purchase_returns (status);

                CREATE TABLE IF NOT EXISTS purchase_return_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    purchase_return_id INTEGER NOT NULL,
                    purchase_order_item_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    sku TEXT NOT NULL,
                    product_name TEXT NOT NULL,
                    unit TEXT NULL,
                    quantity_returned INTEGER NOT NULL,
                    unit_cost TEXT NOT NULL DEFAULT '0',
                    reason_detail TEXT NULL,
                    FOREIGN KEY (purchase_return_id) REFERENCES purchase_returns (id) ON DELETE CASCADE
                );

                CREATE INDEX IF NOT EXISTS IX_purchase_return_items_purchase_return_id ON purchase_return_items (purchase_return_id);
                CREATE INDEX IF NOT EXISTS IX_purchase_return_items_purchase_order_item_id ON purchase_return_items (purchase_order_item_id);
                CREATE INDEX IF NOT EXISTS IX_purchase_return_items_product_id ON purchase_return_items (product_id);
            ");
        }
        else
        {
            await context.Database.ExecuteSqlRawAsync(@"
                CREATE TABLE IF NOT EXISTS purchase_returns (
                    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    purchase_order_id BIGINT NOT NULL,
                    goods_receipt_id BIGINT NULL,
                    supplier_id BIGINT NOT NULL,
                    warehouse_id BIGINT NOT NULL,
                    rma_number VARCHAR(100) NULL,
                    status VARCHAR(30) NOT NULL,
                    reason VARCHAR(50) NOT NULL,
                    notes TEXT NULL,
                    total_amount DECIMAL(18, 2) NOT NULL DEFAULT 0,
                    created_by BIGINT NOT NULL DEFAULT 1,
                    approved_by BIGINT NULL,
                    approved_at DATETIME NULL,
                    dispatched_at DATETIME NULL,
                    version INT NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    INDEX IX_purchase_returns_purchase_order_id (purchase_order_id),
                    INDEX IX_purchase_returns_supplier_id (supplier_id),
                    INDEX IX_purchase_returns_warehouse_id (warehouse_id),
                    INDEX IX_purchase_returns_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS purchase_return_items (
                    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    purchase_return_id BIGINT NOT NULL,
                    purchase_order_item_id BIGINT NOT NULL,
                    product_id BIGINT NOT NULL,
                    sku VARCHAR(100) NOT NULL,
                    product_name VARCHAR(255) NOT NULL,
                    unit VARCHAR(20) NULL,
                    quantity_returned INT NOT NULL,
                    unit_cost DECIMAL(18, 2) NOT NULL DEFAULT 0,
                    reason_detail VARCHAR(500) NULL,
                    INDEX IX_purchase_return_items_purchase_return_id (purchase_return_id),
                    INDEX IX_purchase_return_items_purchase_order_item_id (purchase_order_item_id),
                    INDEX IX_purchase_return_items_product_id (product_id),
                    CONSTRAINT FK_purchase_return_items_returns FOREIGN KEY (purchase_return_id) REFERENCES purchase_returns(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
    }
}
