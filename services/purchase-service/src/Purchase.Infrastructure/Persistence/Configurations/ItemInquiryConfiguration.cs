using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using Purchase.Domain.Entities;

namespace Purchase.Infrastructure.Persistence.Configurations;

public class ItemInquiryRequestConfiguration : IEntityTypeConfiguration<ItemInquiryRequest>
{
    public void Configure(EntityTypeBuilder<ItemInquiryRequest> b)
    {
        b.ToTable("item_inquiry_requests");
        b.HasKey(x => x.Id);
        b.Property(x => x.Code).HasMaxLength(100).IsRequired();
        b.HasIndex(x => x.Code).IsUnique();
        b.Property(x => x.CustomerCode).HasMaxLength(50);
        b.Property(x => x.CustomerName).HasMaxLength(200);
        b.Property(x => x.Status).HasMaxLength(30);
        b.HasMany(x => x.Items).WithOne().HasForeignKey(x => x.RequestId).OnDelete(DeleteBehavior.Cascade);
        b.HasMany(x => x.Attachments).WithOne().HasForeignKey(x => x.RequestId).OnDelete(DeleteBehavior.Cascade);
        b.HasMany(x => x.Inquiries).WithOne().HasForeignKey(x => x.RequestId).OnDelete(DeleteBehavior.Cascade);
    }
}

public class ItemInquiryRequestItemConfiguration : IEntityTypeConfiguration<ItemInquiryRequestItem>
{
    public void Configure(EntityTypeBuilder<ItemInquiryRequestItem> b)
    {
        b.ToTable("item_inquiry_request_items");
        b.HasKey(x => x.Id);
        b.Property(x => x.Quantity).HasPrecision(18, 3);
        b.Property(x => x.GrossPrice).HasPrecision(18, 2);
    }
}

public class ItemInquiryAttachmentConfiguration : IEntityTypeConfiguration<ItemInquiryAttachment>
{
    public void Configure(EntityTypeBuilder<ItemInquiryAttachment> b)
    {
        b.ToTable("item_inquiry_attachments");
        b.HasKey(x => x.Id);
    }
}

public class SupplierInquiryConfiguration : IEntityTypeConfiguration<SupplierInquiry>
{
    public void Configure(EntityTypeBuilder<SupplierInquiry> b)
    {
        b.ToTable("supplier_inquiries");
        b.HasKey(x => x.Id);
        b.HasIndex(x => x.Code).IsUnique();
        b.Property(x => x.Code).HasMaxLength(100);
        b.Property(x => x.Status).HasMaxLength(30);
        b.HasMany(x => x.Items).WithOne().HasForeignKey(x => x.InquiryId).OnDelete(DeleteBehavior.Cascade);
        b.HasMany(x => x.Attachments).WithOne().HasForeignKey(x => x.InquiryId).OnDelete(DeleteBehavior.Cascade);
    }
}

public class SupplierInquiryItemConfiguration : IEntityTypeConfiguration<SupplierInquiryItem>
{
    public void Configure(EntityTypeBuilder<SupplierInquiryItem> b)
    {
        b.ToTable("supplier_inquiry_items");
        b.HasKey(x => x.Id);
        b.Property(x => x.Quantity).HasPrecision(18, 3);
        b.Property(x => x.BuyingPrice).HasPrecision(18, 2);
        b.Property(x => x.Vat).HasPrecision(8, 3);
        b.Property(x => x.Tax).HasPrecision(8, 3);
        b.Property(x => x.PriceIndex).HasPrecision(12, 4);
        b.Property(x => x.GrossPrice).HasPrecision(18, 2);
        b.HasIndex(x => x.RequestItemId);
    }
}

public class SupplierInquiryAttachmentConfiguration : IEntityTypeConfiguration<SupplierInquiryAttachment>
{
    public void Configure(EntityTypeBuilder<SupplierInquiryAttachment> b)
    {
        b.ToTable("supplier_inquiry_attachments");
        b.HasKey(x => x.Id);
    }
}
