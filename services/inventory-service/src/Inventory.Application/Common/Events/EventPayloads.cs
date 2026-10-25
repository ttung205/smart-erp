using System.Text.Json.Serialization;

namespace Inventory.Application.Common.Events;

#region Incoming Payloads

/// <summary>
/// Sự kiện GoodsReceived bắn từ purchase-service qua routing key: purchase.goods.received
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
/// Sự kiện ProductCreated bắn từ catalog-service qua routing key: catalog.product.created
/// </summary>
public class ProductCreatedPayload
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("origin")]
    public string Origin { get; set; } = "Việt Nam";

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = "Cái";

    [JsonPropertyName("gross_price")]
    public decimal GrossPrice { get; set; }

    [JsonPropertyName("min_stock")]
    public int MinStock { get; set; }
}

#endregion

#region Outgoing Payloads

/// <summary>
/// Sự kiện GoodsIssued bắn ra qua routing key: inventory.goods.issued
/// </summary>
public class GoodsIssuedPayload
{
    [JsonPropertyName("issue_id")]
    public string IssueId { get; set; } = string.Empty;

    [JsonPropertyName("order_id")]
    public string? OrderId { get; set; }

    [JsonPropertyName("delivery_id")]
    public string? DeliveryId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("issued_by")]
    public long? IssuedBy { get; set; }

    [JsonPropertyName("issued_at")]
    public DateTime IssuedAt { get; set; } = DateTime.UtcNow;

    [JsonPropertyName("items")]
    public List<GoodsIssuedItemPayload> Items { get; set; } = new();
}

public class GoodsIssuedItemPayload
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("quantity_issued")]
    public int QuantityIssued { get; set; }

    [JsonPropertyName("balance_on_hand_after")]
    public int BalanceOnHandAfter { get; set; }

    [JsonPropertyName("balance_reserved_after")]
    public int BalanceReservedAfter { get; set; }
}

#endregion
