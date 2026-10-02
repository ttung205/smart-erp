using Microsoft.AspNetCore.Mvc;
using Purchase.Application.Common.Models;
using Purchase.Application.DTOs.Invoices;
using Purchase.Application.Interfaces;

namespace Purchase.Api.Controllers;

[ApiController]
[Route("api/v1/purchase/invoices")]
[Route("invoices")]
[Route("api/invoices")]
public class PurchaseInvoicesController : ControllerBase
{
    private readonly IThreeWayMatchingService _matchingService;

    public PurchaseInvoicesController(IThreeWayMatchingService matchingService)
    {
        _matchingService = matchingService;
    }

    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    /// <summary>
    /// Lấy danh sách hóa đơn tài chính của Nhà cung cấp có lọc theo PO, NCC, Trạng thái
    /// </summary>
    [HttpGet]
    public async Task<IActionResult> GetList(
        [FromQuery] long? purchaseOrderId,
        [FromQuery] long? supplierId,
        [FromQuery] string? status,
        CancellationToken ct = default)
    {
        var list = await _matchingService.GetListAsync(purchaseOrderId, supplierId, status, ct);
        return Ok(ApiEnvelope<List<PurchaseInvoiceDto>>.Ok(list, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Xem chi tiết một hóa đơn mua hàng
    /// </summary>
    [HttpGet("{id}")]
    public async Task<IActionResult> GetById(long id, CancellationToken ct = default)
    {
        var invoice = await _matchingService.GetByIdAsync(id, ct);
        if (invoice == null)
        {
            return NotFound(ApiEnvelope<object>.Fail("INVOICE_NOT_FOUND", $"Không tìm thấy hóa đơn ID {id}.", correlationId: GetCorrelationId()));
        }

        return Ok(ApiEnvelope<PurchaseInvoiceDto>.Ok(invoice, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Kế toán lập mới hóa đơn tài chính từ NCC và tự động chạy đối chiếu 3 chiều ban đầu
    /// </summary>
    [HttpPost]
    public async Task<IActionResult> Create([FromBody] CreatePurchaseInvoiceDto dto, CancellationToken ct = default)
    {
        var created = await _matchingService.CreateInvoiceAsync(dto, ct);
        return CreatedAtAction(nameof(GetById), new { id = created.Id },
            ApiEnvelope<PurchaseInvoiceDto>.Ok(created, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Kích hoạt chạy lại đối chiếu 3 bên (PO – GRN – Invoice) để kiểm tra lệch giá/lệch số lượng
    /// </summary>
    [HttpPost("{id}/match")]
    public async Task<IActionResult> Match(long id, CancellationToken ct = default)
    {
        var matchResult = await _matchingService.MatchInvoiceAsync(id, ct);
        return Ok(ApiEnvelope<ThreeWayMatchingResultDto>.Ok(matchResult, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }

    /// <summary>
    /// Ghi sổ hóa đơn (Posted) chuyển sang Kế toán công nợ (Yêu cầu phải MATCHED, chặn nếu có chênh lệch)
    /// </summary>
    [HttpPost("{id}/post")]
    public async Task<IActionResult> Post(long id, CancellationToken ct = default)
    {
        var posted = await _matchingService.PostInvoiceAsync(id, ct);
        return Ok(ApiEnvelope<PurchaseInvoiceDto>.Ok(posted, new ApiMeta { CorrelationId = GetCorrelationId() }));
    }
}
