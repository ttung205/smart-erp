using Purchase.Domain.Enums;
using Purchase.Domain.Exceptions;

namespace Purchase.Domain.Entities;

/// <summary>
/// Đại diện cho Chứng từ mua hàng / Hóa đơn tài chính từ NCC (Purchase Invoice)
/// </summary>
public class PurchaseInvoice
{
    public long Id { get; set; }
    public string Code { get; set; } = string.Empty; // PINV-YYYYMM-XXXX
    public long PurchaseOrderId { get; set; }
    public long? GoodsReceiptId { get; set; }
    public long SupplierId { get; set; }
    public string SupplierInvoiceNumber { get; set; } = string.Empty; // Số hóa đơn VAT gốc từ NCC
    public DateTime InvoiceDate { get; set; } = DateTime.UtcNow;
    public DateTime? DueDate { get; set; }
    public decimal SubtotalAmount { get; set; } = 0m;
    public decimal TaxAmount { get; set; } = 0m;
    public decimal GrandTotal { get; set; } = 0m;
    public PurchaseInvoiceStatus Status { get; set; } = PurchaseInvoiceStatus.Draft;
    public ThreeWayMatchingStatus MatchingStatus { get; set; } = ThreeWayMatchingStatus.Unmatched;
    public string? Notes { get; set; }

    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    // Navigation properties
    public PurchaseOrder? PurchaseOrder { get; set; }
    public GoodsReceipt? GoodsReceipt { get; set; }
    public ICollection<PurchaseInvoiceItem> Items { get; set; } = new List<PurchaseInvoiceItem>();

    public PurchaseInvoice()
    {
    }

    public void RecalculateTotals()
    {
        SubtotalAmount = Items.Sum(i => i.Subtotal);
        TaxAmount = Items.Sum(i => i.Subtotal * (i.TaxRate / 100m));
        GrandTotal = SubtotalAmount + TaxAmount;
        UpdatedAt = DateTime.UtcNow;
    }

    public void Post()
    {
        if (Status != PurchaseInvoiceStatus.Draft)
            throw new PurchaseDomainException("INVALID_STATE", "Chỉ có thể ghi sổ hóa đơn ở trạng thái Dự thảo (Draft).");

        Status = PurchaseInvoiceStatus.Posted;
        UpdatedAt = DateTime.UtcNow;
    }
}
