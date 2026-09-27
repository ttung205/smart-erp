using System.Text.Json.Serialization;

namespace Inventory.Application.Common.Events;

#region Incoming Payloads

/// <summary>
/// Sự kiện SalesOrderCreated bắn từ sales-service qua routing key: sales.order.created
/// </summary>
public class SalesOrderCreatedPayload
{
    [JsonPropertyName("order_id")]
    public string OrderId { get; set; } = string.Empty;

    [JsonPropertyName("customer_id")]
    public long CustomerId { get; set; }

    [JsonPropertyName("order_date")]
    public string? OrderDate { get; set; }

    [JsonPropertyName("total_amount")]
    public decimal TotalAmount { get; set; }

    [JsonPropertyName("items")]
    public List<SalesOrderItemPayload> Items { get; set; } = new();
}

public class SalesOrderItemPayload
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("quantity")]
    public int Quantity { get; set; }

    [JsonPropertyName("gross_price")]
    public decimal GrossPrice { get; set; }

    [JsonPropertyName("discount_percent")]
    public decimal DiscountPercent { get; set; }

    [JsonPropertyName("unit_price")]
    public decimal UnitPrice { get; set; }

    [JsonPropertyName("subtotal")]
    public decimal Subtotal { get; set; }
}

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

#endregion

#region Outgoing Payloads

/// <summary>
/// Sự kiện StockReserved bắn ra cho sales-service qua routing key: inventory.stock.reserved
/// </summary>
public class StockReservedPayload
{
    [JsonPropertyName("order_id")]
    public string OrderId { get; set; } = string.Empty;

    [JsonPropertyName("reserved_at")]
    public DateTime ReservedAt { get; set; } = DateTime.UtcNow;

    [JsonPropertyName("reservations")]
    public List<StockReservationDetailPayload> Reservations { get; set; } = new();
}

public class StockReservationDetailPayload
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("quantity_reserved")]
    public int QuantityReserved { get; set; }

    [JsonPropertyName("remaining_available")]
    public int RemainingAvailable { get; set; }
}

/// <summary>
/// Sự kiện StockReservationFailed bắn ra cho sales-service qua routing key: inventory.stock.reservation_failed
/// </summary>
public class StockReservationFailedPayload
{
    [JsonPropertyName("order_id")]
    public string OrderId { get; set; } = string.Empty;

    [JsonPropertyName("failed_at")]
    public DateTime FailedAt { get; set; } = DateTime.UtcNow;

    [JsonPropertyName("reason")]
    public string Reason { get; set; } = "INSUFFICIENT_STOCK";

    [JsonPropertyName("failed_items")]
    public List<FailedStockItemPayload> FailedItems { get; set; } = new();
}

public class FailedStockItemPayload
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("requested_quantity")]
    public int RequestedQuantity { get; set; }

    [JsonPropertyName("available_quantity")]
    public int AvailableQuantity { get; set; }
}

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
