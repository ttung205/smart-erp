namespace Purchase.Domain.Entities;

public class ItemInquiryRequest
{
    public long Id { get; set; }
    public string Code { get; set; } = "";
    public long CustomerId { get; set; }
    public string CustomerCode { get; set; } = "";
    public string CustomerName { get; set; } = "";
    public long RequestedBy { get; set; }
    public long AssigneeId { get; set; }
    public long? WatcherId { get; set; }
    public string Status { get; set; } = "DRAFT";
    public DateTime? DueDate { get; set; }
    public string? Description { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;
    public List<ItemInquiryRequestItem> Items { get; set; } = [];
    public List<ItemInquiryAttachment> Attachments { get; set; } = [];
    public List<SupplierInquiry> Inquiries { get; set; } = [];
}

public class ItemInquiryRequestItem
{
    public long Id { get; set; }
    public long RequestId { get; set; }
    public string? Sku { get; set; }
    public string Designation { get; set; } = "";
    public string? ItemDescription { get; set; }
    public string? Brand { get; set; }
    public string? Category { get; set; }
    public string? Com { get; set; }
    public string? Unit { get; set; }
    public decimal Quantity { get; set; }
    public string? LeadTime { get; set; }
    public string? Note { get; set; }
    public string? Spec { get; set; }
    public long? SelectedInquiryItemId { get; set; }
    public decimal? GrossPrice { get; set; }
    // Gross price của SKU trong catalog tại thời điểm lập/sửa yêu cầu
    public decimal? CatalogGrossPrice { get; set; }
}

public class ItemInquiryAttachment
{
    public long Id { get; set; }
    public long RequestId { get; set; }
    public string FileName { get; set; } = "";
    public string ContentType { get; set; } = "application/octet-stream";
    public string StorageName { get; set; } = "";
    public long Size { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
}

public class SupplierInquiry
{
    public long Id { get; set; }
    public long RequestId { get; set; }
    public string Code { get; set; } = "";
    public long SupplierId { get; set; }
    public string SupplierCode { get; set; } = "";
    public string SupplierName { get; set; } = "";
    public string SupplierEmail { get; set; } = "";
    public long CreatedBy { get; set; }
    public string Status { get; set; } = "DRAFT";
    public string? EmailJobId { get; set; }
    public DateTime? SentAt { get; set; }
    public DateTime? CompletedAt { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;
    public List<SupplierInquiryItem> Items { get; set; } = [];
    public List<SupplierInquiryAttachment> Attachments { get; set; } = [];
}

public class SupplierInquiryItem
{
    public long Id { get; set; }
    public long InquiryId { get; set; }
    public long RequestItemId { get; set; }
    public string? Sku { get; set; }
    public string Designation { get; set; } = "";
    public string? ItemDescription { get; set; }
    public long BrandId { get; set; }
    public string BrandName { get; set; } = "";
    public string? Category { get; set; }
    public string? Com { get; set; }
    public string? Unit { get; set; }
    public decimal Quantity { get; set; }
    public string? LeadTime { get; set; }
    public string? Note { get; set; }
    public string? Spec { get; set; }
    public decimal BuyingPrice { get; set; }
    public decimal Vat { get; set; }
    public decimal Tax { get; set; }
    public decimal? PriceIndex { get; set; }
    public decimal? GrossPrice { get; set; }
}

public class SupplierInquiryAttachment
{
    public long Id { get; set; }
    public long InquiryId { get; set; }
    public long RequestAttachmentId { get; set; }
}
