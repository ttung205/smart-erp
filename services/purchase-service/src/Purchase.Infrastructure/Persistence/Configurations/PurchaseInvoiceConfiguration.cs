using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using Purchase.Domain.Entities;

namespace Purchase.Infrastructure.Persistence.Configurations;

public class PurchaseInvoiceConfiguration : IEntityTypeConfiguration<PurchaseInvoice>
{
    public void Configure(EntityTypeBuilder<PurchaseInvoice> builder)
    {
        builder.ToTable("purchase_invoices");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.Code)
            .IsRequired()
            .HasMaxLength(50);

        builder.HasIndex(x => x.Code)
            .IsUnique();

        builder.Property(x => x.SupplierInvoiceNumber)
            .IsRequired()
            .HasMaxLength(100);

        builder.Property(x => x.Status)
            .HasConversion<string>()
            .HasMaxLength(30)
            .IsRequired();

        builder.Property(x => x.MatchingStatus)
            .HasConversion<string>()
            .HasMaxLength(30)
            .IsRequired();

        builder.Property(x => x.SubtotalAmount).HasPrecision(15, 2);
        builder.Property(x => x.TaxAmount).HasPrecision(15, 2);
        builder.Property(x => x.GrandTotal).HasPrecision(15, 2);

        builder.HasIndex(x => x.PurchaseOrderId);
        builder.HasIndex(x => x.SupplierId);
        builder.HasIndex(x => x.SupplierInvoiceNumber);
        builder.HasIndex(x => x.Status);

        builder.HasMany(x => x.Items)
            .WithOne(x => x.PurchaseInvoice)
            .HasForeignKey(x => x.PurchaseInvoiceId)
            .OnDelete(DeleteBehavior.Cascade);
    }
}

public class PurchaseInvoiceItemConfiguration : IEntityTypeConfiguration<PurchaseInvoiceItem>
{
    public void Configure(EntityTypeBuilder<PurchaseInvoiceItem> builder)
    {
        builder.ToTable("purchase_invoice_items");

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
        builder.Property(x => x.Subtotal).HasPrecision(15, 2);
        builder.Property(x => x.MatchingStatus).HasMaxLength(30).HasDefaultValue("MATCHED");

        builder.HasIndex(x => x.ProductId);
        builder.HasIndex(x => x.PurchaseOrderItemId);
    }
}
