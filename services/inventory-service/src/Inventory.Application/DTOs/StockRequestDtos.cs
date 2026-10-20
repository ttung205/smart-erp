using System.ComponentModel.DataAnnotations;
using System.Text.Json.Serialization;

namespace Inventory.Application.DTOs;

public class ChangeStockRequest
{
    [Range(1, long.MaxValue, ErrorMessage = "warehouse_id phải lớn hơn 0.")]
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [Range(1, long.MaxValue, ErrorMessage = "product_id phải lớn hơn 0.")]
    [JsonPropertyName("product_id")]
    public long ProductId { get; set; }

    [Required(ErrorMessage = "quantity_delta không được để trống.")]
    [JsonPropertyName("quantity_delta")]
    public int QuantityDelta { get; set; }

    [Required(ErrorMessage = "document_type không được để trống.")]
    [JsonPropertyName("document_type")]
    public string DocumentType { get; set; } = string.Empty;

    [Required(ErrorMessage = "document_id không được để trống.")]
    [JsonPropertyName("document_id")]
    public string DocumentId { get; set; } = string.Empty;

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("created_by")]
    public long? CreatedBy { get; set; }

    [JsonPropertyName("origin")]
    public string? Origin { get; set; }
}

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

/// <summary>Nhả toàn bộ hàng đã giữ cho một chứng từ bán hàng (hủy chứng từ).</summary>
public class ReleaseDocumentRequest
{
    [Required(ErrorMessage = "Mã chứng từ (order_id) không được để trống.")]
    [JsonPropertyName("order_id")]
    public string OrderId { get; set; } = string.Empty;
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
    public int Page { get; set; } = 1;
    public int PageSize { get; set; } = 20;
}
