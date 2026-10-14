using System.Text.Json.Serialization;

namespace Purchase.Application.DTOs.PurchaseReturns;

public class CreatePurchaseReturnDto
{
    [JsonPropertyName("purchase_order_id")]
    public long PurchaseOrderId { get; set; }

    [JsonPropertyName("goods_receipt_id")]
    public long? GoodsReceiptId { get; set; }

    [JsonPropertyName("supplier_id")]
    public long SupplierId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("rma_number")]
    public string? RmaNumber { get; set; }

    [JsonPropertyName("reason")]
    public string Reason { get; set; } = "Defective";

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("created_by")]
    public long CreatedBy { get; set; } = 1;

    [JsonPropertyName("items")]
    public List<CreatePurchaseReturnItemDto> Items { get; set; } = new();
}

public class CreatePurchaseReturnItemDto
{
    [JsonPropertyName("purchase_order_item_id")]
    public long PurchaseOrderItemId { get; set; }

    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("product_name")]
    public string ProductName { get; set; } = string.Empty;

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = "Cái";

    [JsonPropertyName("quantity_returned")]
    public int QuantityReturned { get; set; }

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }

    [JsonPropertyName("reason_detail")]
    public string? ReasonDetail { get; set; }
}

public class PurchaseReturnDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("code")]
    public string Code { get; set; } = string.Empty;

    [JsonPropertyName("purchase_order_id")]
    public long PurchaseOrderId { get; set; }

    [JsonPropertyName("po_code")]
    public string PoCode { get; set; } = string.Empty;

    [JsonPropertyName("goods_receipt_id")]
    public long? GoodsReceiptId { get; set; }

    [JsonPropertyName("goods_receipt_code")]
    public string? GoodsReceiptCode { get; set; }

    [JsonPropertyName("supplier_id")]
    public long SupplierId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("rma_number")]
    public string? RmaNumber { get; set; }

    [JsonPropertyName("status")]
    public string Status { get; set; } = string.Empty;

    [JsonPropertyName("reason")]
    public string Reason { get; set; } = string.Empty;

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("total_amount")]
    public decimal TotalAmount { get; set; }

    [JsonPropertyName("created_by")]
    public long CreatedBy { get; set; }

    [JsonPropertyName("approved_by")]
    public long? ApprovedBy { get; set; }

    [JsonPropertyName("approved_at")]
    public DateTime? ApprovedAt { get; set; }

    [JsonPropertyName("dispatched_at")]
    public DateTime? DispatchedAt { get; set; }

    [JsonPropertyName("created_at")]
    public DateTime CreatedAt { get; set; }

    [JsonPropertyName("items")]
    public List<PurchaseReturnItemDto> Items { get; set; } = new();
}

public class PurchaseReturnItemDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("purchase_order_item_id")]
    public long PurchaseOrderItemId { get; set; }

    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("product_name")]
    public string ProductName { get; set; } = string.Empty;

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = "Cái";

    [JsonPropertyName("quantity_returned")]
    public int QuantityReturned { get; set; }

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }

    [JsonPropertyName("subtotal")]
    public decimal Subtotal { get; set; }

    [JsonPropertyName("reason_detail")]
    public string? ReasonDetail { get; set; }
}
