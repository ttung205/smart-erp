using System.Text.Json.Serialization;

namespace Inventory.Application.DTOs;

public class StockResponseDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("warehouse_code")]
    public string? WarehouseCode { get; set; }

    [JsonPropertyName("warehouse_name")]
    public string? WarehouseName { get; set; }

    [JsonPropertyName("quantity_on_hand")]
    public int QuantityOnHand { get; set; }

    [JsonPropertyName("quantity_reserved")]
    public int QuantityReserved { get; set; }

    [JsonPropertyName("available")]
    public int Available { get; set; }

    [JsonPropertyName("min_stock")]
    public int MinStock { get; set; }

    [JsonPropertyName("is_low_stock")]
    public bool IsLowStock { get; set; }

    [JsonPropertyName("can_sell")]
    public bool CanSell { get; set; } = true;

    [JsonPropertyName("sales_available")]
    public int SalesAvailable { get; set; }

    [JsonPropertyName("updated_at")]
    public DateTime UpdatedAt { get; set; }
}

public class ProductStockOverviewDto
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("total_on_hand")]
    public int TotalOnHand { get; set; }

    [JsonPropertyName("total_reserved")]
    public int TotalReserved { get; set; }

    [JsonPropertyName("total_available")]
    public int TotalAvailable { get; set; }

    [JsonPropertyName("warehouses")]
    public List<WarehouseStockBreakdownDto> Warehouses { get; set; } = new();
}

public class WarehouseStockBreakdownDto
{
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("warehouse_code")]
    public string WarehouseCode { get; set; } = string.Empty;

    [JsonPropertyName("warehouse_name")]
    public string WarehouseName { get; set; } = string.Empty;

    [JsonPropertyName("quantity_on_hand")]
    public int QuantityOnHand { get; set; }

    [JsonPropertyName("quantity_reserved")]
    public int QuantityReserved { get; set; }

    [JsonPropertyName("available")]
    public int Available { get; set; }

    [JsonPropertyName("min_stock")]
    public int MinStock { get; set; }

    [JsonPropertyName("is_low_stock")]
    public bool IsLowStock { get; set; }
}

public class StockMovementResponseDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("warehouse_name")]
    public string? WarehouseName { get; set; }

    [JsonPropertyName("movement_type")]
    public string MovementType { get; set; } = string.Empty;

    [JsonPropertyName("balance_before")]
    public int BalanceBefore { get; set; }

    [JsonPropertyName("quantity_delta")]
    public int QuantityDelta { get; set; }

    [JsonPropertyName("balance_after")]
    public int BalanceAfter { get; set; }

    [JsonPropertyName("balance_on_hand_after")]
    public int BalanceOnHandAfter { get; set; }

    [JsonPropertyName("balance_reserved_after")]
    public int BalanceReservedAfter { get; set; }

    [JsonPropertyName("reference_doc")]
    public string ReferenceDoc { get; set; } = string.Empty;

    [JsonPropertyName("created_at")]
    public DateTime CreatedAt { get; set; }
}

public class StockOperationResponseDto
{
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("quantity_on_hand")]
    public int QuantityOnHand { get; set; }

    [JsonPropertyName("quantity_reserved")]
    public int QuantityReserved { get; set; }

    [JsonPropertyName("available")]
    public int Available { get; set; }

    [JsonPropertyName("operation_type")]
    public string OperationType { get; set; } = string.Empty;

    [JsonPropertyName("quantity_delta")]
    public int QuantityDelta { get; set; }

    [JsonPropertyName("reference_doc")]
    public string ReferenceDoc { get; set; } = string.Empty;

    [JsonPropertyName("movement_id")]
    public long MovementId { get; set; }

    [JsonPropertyName("reservation_id")]
    public long? ReservationId { get; set; }

    [JsonPropertyName("timestamp")]
    public DateTime Timestamp { get; set; }
}
