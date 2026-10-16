using Microsoft.AspNetCore.Mvc;
using Purchase.Application.Common.Models;
using Purchase.Application.DTOs.PurchaseReturns;
using Purchase.Application.Interfaces;

namespace Purchase.Api.Controllers;

[ApiController]
[Route("api/v1/purchase/returns")]
[Route("returns")]
[Route("api/returns")]
public class PurchaseReturnsController : ControllerBase
{
    private readonly IPurchaseReturnService _returnService;

    public PurchaseReturnsController(IPurchaseReturnService returnService)
    {
        _returnService = returnService;
    }

    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    /// <summary>
    /// Lấy danh sách phiếu trả hàng mua (RTV)
    /// </summary>
    [HttpGet]
    public async Task<IActionResult> GetList(
        [FromQuery] long? supplierId,
        [FromQuery] string? status,
        CancellationToken ct = default)
    {
        var list = await _returnService.GetListAsync(supplierId, status, ct);
        return Ok(ApiEnvelope<List<PurchaseReturnDto>>.Ok(list, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Xem chi tiết phiếu trả hàng
    /// </summary>
    [HttpGet("{id}")]
    public async Task<IActionResult> GetById(long id, CancellationToken ct = default)
    {
        var item = await _returnService.GetByIdAsync(id, ct);
        if (item == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("RETURN_NOT_FOUND", $"Không tìm thấy phiếu trả hàng ID {id}.", correlationId: GetCorrelationId()));
        }

        return Ok(ApiEnvelope<PurchaseReturnDto>.Ok(item, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Lập phiếu trả hàng mua mới (Draft)
    /// </summary>
    [HttpPost]
    public async Task<IActionResult> Create([FromBody] CreatePurchaseReturnDto dto, CancellationToken ct = default)
    {
        var created = await _returnService.CreateAsync(dto, ct);
        return CreatedAtAction(nameof(GetById), new { id = created.Id },
            ApiEnvelope<PurchaseReturnDto>.Ok(created, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Cập nhật phiếu trả hàng mua ở trạng thái Draft (lý do, RMA, ghi chú, mặt hàng)
    /// </summary>
    [HttpPut("{id}")]
    public async Task<IActionResult> Update(long id, [FromBody] UpdatePurchaseReturnDto dto, CancellationToken ct = default)
    {
        var updated = await _returnService.UpdateDraftAsync(id, dto, ct);
        return Ok(ApiEnvelope<PurchaseReturnDto>.Ok(updated, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Gửi phê duyệt phiếu trả hàng mua (Submit)
    /// </summary>
    [HttpPost("{id}/submit")]
    public async Task<IActionResult> Submit(long id, [FromBody] SubmitPurchaseReturnDto? dto, CancellationToken ct = default)
    {
        var submitted = await _returnService.SubmitAsync(id, dto, ct);
        return Ok(ApiEnvelope<PurchaseReturnDto>.Ok(submitted, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Phê duyệt phiếu trả hàng mua
    /// </summary>
    [HttpPost("{id}/approve")]
    public async Task<IActionResult> Approve(long id, [FromBody] ApproveReturnRequest? request, CancellationToken ct = default)
    {
        var approved = await _returnService.ApproveAsync(id, request?.ApprovedBy ?? 1, ct);
        return Ok(ApiEnvelope<PurchaseReturnDto>.Ok(approved, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Xác nhận xuất kho trả hàng thực tế cho NCC (Dispatched) -> Giảm tồn kho OnHand qua Outbox
    /// </summary>
    [HttpPost("{id}/dispatch")]
    public async Task<IActionResult> Dispatch(long id, CancellationToken ct = default)
    {
        var dispatched = await _returnService.DispatchAsync(id, ct);
        return Ok(ApiEnvelope<PurchaseReturnDto>.Ok(dispatched, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Hủy phiếu trả hàng mua
    /// </summary>
    [HttpPost("{id}/cancel")]
    public async Task<IActionResult> Cancel(long id, CancellationToken ct = default)
    {
        var cancelled = await _returnService.CancelAsync(id, ct);
        return Ok(ApiEnvelope<PurchaseReturnDto>.Ok(cancelled, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }
}

public class ApproveReturnRequest
{
    public long ApprovedBy { get; set; } = 1;
}
