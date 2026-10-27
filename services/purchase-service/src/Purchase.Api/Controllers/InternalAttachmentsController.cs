using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Purchase.Infrastructure.Persistence;

namespace Purchase.Api.Controllers;

/// <summary>
/// Tệp đính kèm của yêu cầu hỏi hàng cho service khác đọc (notification-service tải tệp để gửi email cho nhà cung cấp).
/// Chỉ chấp nhận X-Internal-Service-Key (xem JwtAuthMiddleware); đường dẫn /internal không đi qua Kong.
/// </summary>
[ApiController]
[Route("internal/inquiry-attachments")]
public class InternalAttachmentsController(PurchaseDbContext db, IWebHostEnvironment env) : ControllerBase
{
    [HttpGet("{id:long}")]
    public async Task<IActionResult> Download(long id, CancellationToken ct)
    {
        var attachment = await db.ItemInquiryAttachments.AsNoTracking().FirstOrDefaultAsync(x => x.Id == id, ct);
        if (attachment == null) return NotFound();

        // StorageName do server sinh; GetFileName chặn mọi đường dẫn thoát khỏi thư mục lưu tệp
        var path = Path.Combine(env.ContentRootPath, "inquiry-attachments", Path.GetFileName(attachment.StorageName));
        if (!System.IO.File.Exists(path)) return NotFound();
        return PhysicalFile(path, attachment.ContentType, attachment.FileName);
    }
}
