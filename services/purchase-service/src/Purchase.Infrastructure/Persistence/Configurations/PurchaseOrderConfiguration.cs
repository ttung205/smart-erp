using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using Purchase.Domain.Entities;

namespace Purchase.Infrastructure.Persistence.Configurations;

public class PurchaseOrderConfiguration : IEntityTypeConfiguration<PurchaseOrder>
{
    public void Configure(EntityTypeBuilder<PurchaseOrder> builder)
    {
        builder.ToTable("purchase_orders");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.Code)
            .IsRequired()
            .HasMaxLength(50);

        builder.HasIndex(x => x.Code)
            .IsUnique();

        builder.Property(x => x.Status)
            .HasConversion<string>()
            .HasMaxLength(30)
            .IsRequired();

        builder.Property(x => x.Currency)
            .HasMaxLength(10)
            .HasDefaultValue("VND");

        builder.Property(x => x.SubtotalAmount).HasPrecision(15, 2);
        builder.Property(x => x.TaxAmount).HasPrecision(15, 2);
        builder.Property(x => x.DiscountAmount).HasPrecision(15, 2);
        builder.Property(x => x.GrandTotal).HasPrecision(15, 2);

        builder.HasIndex(x => x.SupplierId);
        builder.HasIndex(x => x.WarehouseId);
        builder.HasIndex(x => x.Status);
        builder.HasIndex(x => x.OrderDate);

        builder.HasMany(x => x.Items)
            .WithOne(x => x.PurchaseOrder)
            .HasForeignKey(x => x.PurchaseOrderId)
            .OnDelete(DeleteBehavior.Cascade);

        builder.HasMany(x => x.Allocations)
            .WithOne(x => x.PurchaseOrder)
            .HasForeignKey(x => x.PurchaseOrderId)
            .OnDelete(DeleteBehavior.Cascade);
    }
}

public class PurchaseOrderItemConfiguration : IEntityTypeConfiguration<PurchaseOrderItem>
{
    public void Configure(EntityTypeBuilder<PurchaseOrderItem> builder)
    {
        builder.ToTable("purchase_order_items");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.Sku)
            .IsRequired()
            .HasMaxLength(100);

        builder.Property(x => x.ProductName)
            .IsRequired()
            .HasMaxLength(255);

        builder.Property(x => x.Unit)
            .IsRequired()
            .HasMaxLength(50)
            .HasDefaultValue("Cái");

        builder.Property(x => x.UnitCost).HasPrecision(15, 2);
        builder.Property(x => x.TaxRate).HasPrecision(5, 2);
        builder.Property(x => x.DiscountPercent).HasPrecision(5, 2);
        builder.Property(x => x.Subtotal).HasPrecision(15, 2);

        builder.HasIndex(x => x.ProductId);

        builder.HasMany(x => x.Allocations)
            .WithOne(x => x.PurchaseOrderItem)
            .HasForeignKey(x => x.PurchaseOrderItemId)
            .OnDelete(DeleteBehavior.Cascade);
    }
}

public class PurchaseOrderPrAllocationConfiguration : IEntityTypeConfiguration<PurchaseOrderPrAllocation>
{
    public void Configure(EntityTypeBuilder<PurchaseOrderPrAllocation> builder)
    {
        builder.ToTable("purchase_order_pr_allocations");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.Status)
            .HasConversion<string>()
            .HasMaxLength(30)
            .IsRequired();

        builder.HasIndex(x => new { x.PurchaseRequisitionItemId, x.Status });
    }
}
