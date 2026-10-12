using System.Text.Json.Serialization;

namespace Purchase.Application.DTOs.PurchaseOrders;

public class CreatePurchaseOrderDto
{
    [JsonPropertyName("supplier_id")]
    public long SupplierId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("order_date")]
    public DateTime? OrderDate { get; set; }

    [JsonPropertyName("expected_delivery_date")]
    public DateTime? ExpectedDeliveryDate { get; set; }

    [JsonPropertyName("currency")]
    public string Currency { get; set; } = "VND";

    [JsonPropertyName("payment_terms")]
    public string? PaymentTerms { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("created_by")]
    public long CreatedBy { get; set; }

    [JsonPropertyName("items")]
    public List<CreatePurchaseOrderItemDto> Items { get; set; } = new();
}

public class CreatePurchaseOrderItemDto
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("product_name")]
    public string ProductName { get; set; } = string.Empty;

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = "Cái";

    [JsonPropertyName("origin")]
    public string? Origin { get; set; } = "Việt Nam";

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }

    [JsonPropertyName("quantity_ordered")]
    public int QuantityOrdered { get; set; }

    [JsonPropertyName("tax_rate")]
    public decimal TaxRate { get; set; } = 10.00m;

    [JsonPropertyName("discount_percent")]
    public decimal DiscountPercent { get; set; } = 0.00m;

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    /// <summary>
    /// Danh sách gom nhu cầu từ các dòng PR (PR Allocations)
    /// Nếu lập đơn PO từ các PR đã duyệt
    /// </summary>
    [JsonPropertyName("allocations")]
    public List<CreatePrAllocationInputDto>? Allocations { get; set; }
}

public class CreatePrAllocationInputDto
{
    [JsonPropertyName("pr_id")]
    public long PrId { get; set; }

    [JsonPropertyName("pr_item_id")]
    public long PrItemId { get; set; }

    [JsonPropertyName("allocated_quantity")]
    public int AllocatedQuantity { get; set; }
}

public class PurchaseOrderDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("code")]
    public string Code { get; set; } = string.Empty;

    [JsonPropertyName("supplier_id")]
    public long SupplierId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("status")]
    public string Status { get; set; } = string.Empty;

    [JsonPropertyName("order_date")]
    public DateTime OrderDate { get; set; }

    [JsonPropertyName("expected_delivery_date")]
    public DateTime? ExpectedDeliveryDate { get; set; }

    [JsonPropertyName("currency")]
    public string Currency { get; set; } = "VND";

    [JsonPropertyName("subtotal_amount")]
    public decimal SubtotalAmount { get; set; }

    [JsonPropertyName("tax_amount")]
    public decimal TaxAmount { get; set; }

    [JsonPropertyName("discount_amount")]
    public decimal DiscountAmount { get; set; }

    [JsonPropertyName("grand_total")]
    public decimal GrandTotal { get; set; }

    [JsonPropertyName("payment_terms")]
    public string? PaymentTerms { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("created_by")]
    public long CreatedBy { get; set; }

    [JsonPropertyName("approved_by")]
    public long? ApprovedBy { get; set; }

    [JsonPropertyName("approved_at")]
    public DateTime? ApprovedAt { get; set; }

    [JsonPropertyName("created_at")]
    public DateTime CreatedAt { get; set; }

    [JsonPropertyName("items")]
    public List<PurchaseOrderItemDto> Items { get; set; } = new();

    [JsonPropertyName("allocations")]
    public List<PurchaseOrderPrAllocationDto> Allocations { get; set; } = new();
}

public class PurchaseOrderItemDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("product_name")]
    public string ProductName { get; set; } = string.Empty;

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = "Cái";

    [JsonPropertyName("origin")]
    public string? Origin { get; set; }

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }

    [JsonPropertyName("quantity_ordered")]
    public int QuantityOrdered { get; set; }

    [JsonPropertyName("quantity_received")]
    public int QuantityReceived { get; set; }

    [JsonPropertyName("quantity_invoiced")]
    public int QuantityInvoiced { get; set; }

    [JsonPropertyName("tax_rate")]
    public decimal TaxRate { get; set; }

    [JsonPropertyName("discount_percent")]
    public decimal DiscountPercent { get; set; }

    [JsonPropertyName("subtotal")]
    public decimal Subtotal { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }
}

public class PurchaseOrderPrAllocationDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("purchase_order_id")]
    public long PurchaseOrderId { get; set; }

    [JsonPropertyName("purchase_order_item_id")]
    public long PurchaseOrderItemId { get; set; }

    [JsonPropertyName("purchase_requisition_id")]
    public long PurchaseRequisitionId { get; set; }

    [JsonPropertyName("purchase_requisition_item_id")]
    public long PurchaseRequisitionItemId { get; set; }

    [JsonPropertyName("allocated_quantity")]
    public int AllocatedQuantity { get; set; }

    [JsonPropertyName("status")]
    public string Status { get; set; } = string.Empty;
}

public class CancelPurchaseOrderDto
{
    [JsonPropertyName("cancelled_by")]
    public long CancelledBy { get; set; }

    [JsonPropertyName("reason")]
    public string? Reason { get; set; }
}
