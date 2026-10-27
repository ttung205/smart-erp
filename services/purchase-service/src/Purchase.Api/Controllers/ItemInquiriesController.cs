using System.Net.Http.Headers;
using System.Security.Claims;
using System.Text;
using System.Text.Json;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Polly;
using Purchase.Application.Interfaces;
using Purchase.Domain.Entities;
using Purchase.Infrastructure.Persistence;

namespace Purchase.Api.Controllers;

[ApiController]
[Route("inquiries")]
[Route("api/v1/purchase/inquiries")]
public class ItemInquiriesController(PurchaseDbContext db, IOutboxService outbox, IHttpClientFactory clients, IConfiguration config, IWebHostEnvironment env) : ControllerBase
{
    private long UserId => long.TryParse(User.FindFirstValue(ClaimTypes.NameIdentifier), out var id) ? id : 0;
    private bool Admin => User.IsInRole("SUPER_ADMIN");
    private bool Can(string permission) => Admin || User.HasClaim("permission", permission);
    private bool CanPurchase => Can("item-inquiry.process");
    private bool CanRead(ItemInquiryRequest r) => CanPurchase || (Can("item-inquiry.view") && r.RequestedBy == UserId);
    private IActionResult Forbidden() => StatusCode(403, new { success = false, error = new { message = "Không có quyền thực hiện thao tác này." } });
    private static IActionResult Bad(string message) => new BadRequestObjectResult(new { success = false, error = new { message } });
    private string Base(string name, string fallback) => (config[$"Services:{name}"] ?? fallback).TrimEnd('/');
    private string InternalKey() => config["Services:InternalKey"] is { Length: > 0 } key ? key : throw new InvalidOperationException("Services:InternalKey chưa được cấu hình.");
    public const string InternalClient = "internal";
    private HttpClient Client() { var client = clients.CreateClient(InternalClient); client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", Request.Headers.Authorization.ToString().Replace("Bearer ", "", StringComparison.OrdinalIgnoreCase)); return client; }
    private static object OkData(object data) => new { success = true, data };
    private static string Code(string source) => new string(source.Where(ch => char.IsLetterOrDigit(ch) || ch == '-').Select(char.ToUpperInvariant).ToArray()).Trim('-');
    private async Task<string> UniqueCode(string source, bool request, CancellationToken ct)
    {
        var prefix = $"IQ-{Code(source)}-{DateTimeOffset.UtcNow.ToOffset(TimeSpan.FromHours(7)):ddMMyy}";
        var existing = request ? await db.ItemInquiryRequests.Where(x => x.Code.StartsWith(prefix)).Select(x => x.Code).ToListAsync(ct)
            : await db.SupplierInquiries.Where(x => x.Code.StartsWith(prefix)).Select(x => x.Code).ToListAsync(ct);
        if (!existing.Contains(prefix)) return prefix;
        for (var i = 2; i < 10000; i++) if (!existing.Contains($"{prefix}-{i:00}")) return $"{prefix}-{i:00}";
        throw new InvalidOperationException("Không thể cấp mã hỏi hàng.");
    }
    private async Task<JsonElement?> CatalogGet(string path, CancellationToken ct)
    {
        var response = await Client().GetAsync($"{Base("Catalog", "http://localhost:8002")}/api/{path}", ct);
        if (!response.IsSuccessStatusCode) return null;
        using var doc = JsonDocument.Parse(await response.Content.ReadAsStringAsync(ct));
        return doc.RootElement.TryGetProperty("data", out var data) ? data.Clone() : null;
    }

    // Lưu gross price hiện tại của catalog theo SKU để sale thấy giá lúc hỏi hàng
    private async Task SnapshotCatalogPrices(List<ItemInquiryRequestItem> items, CancellationToken ct)
    {
        foreach (var group in items.Where(x => !string.IsNullOrWhiteSpace(x.Sku)).GroupBy(x => x.Sku!, StringComparer.OrdinalIgnoreCase))
        {
            var products = await CatalogGet($"products?search={Uri.EscapeDataString(group.Key)}", ct);
            if (products is not { ValueKind: JsonValueKind.Array }) continue;
            var product = products.Value.EnumerateArray().FirstOrDefault(p => string.Equals(p.GetProperty("sku").GetString(), group.Key, StringComparison.OrdinalIgnoreCase));
            if (product.ValueKind == JsonValueKind.Undefined || !product.TryGetProperty("gross_price", out var gross) || gross.ValueKind == JsonValueKind.Null) continue;
            var price = gross.ValueKind == JsonValueKind.String ? decimal.Parse(gross.GetString()!, System.Globalization.CultureInfo.InvariantCulture) : gross.GetDecimal();
            foreach (var item in group) item.CatalogGrossPrice = price;
        }
    }

    private async Task<(JsonElement? Product, string? Error)> ApplyProduct(SupplierInquiryItem item, string reference, CancellationToken ct)
    {
        var payload = JsonSerializer.Serialize(new { reference, request_item_id = item.RequestItemId, inquiry_item_id = item.Id, sku = item.Sku, designation = item.Designation, description = item.ItemDescription, brand_id = item.BrandId, category = item.Category, com = item.Com, unit = item.Unit, spec = item.Spec, buying_price = item.BuyingPrice, vat = item.Vat, tax = item.Tax, price_index = item.PriceIndex, gross_price = item.GrossPrice });
        var client = Client();
        client.DefaultRequestHeaders.Add("X-Internal-Service-Key", InternalKey());
        var response = await client.PostAsync($"{Base("Catalog", "http://localhost:8002")}/api/products/apply-inquiry-result", new StringContent(payload, Encoding.UTF8, "application/json"), ct);
        var body = await response.Content.ReadAsStringAsync(ct);
        if (!response.IsSuccessStatusCode) return (null, body);
        using var doc = JsonDocument.Parse(body);
        return (doc.RootElement.GetProperty("data").Clone(), null);
    }
    private async Task<bool> ValidUser(long id, bool watcher, CancellationToken ct)
    {
        if (id <= 0) return false;
        var response = await Client().GetAsync($"{Base("Identity", "http://localhost:8001")}/api/users/{id}", ct);
        if (!response.IsSuccessStatusCode) return false;
        using var doc = JsonDocument.Parse(await response.Content.ReadAsStringAsync(ct));
        var data = doc.RootElement.GetProperty("data");
        var departmentCode = data.TryGetProperty("department_code", out var dep) ? dep.GetString() : null;
        if (departmentCode == "PURCHASE") return true;
        if (!watcher) return false;
        // show() does not include roles; index() does.
        var listResponse = await Client().GetAsync($"{Base("Identity", "http://localhost:8001")}/api/users", ct);
        if (!listResponse.IsSuccessStatusCode) return false;
        using var listDoc = JsonDocument.Parse(await listResponse.Content.ReadAsStringAsync(ct));
        return listDoc.RootElement.GetProperty("data").EnumerateArray().Any(x => x.GetProperty("id").GetInt64() == id && x.TryGetProperty("roles", out var roles) && roles.EnumerateArray().Any(role => role.TryGetProperty("code", out var code) && code.GetString() == "SUPER_ADMIN"));
    }
    private async Task<ItemInquiryRequest?> FindRequest(long id, CancellationToken ct) => await db.ItemInquiryRequests.Include(x => x.Items).Include(x => x.Attachments).Include(x => x.Inquiries).ThenInclude(x => x.Items).FirstOrDefaultAsync(x => x.Id == id, ct);
    private async Task<SupplierInquiry?> FindInquiry(long id, CancellationToken ct) => await db.SupplierInquiries.Include(x => x.Items).Include(x => x.Attachments).FirstOrDefaultAsync(x => x.Id == id, ct);
    private object RequestView(ItemInquiryRequest r) => new { r.Id, r.Code, r.CustomerId, r.CustomerCode, r.CustomerName, r.RequestedBy, r.AssigneeId, r.WatcherId, r.Status, r.DueDate, r.Description, r.CreatedAt, r.UpdatedAt, r.Items, attachments = r.Attachments.Select(x => new { x.Id, x.FileName, x.ContentType, x.Size, x.CreatedAt }), inquiries = r.Inquiries.Select(x => new { x.Id, x.Code, x.SupplierName, x.Status }), results = r.Inquiries.Where(x => x.Status == "COMPLETED").SelectMany(inquiry => inquiry.Items.Select(item => new { inquiry_id = inquiry.Id, inquiry_code = inquiry.Code, inquiry.SupplierName, item.Id, item.RequestItemId, item.Sku, item.Designation, item.ItemDescription, brand = item.BrandName, item.Category, item.Com, item.Unit, item.Quantity, item.LeadTime, item.Note, item.Spec, item.GrossPrice })) };

    private async Task RefreshSentStatus(SupplierInquiry inquiry, CancellationToken ct)
    {
        if (inquiry.Status != "DRAFT" || string.IsNullOrEmpty(inquiry.EmailJobId)) return;
        try
        {
            var response = await Client().GetAsync($"{Base("Notification", "http://localhost:8006")}/api/v1/notifications/email/logs/{inquiry.EmailJobId}", ct);
            if (!response.IsSuccessStatusCode) return;
            using var doc = JsonDocument.Parse(await response.Content.ReadAsStringAsync(ct));
            if (doc.RootElement.GetProperty("data").GetProperty("status").GetString() != "SENT") return;
            inquiry.Status = "SENT";
            inquiry.SentAt = DateTime.UtcNow;
            inquiry.UpdatedAt = DateTime.UtcNow;
            await db.SaveChangesAsync(ct);
        }
        catch (Exception ex) when (ex is HttpRequestException or ExecutionRejectedException) { /* The email worker may be temporarily unavailable. */ }
    }

    [HttpGet("requests")]
    public async Task<IActionResult> Requests(CancellationToken ct)
    {
        if (!Can("item-inquiry.view") && !CanPurchase) return Forbidden();
        var q = db.ItemInquiryRequests.AsQueryable();
        if (!CanPurchase) q = q.Where(x => x.RequestedBy == UserId);
        var data = await q.OrderByDescending(x => x.Id).Select(x => new { x.Id, x.Code, x.CustomerName, x.RequestedBy, x.AssigneeId, x.WatcherId, x.Status, x.DueDate, x.CreatedAt, x.UpdatedAt }).ToListAsync(ct);
        return Ok(OkData(data));
    }

    [HttpGet("requests/{id:long}")]
    public async Task<IActionResult> RequestById(long id, CancellationToken ct)
    {
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (!CanRead(r)) return Forbidden();
        if (CanPurchase)
            foreach (var inquiry in r.Inquiries.Where(x => x.Status == "DRAFT" && x.EmailJobId != null))
                await RefreshSentStatus(inquiry, ct);
        return Ok(OkData(RequestView(r)));
    }

    public record RequestInput(long CustomerId, long AssigneeId, long? WatcherId, DateTime? DueDate, string? Description, List<RequestItemInput> Items);
    public record RequestItemInput(string? Sku, string Designation, string? ItemDescription, string? Brand, string? Category, string? Com, string? Unit, decimal Quantity, string? LeadTime, string? Note, string? Spec);

    [HttpPost("requests")]
    public async Task<IActionResult> CreateRequest([FromBody] RequestInput input, CancellationToken ct)
    {
        if (!Can("item-inquiry.create")) return Forbidden();
        if (input.Items == null || input.Items.Count == 0 || input.Items.Any(x => string.IsNullOrWhiteSpace(x.Designation) || x.Quantity <= 0)) return Bad("Cần ít nhất một sản phẩm có tên và số lượng hợp lệ.");
        if (!await ValidUser(input.AssigneeId, false, ct) || (input.WatcherId.HasValue && !await ValidUser(input.WatcherId.Value, true, ct))) return Bad("Người phụ trách phải thuộc Purchase; người theo dõi phải thuộc Purchase hoặc Super Admin.");
        var customer = await CatalogGet($"customers/{input.CustomerId}", ct);
        if (customer == null) return Bad("Khách hàng không tồn tại.");
        var code = customer.Value.GetProperty("code").GetString() ?? input.CustomerId.ToString();
        var name = customer.Value.GetProperty("name").GetString() ?? "";
        var r = new ItemInquiryRequest { Code = await UniqueCode(code, true, ct), CustomerId = input.CustomerId, CustomerCode = code, CustomerName = name, RequestedBy = UserId, AssigneeId = input.AssigneeId, WatcherId = input.WatcherId, DueDate = input.DueDate, Description = input.Description,
            Items = input.Items.Select(x => new ItemInquiryRequestItem { Sku = x.Sku?.Trim(), Designation = x.Designation.Trim(), ItemDescription = x.ItemDescription, Brand = x.Brand, Category = x.Category, Com = x.Com, Unit = x.Unit, Quantity = x.Quantity, LeadTime = x.LeadTime, Note = x.Note, Spec = x.Spec }).ToList() };
        await SnapshotCatalogPrices(r.Items, ct);
        db.ItemInquiryRequests.Add(r);
        await db.SaveChangesAsync(ct);
        return CreatedAtAction(nameof(RequestById), new { id = r.Id }, OkData(RequestView(r)));
    }

    [HttpPut("requests/{id:long}")]
    public async Task<IActionResult> UpdateRequest(long id, [FromBody] RequestInput input, CancellationToken ct)
    {
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (r.Status != "DRAFT" || (r.RequestedBy != UserId && !Admin) || !Can("item-inquiry.create")) return Forbidden();
        if (input.CustomerId != r.CustomerId) return Bad("Không thể đổi khách hàng sau khi cấp mã yêu cầu.");
        if (input.Items == null || input.Items.Count == 0 || input.Items.Any(x => string.IsNullOrWhiteSpace(x.Designation) || x.Quantity <= 0)) return Bad("Danh sách sản phẩm không hợp lệ.");
        if (!await ValidUser(input.AssigneeId, false, ct) || (input.WatcherId.HasValue && !await ValidUser(input.WatcherId.Value, true, ct))) return Bad("Người phụ trách hoặc người theo dõi không hợp lệ.");
        r.AssigneeId = input.AssigneeId; r.WatcherId = input.WatcherId; r.DueDate = input.DueDate; r.Description = input.Description; r.UpdatedAt = DateTime.UtcNow;
        db.ItemInquiryRequestItems.RemoveRange(r.Items);
        r.Items = input.Items.Select(x => new ItemInquiryRequestItem { Sku = x.Sku?.Trim(), Designation = x.Designation.Trim(), ItemDescription = x.ItemDescription, Brand = x.Brand, Category = x.Category, Com = x.Com, Unit = x.Unit, Quantity = x.Quantity, LeadTime = x.LeadTime, Note = x.Note, Spec = x.Spec }).ToList();
        await SnapshotCatalogPrices(r.Items, ct);
        await db.SaveChangesAsync(ct);
        return Ok(OkData(RequestView(r)));
    }

    [HttpPost("requests/{id:long}/submit")]
    public async Task<IActionResult> SubmitRequest(long id, CancellationToken ct)
    {
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (r.RequestedBy != UserId && !Admin) return Forbidden();
        if (r.Status != "DRAFT") return Bad("Chỉ gửi được yêu cầu nháp.");
        r.Status = "WAITING"; r.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        await outbox.SaveEventAsync("purchase.inquiry.requested", "ItemInquiryRequested", "ItemInquiryRequest", r.Id.ToString(), new { request_id = r.Id, r.Code, r.AssigneeId, r.WatcherId, r.RequestedBy, actor = new { id = UserId, name = User.Identity?.Name } }, ct: ct);
        return Ok(OkData(RequestView(r)));
    }

    [HttpPost("requests/{id:long}/close")]
    public async Task<IActionResult> CloseRequest(long id, CancellationToken ct)
    {
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (r.RequestedBy != UserId && !CanPurchase) return Forbidden();
        if (r.Status != "RESPONDED") return Bad("Chỉ đóng được yêu cầu đã phản hồi.");
        r.Status = "CLOSED"; r.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Ok(OkData(RequestView(r)));
    }

    [HttpPost("requests/{id:long}/attachments")]
    [RequestSizeLimit(20_000_000)]
    public async Task<IActionResult> Upload(long id, IFormFile file, CancellationToken ct)
    {
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (r.Status != "DRAFT" || (r.RequestedBy != UserId && !Admin)) return Forbidden();
        if (file == null || file.Length == 0 || file.Length > 10_000_000) return Bad("Tệp phải có dung lượng từ 1 byte đến 10 MB.");
        var ext = Path.GetExtension(file.FileName).ToLowerInvariant();
        if (!new[] { ".pdf", ".png", ".jpg", ".jpeg", ".docx", ".xlsx" }.Contains(ext)) return Bad("Định dạng tệp không được hỗ trợ.");
        var storage = Guid.NewGuid().ToString("N") + ext;
        var folder = Path.Combine(env.ContentRootPath, "inquiry-attachments");
        Directory.CreateDirectory(folder);
        await using (var output = System.IO.File.Create(Path.Combine(folder, storage))) await file.CopyToAsync(output, ct);
        var contentType = ext switch { ".pdf" => "application/pdf", ".png" => "image/png", ".jpg" or ".jpeg" => "image/jpeg", ".docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document", ".xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", _ => "application/octet-stream" };
        var attachment = new ItemInquiryAttachment { RequestId = id, FileName = Path.GetFileName(file.FileName), ContentType = contentType, StorageName = storage, Size = file.Length };
        db.ItemInquiryAttachments.Add(attachment);
        await db.SaveChangesAsync(ct);
        return Ok(OkData(attachment));
    }

    [HttpGet("requests/{id:long}/attachments/{attachmentId:long}")]
    public async Task<IActionResult> Download(long id, long attachmentId, CancellationToken ct)
    {
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (!CanRead(r)) return Forbidden();
        var a = r.Attachments.FirstOrDefault(x => x.Id == attachmentId);
        if (a == null) return NotFound();
        var path = Path.Combine(env.ContentRootPath, "inquiry-attachments", a.StorageName);
        if (!System.IO.File.Exists(path)) return NotFound();
        return PhysicalFile(path, a.ContentType, a.FileName);
    }

    [HttpDelete("requests/{id:long}/attachments/{attachmentId:long}")]
    public async Task<IActionResult> DeleteAttachment(long id, long attachmentId, CancellationToken ct)
    {
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (r.Status != "DRAFT" || (r.RequestedBy != UserId && !Admin)) return Forbidden();
        var a = r.Attachments.FirstOrDefault(x => x.Id == attachmentId);
        if (a == null) return NotFound();
        db.ItemInquiryAttachments.Remove(a);
        await db.SaveChangesAsync(ct);
        var path = Path.Combine(env.ContentRootPath, "inquiry-attachments", a.StorageName);
        if (System.IO.File.Exists(path)) System.IO.File.Delete(path);
        return Ok(OkData(new { a.Id }));
    }

    public record InquiryInput(long SupplierId, List<InquiryItemInput> Items, List<long>? AttachmentIds);
    public record InquiryItemInput(long RequestItemId, string? Sku, string Designation, string? ItemDescription, long BrandId, string? Category, string? Com, string? Unit, decimal Quantity, string? LeadTime, string? Note, string? Spec, decimal BuyingPrice, decimal Vat, decimal Tax);
    private static bool ValidInquiryItems(List<InquiryItemInput>? items) => items is { Count: > 0 } && items.All(x => x.RequestItemId > 0 && x.BrandId > 0 && !string.IsNullOrWhiteSpace(x.Designation) && x.Quantity > 0 && x.BuyingPrice >= 0 && x.Vat is >= 0 and <= 100 && x.Tax is >= 0 and <= 100);
    private async Task<(List<SupplierInquiryItem>?, string?)> MakeItems(ItemInquiryRequest r, List<InquiryItemInput> input, CancellationToken ct)
    {
        var brands = await CatalogGet("brands", ct);
        if (brands == null || brands.Value.ValueKind != JsonValueKind.Array) return (null, "Không tải được danh sách brand.");
        var result = new List<SupplierInquiryItem>();
        foreach (var x in input)
        {
            if (!r.Items.Any(i => i.Id == x.RequestItemId) || result.Any(i => i.RequestItemId == x.RequestItemId)) return (null, "Item không thuộc yêu cầu hoặc bị lặp.");
            var brand = brands.Value.EnumerateArray().FirstOrDefault(b => b.GetProperty("id").GetInt64() == x.BrandId);
            if (brand.ValueKind == JsonValueKind.Undefined) return (null, "Brand đã chọn không tồn tại.");
            result.Add(new SupplierInquiryItem { RequestItemId = x.RequestItemId, Sku = x.Sku?.Trim(), Designation = x.Designation.Trim(), ItemDescription = x.ItemDescription, BrandId = x.BrandId, BrandName = brand.GetProperty("name").GetString() ?? "", Category = x.Category, Com = x.Com, Unit = x.Unit, Quantity = x.Quantity, LeadTime = x.LeadTime, Note = x.Note, Spec = x.Spec, BuyingPrice = x.BuyingPrice, Vat = x.Vat, Tax = x.Tax });
        }
        return (result, null);
    }
    private object InquiryView(SupplierInquiry i) => new { i.Id, i.RequestId, i.Code, i.SupplierId, i.SupplierCode, i.SupplierName, i.SupplierEmail, i.CreatedBy, i.Status, i.EmailJobId, i.SentAt, i.CompletedAt, i.CreatedAt, i.UpdatedAt, i.Items, attachment_ids = i.Attachments.Select(x => x.RequestAttachmentId) };

    [HttpGet("supplier-inquiries")]
    public async Task<IActionResult> AllInquiries(CancellationToken ct)
    {
        if (!CanPurchase) return Forbidden();
        var data = await db.SupplierInquiries.Join(db.ItemInquiryRequests, i => i.RequestId, r => r.Id,
            (i, r) => new { i.Id, i.RequestId, i.Code, i.SupplierName, i.Status, i.CreatedAt, i.UpdatedAt, request_code = r.Code, r.CustomerName })
            .OrderByDescending(x => x.Id).ToListAsync(ct);
        return Ok(OkData(data));
    }

    [HttpGet("requests/{id:long}/supplier-inquiries")]
    public async Task<IActionResult> Inquiries(long id, CancellationToken ct)
    {
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (!CanPurchase) return Forbidden();
        var list = await db.SupplierInquiries.Where(x => x.RequestId == id).OrderByDescending(x => x.Id).Select(x => new { x.Id, x.Code, x.SupplierName, x.Status, x.CreatedAt }).ToListAsync(ct);
        return Ok(OkData(list));
    }

    [HttpPost("requests/{id:long}/supplier-inquiries")]
    public async Task<IActionResult> CreateInquiry(long id, [FromBody] InquiryInput input, CancellationToken ct)
    {
        if (!CanPurchase) return Forbidden();
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (r.Status is not ("WAITING" or "IN_PROGRESS")) return Bad("Yêu cầu chưa được gửi hoặc đã kết thúc.");
        if (!ValidInquiryItems(input.Items)) return Bad("Thông tin item hoặc giá trị VAT/tax không hợp lệ.");
        var (items, error) = await MakeItems(r, input.Items, ct);
        if (error != null) return Bad(error);
        var supplier = await CatalogGet($"suppliers/{input.SupplierId}", ct);
        if (supplier == null) return Bad("Nhà cung cấp không tồn tại.");
        var s = supplier.Value;
        var email = s.TryGetProperty("email", out var e) ? e.GetString() : null;
        if (string.IsNullOrWhiteSpace(email) && s.TryGetProperty("contact_email", out e)) email = e.GetString();
        if (string.IsNullOrWhiteSpace(email)) return Bad("Nhà cung cấp chưa có email.");
        var attachmentIds = input.AttachmentIds?.Distinct().ToList() ?? [];
        if (attachmentIds.Any(a => !r.Attachments.Any(x => x.Id == a))) return Bad("Tệp đính kèm không thuộc yêu cầu.");
        var code = s.GetProperty("code").GetString() ?? input.SupplierId.ToString();
        var i = new SupplierInquiry { RequestId = id, Code = await UniqueCode(code, false, ct), SupplierId = input.SupplierId, SupplierCode = code, SupplierName = s.GetProperty("name").GetString() ?? "", SupplierEmail = email!, CreatedBy = UserId, Items = items!, Attachments = attachmentIds.Select(a => new SupplierInquiryAttachment { RequestAttachmentId = a }).ToList() };
        db.SupplierInquiries.Add(i);
        r.Status = "IN_PROGRESS"; r.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Ok(OkData(InquiryView(i)));
    }

    [HttpGet("supplier-inquiries/{id:long}")]
    public async Task<IActionResult> InquiryById(long id, CancellationToken ct)
    {
        if (!CanPurchase) return Forbidden();
        var i = await FindInquiry(id, ct);
        if (i != null) await RefreshSentStatus(i, ct);
        return i == null ? NotFound() : Ok(OkData(InquiryView(i)));
    }

    [HttpPut("supplier-inquiries/{id:long}")]
    public async Task<IActionResult> UpdateInquiry(long id, [FromBody] InquiryInput input, CancellationToken ct)
    {
        if (!CanPurchase) return Forbidden();
        var i = await FindInquiry(id, ct);
        if (i == null) return NotFound();
        if (i.Status != "DRAFT") return Bad("Chỉ sửa được phiếu hỏi hàng nháp.");
        if (i.EmailJobId != null) return Bad("Phiếu đã vào hàng đợi email; không thể sửa nội dung.");
        if (input.SupplierId != i.SupplierId) return Bad("Không thể đổi nhà cung cấp sau khi cấp mã phiếu.");
        if (!ValidInquiryItems(input.Items)) return Bad("Thông tin item không hợp lệ.");
        var r = await FindRequest(i.RequestId, ct);
        if (r == null) return NotFound();
        var (items, error) = await MakeItems(r, input.Items, ct);
        if (error != null) return Bad(error);
        var attachmentIds = input.AttachmentIds?.Distinct().ToList() ?? [];
        if (attachmentIds.Any(a => !r.Attachments.Any(x => x.Id == a))) return Bad("Tệp đính kèm không thuộc yêu cầu.");
        db.SupplierInquiryItems.RemoveRange(i.Items); db.SupplierInquiryAttachments.RemoveRange(i.Attachments);
        i.Items = items!; i.Attachments = attachmentIds.Select(a => new SupplierInquiryAttachment { RequestAttachmentId = a }).ToList(); i.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Ok(OkData(InquiryView(i)));
    }

    [HttpPost("supplier-inquiries/{id:long}/send")]
    public async Task<IActionResult> SendInquiry(long id, CancellationToken ct)
    {
        if (!CanPurchase) return Forbidden();
        var i = await FindInquiry(id, ct);
        if (i == null) return NotFound();
        if (i.Status != "DRAFT") return Bad("Chỉ gửi được phiếu nháp.");
        if (i.EmailJobId != null)
        {
            var previous = await Client().GetAsync($"{Base("Notification", "http://localhost:8006")}/api/v1/notifications/email/logs/{i.EmailJobId}", ct);
            if (!previous.IsSuccessStatusCode) return StatusCode(502);
            using var previousDoc = JsonDocument.Parse(await previous.Content.ReadAsStringAsync(ct));
            if (previousDoc.RootElement.GetProperty("data").GetProperty("status").GetString() != "FAILED") return Bad("Email đang được gửi hoặc đã gửi. Hãy kiểm tra trạng thái email.");
        }
        var r = await FindRequest(i.RequestId, ct);
        if (r == null) return NotFound();
        var files = new List<object>();
        foreach (var link in i.Attachments)
        {
            var a = r.Attachments.First(x => x.Id == link.RequestAttachmentId);
            var path = Path.Combine(env.ContentRootPath, "inquiry-attachments", a.StorageName);
            if (!System.IO.File.Exists(path)) return Bad($"Thiếu tệp {a.FileName}.");
            files.Add(new { filename = a.FileName, content = Convert.ToBase64String(await System.IO.File.ReadAllBytesAsync(path, ct)), encoding = "base64", contentType = a.ContentType });
        }
        var body = new StringBuilder($"Kính gửi {i.SupplierName},\n\nVui lòng phản hồi thông tin hỏi hàng {i.Code}:\n");
        foreach (var item in i.Items) body.AppendLine($"- {item.Designation} ({item.Sku ?? "chưa có SKU"}), SL {item.Quantity} {item.Unit}; brand {item.BrandName}; category {item.Category}; COM {item.Com}; mô tả {item.ItemDescription}; spec {item.Spec}; lead time {item.LeadTime}; ghi chú {item.Note}; giá mua dự kiến {item.BuyingPrice:N2}; VAT {item.Vat}%; tax {item.Tax}%");
        body.AppendLine("\nTrân trọng,");
        var payload = JsonSerializer.Serialize(new { to = i.SupplierEmail, subject = $"Hỏi hàng {i.Code}", text = body.ToString(), attachments = files });
        var response = await Client().PostAsync($"{Base("Notification", "http://localhost:8006")}/api/v1/notifications/email/send", new StringContent(payload, Encoding.UTF8, "application/json"), ct);
        if (!response.IsSuccessStatusCode) return StatusCode(502, new { success = false, error = new { message = "Notification Service chưa nhận email. Có thể gửi lại." } });
        using var doc = JsonDocument.Parse(await response.Content.ReadAsStringAsync(ct));
        i.EmailJobId = doc.RootElement.GetProperty("data").GetProperty("id").GetString();
        i.UpdatedAt = DateTime.UtcNow;
        await db.SaveChangesAsync(ct);
        return Accepted(OkData(InquiryView(i)));
    }

    [HttpPost("supplier-inquiries/{id:long}/sync-email")]
    public async Task<IActionResult> SyncEmail(long id, CancellationToken ct)
    {
        if (!CanPurchase) return Forbidden();
        var i = await FindInquiry(id, ct);
        if (i == null) return NotFound();
        if (i.Status == "SENT") return Ok(OkData(InquiryView(i)));
        if (i.EmailJobId == null) return Bad("Phiếu chưa được đưa vào hàng đợi email.");
        var response = await Client().GetAsync($"{Base("Notification", "http://localhost:8006")}/api/v1/notifications/email/logs/{i.EmailJobId}", ct);
        if (!response.IsSuccessStatusCode) return StatusCode(502);
        using var doc = JsonDocument.Parse(await response.Content.ReadAsStringAsync(ct));
        var job = doc.RootElement.GetProperty("data");
        var status = job.GetProperty("status").GetString();
        if (status == "SENT") { i.Status = "SENT"; i.SentAt = DateTime.UtcNow; i.UpdatedAt = DateTime.UtcNow; await db.SaveChangesAsync(ct); }
        return Ok(OkData(new { inquiry = InquiryView(i), email_status = status, email_error = job.TryGetProperty("error", out var error) ? error.ToString() : null }));
    }

    [HttpPost("supplier-inquiries/{id:long}/complete")]
    public async Task<IActionResult> CompleteInquiry(long id, CancellationToken ct)
    {
        if (!CanPurchase) return Forbidden();
        var i = await FindInquiry(id, ct);
        if (i == null) return NotFound();
        await RefreshSentStatus(i, ct);
        if (i.Status is not ("SENT" or "COMPLETED")) return Bad("Email hỏi hàng phải gửi thành công trước khi hoàn thành.");
        var wasCompleted = i.Status == "COMPLETED";
        var request = await FindRequest(i.RequestId, ct);
        if (request == null) return NotFound();
        foreach (var item in i.Items)
        {
            if (item.BrandId <= 0) return Bad("Mỗi item phải chọn Brand.");
            if (string.IsNullOrWhiteSpace(item.Sku)) item.Sku = $"IQP-{i.RequestId}-{item.Id}";
            var brand = await CatalogGet($"brands/{item.BrandId}", ct);
            if (brand == null || !brand.Value.TryGetProperty("price_index", out var priceIndex) || priceIndex.ValueKind == JsonValueKind.Null) return Bad("Brand chưa có price index.");
            var index = priceIndex.GetDecimal();
            if (index <= 0) return Bad("Price index phải lớn hơn 0.");
            item.PriceIndex = index;
            item.GrossPrice = decimal.Round(item.BuyingPrice * (1 + (item.Vat + item.Tax) / 100) * index, 2, MidpointRounding.AwayFromZero);
        }
        foreach (var item in i.Items)
        {
            var requestItem = request.Items.FirstOrDefault(x => x.Id == item.RequestItemId);
            if (requestItem == null) return Bad("Item hỏi hàng không thuộc yêu cầu.");
            if (requestItem.SelectedInquiryItemId.HasValue || (wasCompleted && requestItem.GrossPrice.HasValue)) continue;
            var (product, error) = await ApplyProduct(item, i.Code, ct);
            if (product == null) return StatusCode(502, new { success = false, error = new { message = $"Chưa cập nhật được Product cho SKU {item.Sku}.", detail = error } });
            requestItem.Sku = product.Value.GetProperty("sku").GetString();
            requestItem.GrossPrice = product.Value.GetProperty("gross_price").GetDecimal();
        }
        i.Status = "COMPLETED"; i.CompletedAt = DateTime.UtcNow; i.UpdatedAt = DateTime.UtcNow;
        request.UpdatedAt = DateTime.UtcNow;
        var responded = request.Status != "RESPONDED" && request.Items.All(x => x.GrossPrice.HasValue);
        if (responded) request.Status = "RESPONDED";
        await db.SaveChangesAsync(ct);
        if (responded) await outbox.SaveEventAsync("purchase.inquiry.responded", "ItemInquiryResponded", "ItemInquiryRequest", request.Id.ToString(), new { request_id = request.Id, request.Code, request.RequestedBy, request.AssigneeId, request.WatcherId, actor = new { id = UserId, name = User.Identity?.Name } }, ct: ct);
        return Ok(OkData(InquiryView(i)));
    }

    public record SelectResultInput(long InquiryItemId);

    [HttpPost("requests/{id:long}/items/{itemId:long}/select-result")]
    public async Task<IActionResult> SelectResult(long id, long itemId, [FromBody] SelectResultInput input, CancellationToken ct)
    {
        if (!CanPurchase) return Forbidden();
        var r = await FindRequest(id, ct);
        if (r == null) return NotFound();
        if (r.Status is not ("IN_PROGRESS" or "RESPONDED")) return Bad("Yêu cầu không ở trạng thái đang xử lý.");
        var requestItem = r.Items.FirstOrDefault(x => x.Id == itemId);
        if (requestItem == null) return NotFound();
        if (requestItem.SelectedInquiryItemId.HasValue) return Bad("Item đã chọn kết quả; không thể ghi đè âm thầm.");
        var selected = await db.SupplierInquiryItems.Join(db.SupplierInquiries, x => x.InquiryId, i => i.Id, (x, i) => new { Item = x, Inquiry = i }).FirstOrDefaultAsync(x => x.Item.Id == input.InquiryItemId && x.Item.RequestItemId == itemId && x.Inquiry.RequestId == id && x.Inquiry.Status == "COMPLETED", ct);
        if (selected == null) return Bad("Kết quả không thuộc item hoặc phiếu hỏi hàng chưa hoàn thành.");
        var x = selected.Item;
        var payload = JsonSerializer.Serialize(new { reference = selected.Inquiry.Code, request_item_id = itemId, inquiry_item_id = x.Id, sku = x.Sku, designation = x.Designation, description = x.ItemDescription, brand_id = x.BrandId, category = x.Category, com = x.Com, unit = x.Unit, spec = x.Spec, buying_price = x.BuyingPrice, vat = x.Vat, tax = x.Tax, price_index = x.PriceIndex, gross_price = x.GrossPrice });
        var client = Client();
        client.DefaultRequestHeaders.Add("X-Internal-Service-Key", InternalKey());
        var response = await client.PostAsync($"{Base("Catalog", "http://localhost:8002")}/api/products/apply-inquiry-result", new StringContent(payload, Encoding.UTF8, "application/json"), ct);
        if (!response.IsSuccessStatusCode) return StatusCode(502, new { success = false, error = new { message = "Catalog chưa cập nhật được Product.", detail = await response.Content.ReadAsStringAsync(ct) } });
        using var productResult = JsonDocument.Parse(await response.Content.ReadAsStringAsync(ct));
        var product = productResult.RootElement.GetProperty("data");
        requestItem.Sku = product.GetProperty("sku").GetString();
        requestItem.SelectedInquiryItemId = x.Id; requestItem.GrossPrice = product.GetProperty("gross_price").GetDecimal();
        r.UpdatedAt = DateTime.UtcNow;
        if (r.Items.All(item => item.SelectedInquiryItemId.HasValue)) r.Status = "RESPONDED";
        await db.SaveChangesAsync(ct);
        if (r.Status == "RESPONDED") await outbox.SaveEventAsync("purchase.inquiry.responded", "ItemInquiryResponded", "ItemInquiryRequest", r.Id.ToString(), new { request_id = r.Id, r.Code, r.RequestedBy, r.AssigneeId, r.WatcherId, actor = new { id = UserId, name = User.Identity?.Name } }, ct: ct);
        return Ok(OkData(RequestView(r)));
    }
}
