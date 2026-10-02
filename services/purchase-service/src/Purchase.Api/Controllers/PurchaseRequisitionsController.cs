using Microsoft.AspNetCore.Mvc;
using Purchase.Application.Common.Models;
using Purchase.Application.DTOs.Requisitions;
using Purchase.Application.Interfaces;

namespace Purchase.Api.Controllers;

[ApiController]
[Route("api/v1/purchase/requisitions")]
[Route("requisitions")]
[Route("api/requisitions")]
public class PurchaseRequisitionsController : ControllerBase
{
    private readonly IPurchaseRequisitionService _requisitionService;

    public PurchaseRequisitionsController(IPurchaseRequisitionService requisitionService)
    {
        _requisitionService = requisitionService;
    }

    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    /// <summary>
    /// Lấy danh sách phiếu đề nghị mua hàng (PR) có lọc theo Kho và Trạng thái
    /// </summary>
    [HttpGet]
    public async Task<IActionResult> GetList(
        [FromQuery] long? warehouseId,
        [FromQuery] string? approvalStatus,
        [FromQuery] string? orderStatus,
        CancellationToken ct = default)
    {
        var list = await _requisitionService.GetListAsync(warehouseId, approvalStatus, orderStatus, ct);
        return Ok(ApiEnvelope<List<PurchaseRequisitionDto>>.Ok(list, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Lấy danh sách các dòng PR đã duyệt còn hạn mức khả dụng để nhân viên thu mua gom vào PO
    /// </summary>
    [HttpGet("available-for-order")]
    public async Task<IActionResult> GetAvailableForOrder(
        [FromQuery] long? warehouseId,
        CancellationToken ct = default)
    {
        var items = await _requisitionService.GetApprovedItemsAvailableForOrderingAsync(warehouseId, ct);
        return Ok(ApiEnvelope<List<ApprovedPrItemForOrderingDto>>.Ok(items, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Xem chi tiết một phiếu đề nghị mua hàng
    /// </summary>
    [HttpGet("{id}")]
    public async Task<IActionResult> GetById(long id, CancellationToken ct = default)
    {
        var pr = await _requisitionService.GetByIdAsync(id, ct);
        if (pr == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("PR_NOT_FOUND", $"Không tìm thấy phiếu đề nghị ID {id}.", correlationId: GetCorrelationId()));
        }

        return Ok(ApiEnvelope<PurchaseRequisitionDto>.Ok(pr, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Thủ kho lập mới đề nghị mua hàng (PR) ở trạng thái Dự thảo (Draft)
    /// </summary>
    [HttpPost]
    public async Task<IActionResult> Create([FromBody] CreatePurchaseRequisitionDto dto, CancellationToken ct = default)
    {
        var created = await _requisitionService.CreateAsync(dto, ct);
        return CreatedAtAction(nameof(GetById), new { id = created.Id },
            ApiEnvelope<PurchaseRequisitionDto>.Ok(created, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Gửi duyệt phiếu đề nghị mua hàng (Draft -> Submitted)
    /// </summary>
    [HttpPost("{id}/submit")]
    public async Task<IActionResult> Submit(long id, CancellationToken ct = default)
    {
        var updated = await _requisitionService.SubmitAsync(id, ct);
        return Ok(ApiEnvelope<PurchaseRequisitionDto>.Ok(updated, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Quản lý phê duyệt đề nghị mua hàng (có thể điều chỉnh số lượng duyệt từng dòng)
    /// </summary>
    [HttpPost("{id}/approve")]
    public async Task<IActionResult> Approve(long id, [FromBody] ApprovePurchaseRequisitionDto dto, CancellationToken ct = default)
    {
        var approved = await _requisitionService.ApproveAsync(id, dto, ct);
        return Ok(ApiEnvelope<PurchaseRequisitionDto>.Ok(approved, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Quản lý từ chối đề nghị mua hàng
    /// </summary>
    [HttpPost("{id}/reject")]
    public async Task<IActionResult> Reject(long id, [FromBody] RejectPurchaseRequisitionDto dto, CancellationToken ct = default)
    {
        var rejected = await _requisitionService.RejectAsync(id, dto, ct);
        return Ok(ApiEnvelope<PurchaseRequisitionDto>.Ok(rejected, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }
}
