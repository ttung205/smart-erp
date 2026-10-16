using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Purchase.Domain.Entities;

namespace Purchase.Application.Interfaces;

public interface IPurchaseDbContext
{
    DbSet<PurchaseRequisition> PurchaseRequisitions { get; }
    DbSet<PurchaseRequisitionItem> PurchaseRequisitionItems { get; }
    DbSet<PurchaseOrder> PurchaseOrders { get; }
    DbSet<PurchaseOrderItem> PurchaseOrderItems { get; }
    DbSet<PurchaseOrderPrAllocation> PurchaseOrderPrAllocations { get; }
    DbSet<GoodsReceipt> GoodsReceipts { get; }
    DbSet<GoodsReceiptItem> GoodsReceiptItems { get; }
    DbSet<PurchaseInvoice> PurchaseInvoices { get; }
    DbSet<PurchaseInvoiceItem> PurchaseInvoiceItems { get; }
    DbSet<PurchaseReturn> PurchaseReturns { get; }
    DbSet<PurchaseReturnItem> PurchaseReturnItems { get; }
    DbSet<OutboxEvent> OutboxEvents { get; }
    DbSet<ProcessedEvent> ProcessedEvents { get; }

    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
    Task<IDbContextTransaction> BeginTransactionAsync(CancellationToken cancellationToken = default);
    Task<int> ExecuteSqlInterpolatedAsync(FormattableString sql, CancellationToken cancellationToken = default);
}
