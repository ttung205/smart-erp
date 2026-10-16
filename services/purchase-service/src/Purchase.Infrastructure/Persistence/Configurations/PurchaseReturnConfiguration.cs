using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using Purchase.Domain.Entities;

namespace Purchase.Infrastructure.Persistence.Configurations;

public class PurchaseReturnConfiguration : IEntityTypeConfiguration<PurchaseReturn>
{
    public void Configure(EntityTypeBuilder<PurchaseReturn> builder)
    {
        builder.ToTable("purchase_returns");

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

        builder.Property(x => x.Reason)
            .HasConversion<string>()
            .HasMaxLength(50)
            .IsRequired();

        builder.Property(x => x.RmaNumber)
            .HasMaxLength(100);

        builder.Property(x => x.TotalAmount)
            .HasPrecision(18, 2);

        builder.HasIndex(x => x.PurchaseOrderId);
        builder.HasIndex(x => x.SupplierId);
        builder.HasIndex(x => x.WarehouseId);
        builder.HasIndex(x => x.Status);

        builder.HasMany(x => x.Items)
            .WithOne(x => x.PurchaseReturn)
            .HasForeignKey(x => x.PurchaseReturnId)
            .OnDelete(DeleteBehavior.Cascade);
    }
}

public class PurchaseReturnItemConfiguration : IEntityTypeConfiguration<PurchaseReturnItem>
{
    public void Configure(EntityTypeBuilder<PurchaseReturnItem> builder)
    {
        builder.ToTable("purchase_return_items");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.Sku)
            .IsRequired()
            .HasMaxLength(100);

        builder.Property(x => x.ProductName)
            .IsRequired()
            .HasMaxLength(255);

        builder.Property(x => x.Unit)
            .HasMaxLength(20);

        builder.Property(x => x.UnitCost)
            .HasPrecision(18, 2);

        builder.Property(x => x.ReasonDetail)
            .HasMaxLength(500);

        builder.HasIndex(x => x.PurchaseReturnId);
        builder.HasIndex(x => x.PurchaseOrderItemId);
        builder.HasIndex(x => x.ProductId);
    }
}
