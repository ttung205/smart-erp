using System.Text.Json.Serialization;
using Purchase.Domain.Enums;

namespace Purchase.Application.DTOs.Requisitions;

public class CreatePurchaseRequisitionDto
{
    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("requested_by")]
    public long RequestedBy { get; set; }

    [JsonPropertyName("expected_needed_date")]
    public DateTime ExpectedNeededDate { get; set; }

    [JsonPropertyName("priority")]
    public string Priority { get; set; } = "Medium"; // Low, Medium, High, Urgent

    [JsonPropertyName("reason")]
    public string? Reason { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("items")]
    public List<CreatePurchaseRequisitionItemDto> Items { get; set; } = new();
}

public class CreatePurchaseRequisitionItemDto
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

    [JsonPropertyName("quantity_requested")]
    public int QuantityRequested { get; set; }

    [JsonPropertyName("estimated_unit_cost")]
    public decimal? EstimatedUnitCost { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }
}

public class ApprovePurchaseRequisitionDto
{
    [JsonPropertyName("approved_by")]
    public long ApprovedBy { get; set; }

    [JsonPropertyName("items")]
    public List<ApprovePrItemAdjustDto>? Items { get; set; }
}

public class ApprovePrItemAdjustDto
{
    [JsonPropertyName("item_id")]
    public long ItemId { get; set; }

    [JsonPropertyName("quantity_approved")]
    public int QuantityApproved { get; set; }
}

public class RejectPurchaseRequisitionDto
{
    [JsonPropertyName("rejected_by")]
    public long RejectedBy { get; set; }

    [JsonPropertyName("reason")]
    public string Reason { get; set; } = string.Empty;
}

public class PurchaseRequisitionDto
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("code")]
    public string Code { get; set; } = string.Empty;

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

    [JsonPropertyName("requested_by")]
    public long RequestedBy { get; set; }

    [JsonPropertyName("request_date")]
    public DateTime RequestDate { get; set; }

    [JsonPropertyName("expected_needed_date")]
    public DateTime ExpectedNeededDate { get; set; }

    [JsonPropertyName("priority")]
    public string Priority { get; set; } = string.Empty;

    [JsonPropertyName("reason")]
    public string? Reason { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }

    [JsonPropertyName("approval_status")]
    public string ApprovalStatus { get; set; } = string.Empty;

    [JsonPropertyName("order_status")]
    public string OrderStatus { get; set; } = string.Empty;

    [JsonPropertyName("approved_by")]
    public long? ApprovedBy { get; set; }

    [JsonPropertyName("approved_at")]
    public DateTime? ApprovedAt { get; set; }

    [JsonPropertyName("rejected_reason")]
    public string? RejectedReason { get; set; }

    [JsonPropertyName("created_at")]
    public DateTime CreatedAt { get; set; }

    [JsonPropertyName("items")]
    public List<PurchaseRequisitionItemDto> Items { get; set; } = new();
}

public class PurchaseRequisitionItemDto
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

    [JsonPropertyName("quantity_requested")]
    public int QuantityRequested { get; set; }

    [JsonPropertyName("quantity_approved")]
    public int QuantityApproved { get; set; }

    [JsonPropertyName("quantity_ordered")]
    public int QuantityOrdered { get; set; }

    [JsonPropertyName("available_to_order")]
    public int AvailableToOrder { get; set; }

    [JsonPropertyName("estimated_unit_cost")]
    public decimal? EstimatedUnitCost { get; set; }

    [JsonPropertyName("notes")]
    public string? Notes { get; set; }
}

public class ApprovedPrItemForOrderingDto
{
    [JsonPropertyName("pr_id")]
    public long PrId { get; set; }

    [JsonPropertyName("pr_code")]
    public string PrCode { get; set; } = string.Empty;

    [JsonPropertyName("pr_item_id")]
    public long PrItemId { get; set; }

    [JsonPropertyName("warehouse_id")]
    public long WarehouseId { get; set; }

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

    [JsonPropertyName("quantity_approved")]
    public int QuantityApproved { get; set; }

    [JsonPropertyName("quantity_ordered")]
    public int QuantityOrdered { get; set; }

    [JsonPropertyName("available_to_order")]
    public int AvailableToOrder { get; set; }

    [JsonPropertyName("estimated_unit_cost")]
    public decimal? EstimatedUnitCost { get; set; }

    [JsonPropertyName("expected_needed_date")]
    public DateTime ExpectedNeededDate { get; set; }
}
