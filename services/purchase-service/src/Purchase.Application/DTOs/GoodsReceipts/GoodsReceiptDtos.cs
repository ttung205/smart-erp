using System.Text.Json.Serialization;

namespace Purchase.Application.DTOs.GoodsReceipts;

public class CreateGoodsReceiptDto
{
    [JsonPropertyName("purchase_order_id")]
    public long PurchaseOrderId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("received_by")]
    public long ReceivedBy { get; set; }

    [JsonPropertyName("received_at")]
    public DateTime? ReceivedAt { get; set; }

    [JsonPropertyName("delivery_note_number")]
    public string? DeliveryNoteNumber { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("items")]
    public List<CreateGoodsReceiptItemDto> Items { get; set; } = new();
}

public class CreateGoodsReceiptItemDto
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

    [JsonPropertyName("quantity_received")]
    public int QuantityReceived { get; set; }

    [JsonPropertyName("quantity_rejected")]
    public int QuantityRejected { get; set; } = 0;

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }

    [JsonPropertyName("batch_number")]
    public string? BatchNumber { get; set; }

    [JsonPropertyName("expiry_date")]
    public DateTime? ExpiryDate { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }
}

public class GoodsReceiptDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("code")]
    public string Code { get; set; } = string.Empty;

    [JsonPropertyName("purchase_order_id")]
    public long PurchaseOrderId { get; set; }

    [JsonPropertyName("po_code")]
    public string PoCode { get; set; } = string.Empty;

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("received_by")]
    public long ReceivedBy { get; set; }

    [JsonPropertyName("received_at")]
    public DateTime ReceivedAt { get; set; }

    [JsonPropertyName("delivery_note_number")]
    public string? DeliveryNoteNumber { get; set; }

    [JsonPropertyName("status")]
    public string Status { get; set; } = string.Empty;

    [JsonPropertyName("inventory_sync_status")]
    public string InventorySyncStatus { get; set; } = string.Empty;

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("created_at")]
    public DateTime CreatedAt { get; set; }

    [JsonPropertyName("auto_purchase_return_id")]
    public long? AutoPurchaseReturnId { get; set; }

    [JsonPropertyName("auto_purchase_return_code")]
    public string? AutoPurchaseReturnCode { get; set; }

    [JsonPropertyName("auto_purchase_return_qty")]
    public int AutoPurchaseReturnQty { get; set; }

    [JsonPropertyName("items")]
    public List<GoodsReceiptItemDto> Items { get; set; } = new();
}

public class GoodsReceiptItemDto
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

    [JsonPropertyName("quantity_ordered")]
    public int QuantityOrdered { get; set; }

    [JsonPropertyName("quantity_received")]
    public int QuantityReceived { get; set; }

    [JsonPropertyName("quantity_rejected")]
    public int QuantityRejected { get; set; }

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }

    [JsonPropertyName("batch_number")]
    public string? BatchNumber { get; set; }

    [JsonPropertyName("expiry_date")]
    public DateTime? ExpiryDate { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }
}
