using System.Text.Json.Serialization;

namespace Purchase.Application.Common.Events;

/// <summary>
/// Payload sự kiện GoodsReceived bắn ra qua routing key: purchase.goods.received
/// Khớp 100% với định dạng mà inventory-service (EventConsumerHandler) đang đón nhận
/// </summary>
public class GoodsReceivedPayload
{
    [JsonPropertyName("receipt_id")]
    public string ReceiptId { get; set; } = string.Empty;

    [JsonPropertyName("po_id")]
    public string PoId { get; set; } = string.Empty;

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("received_by")]
    public long? ReceivedBy { get; set; }

    [JsonPropertyName("received_at")]
    public DateTime ReceivedAt { get; set; } = DateTime.UtcNow;

    [JsonPropertyName("items")]
    public List<GoodsReceivedItemPayload> Items { get; set; } = new();
}

public class GoodsReceivedItemPayload
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("quantity_received")]
    public int QuantityReceived { get; set; }
}

/// <summary>
/// Payload sự kiện PurchaseOrderApproved bắn ra qua routing key: purchase.order.approved
/// </summary>
public class PurchaseOrderApprovedPayload
{
    [JsonPropertyName("po_id")]
    public long PoId { get; set; }

    [JsonPropertyName("po_code")]
    public string PoCode { get; set; } = string.Empty;

    [JsonPropertyName("supplier_id")]
    public long SupplierId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("grand_total")]
    public decimal GrandTotal { get; set; }

    [JsonPropertyName("approved_by")]
    public long? ApprovedBy { get; set; }

    [JsonPropertyName("approved_at")]
    public DateTime? ApprovedAt { get; set; }

    [JsonPropertyName("items")]
    public List<PurchaseOrderApprovedItemPayload> Items { get; set; } = new();
}

public class PurchaseOrderApprovedItemPayload
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("product_name")]
    public string ProductName { get; set; } = string.Empty;

    [JsonPropertyName("quantity_ordered")]
    public int QuantityOrdered { get; set; }

    [JsonPropertyName("unit_cost")]
    public decimal UnitCost { get; set; }
}

/// <summary>
/// Payload sự kiện PurchaseOrderCancelled bắn ra qua routing key: purchase.order.cancelled
/// </summary>
public class PurchaseOrderCancelledPayload
{
    [JsonPropertyName("po_id")]
    public long PoId { get; set; }

    [JsonPropertyName("po_code")]
    public string PoCode { get; set; } = string.Empty;

    [JsonPropertyName("cancelled_by")]
    public long CancelledBy { get; set; }

    [JsonPropertyName("reason")]
    public string? Reason { get; set; }

    [JsonPropertyName("cancelled_at")]
    public DateTime CancelledAt { get; set; } = DateTime.UtcNow;
}
