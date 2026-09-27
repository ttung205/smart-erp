using System.ComponentModel.DataAnnotations;
using System.Text.Json.Serialization;

namespace Inventory.Application.DTOs;

public class ReserveStockRequest
{
    [Required(ErrorMessage = "Mã đơn hàng (order_id) không được để trống.")]
    [JsonPropertyName("order_id")]
    public string OrderId { get; set; } = string.Empty;

    [Range(1, long.MaxValue, ErrorMessage = "warehouse_id phải lớn hơn 0.")]
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [Range(1, long.MaxValue, ErrorMessage = "product_id phải lớn hơn 0.")]
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [Range(1, int.MaxValue, ErrorMessage = "quantity phải lớn hơn 0.")]
    [JsonPropertyName("quantity")]
    public int Quantity { get; set; }

    [JsonPropertyName("expires_at")]
    public DateTime? ExpiresAt { get; set; }
}

public class BatchReserveStockRequest
{
    [Required(ErrorMessage = "Mã đơn hàng (order_id) không được để trống.")]
    [JsonPropertyName("order_id")]
    public string OrderId { get; set; } = string.Empty;

    [Required(ErrorMessage = "Danh sách mặt hàng không được để trống.")]
    [MinLength(1, ErrorMessage = "Phải có ít nhất 1 mặt hàng để giữ kho.")]
    [JsonPropertyName("items")]
    public List<BatchReserveItem> Items { get; set; } = new();

    [JsonPropertyName("expires_at")]
    public DateTime? ExpiresAt { get; set; }
}

public class BatchReserveItem
{
    [Range(1, long.MaxValue, ErrorMessage = "warehouse_id phải lớn hơn 0.")]
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [Range(1, long.MaxValue, ErrorMessage = "product_id phải lớn hơn 0.")]
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [Range(1, int.MaxValue, ErrorMessage = "quantity phải lớn hơn 0.")]
    [JsonPropertyName("quantity")]
    public int Quantity { get; set; }
}

public class ReleaseStockRequest
{
    [Required(ErrorMessage = "Mã đơn hàng (order_id) không được để trống.")]
    [JsonPropertyName("order_id")]
    public string OrderId { get; set; } = string.Empty;

    [Range(1, long.MaxValue, ErrorMessage = "warehouse_id phải lớn hơn 0.")]
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [Range(1, long.MaxValue, ErrorMessage = "product_id phải lớn hơn 0.")]
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [Range(1, int.MaxValue, ErrorMessage = "quantity phải lớn hơn 0.")]
    [JsonPropertyName("quantity")]
    public int Quantity { get; set; }
}

public class ReceiveStockRequest
{
    [Required(ErrorMessage = "Mã chứng từ (reference_doc) không được để trống.")]
    [JsonPropertyName("reference_doc")]
    public string ReferenceDoc { get; set; } = string.Empty;

    [Range(1, long.MaxValue, ErrorMessage = "warehouse_id phải lớn hơn 0.")]
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [Range(1, long.MaxValue, ErrorMessage = "product_id phải lớn hơn 0.")]
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [Range(1, int.MaxValue, ErrorMessage = "quantity phải lớn hơn 0.")]
    [JsonPropertyName("quantity")]
    public int Quantity { get; set; }

    [Range(0, int.MaxValue, ErrorMessage = "min_stock không được là số âm.")]
    [JsonPropertyName("min_stock")]
    public int MinStock { get; set; } = 0;
}

public class IssueStockRequest
{
    [Required(ErrorMessage = "Mã chứng từ (reference_doc) không được để trống.")]
    [JsonPropertyName("reference_doc")]
    public string ReferenceDoc { get; set; } = string.Empty;

    [Range(1, long.MaxValue, ErrorMessage = "warehouse_id phải lớn hơn 0.")]
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [Range(1, long.MaxValue, ErrorMessage = "product_id phải lớn hơn 0.")]
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [Range(1, int.MaxValue, ErrorMessage = "quantity phải lớn hơn 0.")]
    [JsonPropertyName("quantity")]
    public int Quantity { get; set; }

    [JsonPropertyName("order_id")]
    public string? OrderId { get; set; }
}

public class StockFilterQuery
{
    public long? ProductId { get; set; }
    public long? WarehouseId { get; set; }
    public bool? LowStock { get; set; }
    public int Page { get; set; } = 1;
    public int PageSize { get; set; } = 20;
}
