using Microsoft.AspNetCore.Mvc;
using Purchase.Application.Common.Models;
using Purchase.Application.DTOs.GoodsReceipts;
using Purchase.Application.Interfaces;

namespace Purchase.Api.Controllers;

[ApiController]
[Route("api/v1/purchase/receipts")]
[Route("receipts")]
[Route("api/receipts")]
public class GoodsReceiptsController : ControllerBase
{
    private readonly IGoodsReceiptService _receiptService;

    public GoodsReceiptsController(IGoodsReceiptService receiptService)
    {
        _receiptService = receiptService;
    }

    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    /// <summary>
    /// Lấy danh sách phiếu kiểm nhận nhập kho (GRN) có lọc theo PO, Kho, Trạng thái
    /// </summary>
    [HttpGet]
    public async Task<IActionResult> GetList(
        [FromQuery] long? purchaseOrderId,
        [FromQuery] long? warehouseId,
        [FromQuery] string? status,
        CancellationToken ct = default)
    {
        var list = await _receiptService.GetListAsync(purchaseOrderId, warehouseId, status, ct);
        return Ok(ApiEnvelope<List<GoodsReceiptDto>>.Ok(list, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Xem chi tiết một phiếu nhập kho
    /// </summary>
    [HttpGet("{id}")]
    public async Task<IActionResult> GetById(long id, CancellationToken ct = default)
    {
        var grn = await _receiptService.GetByIdAsync(id, ct);
        if (grn == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("GRN_NOT_FOUND", $"Không tìm thấy phiếu nhập kho ID {id}.", correlationId: GetCorrelationId()));
        }

        return Ok(ApiEnvelope<GoodsReceiptDto>.Ok(grn, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Thủ kho lập mới phiếu kiểm nhận nhập kho theo đơn PO đã duyệt
    /// </summary>
    [HttpPost]
    public async Task<IActionResult> Create([FromBody] CreateGoodsReceiptDto dto, CancellationToken ct = default)
    {
        var created = await _receiptService.CreateAsync(dto, ct);
        return CreatedAtAction(nameof(GetById), new { id = created.Id },
            ApiEnvelope<GoodsReceiptDto>.Ok(created, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Xác nhận phiếu nhập kho (Confirmed) -> Tự động cập nhật PO và ghi sự kiện Outbox GoodsReceived sang Inventory Service
    /// </summary>
    [HttpPost("{id}/confirm")]
    public async Task<IActionResult> Confirm(long id, CancellationToken ct = default)
    {
        var confirmed = await _receiptService.ConfirmAsync(id, ct);
        return Ok(ApiEnvelope<GoodsReceiptDto>.Ok(confirmed, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }
}
