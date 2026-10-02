using System.Text.Json.Serialization;

namespace Purchase.Application.DTOs.Invoices;

public class CreatePurchaseInvoiceDto
{
    [JsonPropertyName("purchase_order_id")]
    public long PurchaseOrderId { get; set; }

    [JsonPropertyName("goods_receipt_id")]
    public long? GoodsReceiptId { get; set; }

    [JsonPropertyName("supplier_id")]
    public long SupplierId { get; set; }

    [JsonPropertyName("supplier_invoice_number")]
    public string SupplierInvoiceNumber { get; set; } = string.Empty;

    [JsonPropertyName("invoice_date")]
    public DateTime? InvoiceDate { get; set; }

    [JsonPropertyName("due_date")]
    public DateTime? DueDate { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("items")]
    public List<CreatePurchaseInvoiceItemDto> Items { get; set; } = new();
}

public class CreatePurchaseInvoiceItemDto
{
    [JsonPropertyName("purchase_order_item_id")]
    public long PurchaseOrderItemId { get; set; }

    [JsonPropertyName("goods_receipt_item_id")]
    public long? GoodsReceiptItemId { get; set; }

    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("product_name")]
    public string ProductName { get; set; } = string.Empty;

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = "Cái";

    [JsonPropertyName("quantity_invoiced")]
    public int QuantityInvoiced { get; set; }

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }

    [JsonPropertyName("tax_rate")]
    public decimal TaxRate { get; set; } = 10.00m;
}

public class PurchaseInvoiceDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("code")]
    public string Code { get; set; } = string.Empty;

    [JsonPropertyName("purchase_order_id")]
    public long PurchaseOrderId { get; set; }

    [JsonPropertyName("goods_receipt_id")]
    public long? GoodsReceiptId { get; set; }

    [JsonPropertyName("supplier_id")]
    public long SupplierId { get; set; }

    [JsonPropertyName("supplier_invoice_number")]
    public string SupplierInvoiceNumber { get; set; } = string.Empty;

    [JsonPropertyName("invoice_date")]
    public DateTime InvoiceDate { get; set; }

    [JsonPropertyName("due_date")]
    public DateTime? DueDate { get; set; }

    [JsonPropertyName("subtotal_amount")]
    public decimal SubtotalAmount { get; set; }

    [JsonPropertyName("tax_amount")]
    public decimal TaxAmount { get; set; }

    [JsonPropertyName("grand_total")]
    public decimal GrandTotal { get; set; }

    [JsonPropertyName("status")]
    public string Status { get; set; } = string.Empty;

    [JsonPropertyName("matching_status")]
    public string MatchingStatus { get; set; } = string.Empty;

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("created_at")]
    public DateTime CreatedAt { get; set; }

    [JsonPropertyName("items")]
    public List<PurchaseInvoiceItemDto> Items { get; set; } = new();
}

public class PurchaseInvoiceItemDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("purchase_order_item_id")]
    public long PurchaseOrderItemId { get; set; }

    [JsonPropertyName("goods_receipt_item_id")]
    public long? GoodsReceiptItemId { get; set; }

    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("product_name")]
    public string ProductName { get; set; } = string.Empty;

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = "Cái";

    [JsonPropertyName("quantity_invoiced")]
    public int QuantityInvoiced { get; set; }

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }

    [JsonPropertyName("tax_rate")]
    public decimal TaxRate { get; set; }

    [JsonPropertyName("subtotal")]
    public decimal Subtotal { get; set; }

    [JsonPropertyName("matching_status")]
    public string MatchingStatus { get; set; } = string.Empty;
}

public class ThreeWayMatchingResultDto
{
    [JsonPropertyName("invoice_id")]
    public long InvoiceId { get; set; }

    [JsonPropertyName("overall_status")]
    public string OverallStatus { get; set; } = string.Empty; // Matched, Discrepancy

    [JsonPropertyName("items_matched_count")]
    public int ItemsMatchedCount { get; set; }

    [JsonPropertyName("items_discrepancy_count")]
    public int ItemsDiscrepancyCount { get; set; }

    [JsonPropertyName("discrepancies")]
    public List<MatchingDiscrepancyDto> Discrepancies { get; set; } = new();
}

public class MatchingDiscrepancyDto
{
    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("product_name")]
    public string ProductName { get; set; } = string.Empty;

    [JsonPropertyName("issue_type")]
    public string IssueType { get; set; } = string.Empty; // QTY_DIFF, PRICE_DIFF, BOTH

    [JsonPropertyName("order_qty")]
    public int OrderQty { get; set; }

    [JsonPropertyName("received_qty")]
    public int ReceivedQty { get; set; }

    [JsonPropertyName("invoiced_qty")]
    public int InvoicedQty { get; set; }

    [JsonPropertyName("order_price")]
    public decimal OrderPrice { get; set; }

    [JsonPropertyName("invoiced_price")]
    public decimal InvoicedPrice { get; set; }
}
