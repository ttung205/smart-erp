using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using Purchase.Domain.Entities;

namespace Purchase.Infrastructure.Persistence.Configurations;

public class PurchaseRequisitionConfiguration : IEntityTypeConfiguration<PurchaseRequisition>
{
    public void Configure(EntityTypeBuilder<PurchaseRequisition> builder)
    {
        builder.ToTable("purchase_requisitions");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.Code)
            .IsRequired()
            .HasMaxLength(50);

        builder.HasIndex(x => x.Code)
            .IsUnique();

        builder.Property(x => x.ApprovalStatus)
            .HasConversion<string>()
            .HasMaxLength(30)
            .IsRequired();

        builder.Property(x => x.OrderStatus)
            .HasConversion<string>()
            .HasMaxLength(30)
            .IsRequired();

        builder.Property(x => x.Priority)
            .HasConversion<string>()
            .HasMaxLength(20)
            .IsRequired();

        builder.HasIndex(x => x.WarehouseId);
        builder.HasIndex(x => x.ApprovalStatus);
        builder.HasIndex(x => x.OrderStatus);
        builder.HasIndex(x => x.ExpectedNeededDate);

        builder.HasMany(x => x.Items)
            .WithOne(x => x.PurchaseRequisition)
            .HasForeignKey(x => x.PurchaseRequisitionId)
            .OnDelete(DeleteBehavior.Cascade);
    }
}

public class PurchaseRequisitionItemConfiguration : IEntityTypeConfiguration<PurchaseRequisitionItem>
{
    public void Configure(EntityTypeBuilder<PurchaseRequisitionItem> builder)
    {
        builder.ToTable("purchase_requisition_items");

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

        builder.Property(x => x.Origin)
            .HasMaxLength(100)
            .HasDefaultValue("Việt Nam");

        builder.Property(x => x.EstimatedUnitCost)
            .HasPrecision(15, 2);

        builder.HasIndex(x => x.ProductId);
    }
}
