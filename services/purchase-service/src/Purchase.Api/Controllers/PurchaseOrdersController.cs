using Microsoft.AspNetCore.Mvc;
using Purchase.Application.Common.Models;
using Purchase.Application.DTOs.PurchaseOrders;
using Purchase.Application.Interfaces;
using System.Text.Json.Serialization;

namespace Purchase.Api.Controllers;

public class ApprovePurchaseOrderRequest
{
    [JsonPropertyName("approved_by")]
    public long? ApprovedBy { get; set; }
}

[ApiController]
[Route("api/v1/purchase/orders")]
[Route("orders")]
[Route("api/orders")]
public class PurchaseOrdersController : ControllerBase
{
    private readonly IPurchaseOrderService _orderService;

    public PurchaseOrdersController(IPurchaseOrderService orderService)
    {
        _orderService = orderService;
    }

    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    /// <summary>
    /// Lấy danh sách đơn mua hàng (PO) có lọc theo Nhà cung cấp, Kho, Trạng thái
    /// </summary>
    [HttpGet]
    public async Task<IActionResult> GetList(
        [FromQuery] long? supplierId,
        [FromQuery] long? warehouseId,
        [FromQuery] string? status,
        CancellationToken ct = default)
    {
        var list = await _orderService.GetListAsync(supplierId, warehouseId, status, ct);
        return Ok(ApiEnvelope<List<PurchaseOrderDto>>.Ok(list, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Xem chi tiết một đơn mua hàng kèm danh sách phân bổ nhu cầu từ PR
    /// </summary>
    [HttpGet("{id}")]
    public async Task<IActionResult> GetById(long id, CancellationToken ct = default)
    {
        var po = await _orderService.GetByIdAsync(id, ct);
        if (po == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("PO_NOT_FOUND", $"Không tìm thấy đơn mua hàng ID {id}.", correlationId: GetCorrelationId()));
        }

        return Ok(ApiEnvelope<PurchaseOrderDto>.Ok(po, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Nhân viên thu mua tạo mới đơn PO (có hỗ trợ phân bổ gom nhu cầu từ các PR đã duyệt)
    /// </summary>
    [HttpPost]
    public async Task<IActionResult> Create([FromBody] CreatePurchaseOrderDto dto, CancellationToken ct = default)
    {
        var created = await _orderService.CreateAsync(dto, ct);
        return CreatedAtAction(nameof(GetById), new { id = created.Id },
            ApiEnvelope<PurchaseOrderDto>.Ok(created, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Gửi duyệt đơn đặt mua (Draft -> PendingApproval)
    /// </summary>
    [HttpPost("{id}/submit")]
    public async Task<IActionResult> Submit(long id, CancellationToken ct = default)
    {
        var updated = await _orderService.SubmitAsync(id, ct);
        return Ok(ApiEnvelope<PurchaseOrderDto>.Ok(updated, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Phê duyệt đơn mua hàng (Approved) và kích hoạt ghi nhận Outbox PurchaseOrderApproved
    /// </summary>
    [HttpPost("{id}/approve")]
    public async Task<IActionResult> Approve(long id, [FromBody] ApprovePurchaseOrderRequest? req, CancellationToken ct = default)
    {
        long approvedBy = req?.ApprovedBy ?? 1;
        var approved = await _orderService.ApproveAsync(id, approvedBy, ct);
        return Ok(ApiEnvelope<PurchaseOrderDto>.Ok(approved, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Hủy đơn mua hàng (Cancelled), giải phóng phân bổ nhu cầu PR và kích hoạt Outbox PurchaseOrderCancelled
    /// </summary>
    [HttpPost("{id}/cancel")]
    public async Task<IActionResult> Cancel(long id, [FromBody] CancelPurchaseOrderDto dto, CancellationToken ct = default)
    {
        var cancelled = await _orderService.CancelAsync(id, dto, ct);
        return Ok(ApiEnvelope<PurchaseOrderDto>.Ok(cancelled, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Đóng đơn mua hàng sớm (Completed) khi không cần nhà cung cấp giao bù số lượng còn lại
    /// </summary>
    [HttpPost("{id}/close")]
    public async Task<IActionResult> Close(long id, [FromBody] ClosePurchaseOrderDto? dto, CancellationToken ct = default)
    {
        var req = dto ?? new ClosePurchaseOrderDto();
        var closed = await _orderService.CloseAsync(id, req, ct);
        return Ok(ApiEnvelope<PurchaseOrderDto>.Ok(closed, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }
}
