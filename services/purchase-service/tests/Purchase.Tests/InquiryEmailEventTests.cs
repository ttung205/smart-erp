using System.Net;
using System.Security.Claims;
using System.Text.Json;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.FileProviders;
using Purchase.Api.Controllers;
using Purchase.Domain.Entities;
using Purchase.Infrastructure.Persistence;
using Purchase.Infrastructure.Services;
using Xunit;

namespace Purchase.Tests;

/// <summary>
/// Gửi email hỏi hàng cho NCC đi theo event: Purchase ghi SupplierInquiryEmailRequested vào outbox cùng lúc lưu mã job,
/// không gọi notification-service để gửi. Tệp đính kèm chỉ là tham chiếu.
/// </summary>
public class InquiryEmailEventTests : IDisposable
{
    private readonly PurchaseDbContext _db;
    private readonly Microsoft.Data.Sqlite.SqliteConnection _connection;
    private readonly string _contentRoot = Path.Combine(Path.GetTempPath(), "purchase-mail-" + Guid.NewGuid().ToString("N"));
    private readonly FakeHttpFactory _http = new();

    public InquiryEmailEventTests()
    {
        (_db, _connection) = TestDbContextFactory.CreateInMemoryDbContext();
        Directory.CreateDirectory(Path.Combine(_contentRoot, "inquiry-attachments"));
    }

    public void Dispose()
    {
        _db.Dispose();
        _connection.Dispose();
        try { Directory.Delete(_contentRoot, true); } catch (IOException) { }
    }

    private ItemInquiriesController Controller()
    {
        var config = new ConfigurationBuilder().AddInMemoryCollection(new Dictionary<string, string?>
        {
            ["Services:InternalKey"] = "internal-key", ["Services:Notification"] = "http://notification.test"
        }).Build();
        var claims = new ClaimsIdentity([new Claim(ClaimTypes.NameIdentifier, "9"), new Claim(ClaimTypes.Name, "Mua hàng"),
            new Claim("permission", "item-inquiry.process")], "test");
        return new ItemInquiriesController(_db, new OutboxService(_db), _http, config, new FakeEnv(_contentRoot))
        {
            ControllerContext = new ControllerContext { HttpContext = new DefaultHttpContext { User = new ClaimsPrincipal(claims) } }
        };
    }

    private async Task<SupplierInquiry> SeedInquiryAsync(string? emailJobId = null, DateTime? updatedAt = null, bool withAttachment = true)
    {
        var request = new ItemInquiryRequest { Code = "IQ-RE-1", CustomerId = 1, CustomerCode = "C1", CustomerName = "Khách", RequestedBy = 3, AssigneeId = 9, Status = "OPEN" };
        var attachment = new ItemInquiryAttachment { FileName = "bang-ve.pdf", ContentType = "application/pdf", StorageName = "stored.pdf", Size = 4 };
        request.Attachments.Add(attachment);
        _db.ItemInquiryRequests.Add(request);
        await _db.SaveChangesAsync();
        if (withAttachment) await File.WriteAllBytesAsync(Path.Combine(_contentRoot, "inquiry-attachments", "stored.pdf"), [1, 2, 3, 4]);

        var inquiry = new SupplierInquiry
        {
            RequestId = request.Id, Code = "IQ-RE-1-A", SupplierId = 5, SupplierCode = "S5", SupplierName = "Công ty NCC",
            SupplierEmail = "ncc@example.com", CreatedBy = 9, EmailJobId = emailJobId, UpdatedAt = updatedAt ?? DateTime.UtcNow,
            Items = [new SupplierInquiryItem { RequestItemId = 1, Designation = "Máy lạnh", Quantity = 2, BrandName = "LG" }],
            Attachments = [new SupplierInquiryAttachment { RequestAttachmentId = attachment.Id }]
        };
        _db.SupplierInquiries.Add(inquiry);
        await _db.SaveChangesAsync();
        return inquiry;
    }

    [Fact]
    public async Task Send_records_email_requested_event_and_job_id_together_without_calling_notification()
    {
        var inquiry = await SeedInquiryAsync();

        var result = await Controller().SendInquiry(inquiry.Id, CancellationToken.None);

        Assert.IsType<AcceptedResult>(result);
        Assert.Empty(_http.Requests); // không còn gọi POST /email/send

        var saved = await _db.SupplierInquiries.AsNoTracking().SingleAsync();
        Assert.False(string.IsNullOrEmpty(saved.EmailJobId));
        var evt = await _db.OutboxEvents.AsNoTracking().SingleAsync();
        Assert.Equal("purchase.inquiry.email_requested", evt.RoutingKey);
        Assert.Equal("PENDING", evt.Status);

        using var doc = JsonDocument.Parse(evt.Payload);
        var payload = doc.RootElement.GetProperty("payload");
        Assert.Equal("SupplierInquiryEmailRequested", doc.RootElement.GetProperty("event_type").GetString());
        var email = payload.GetProperty("email");
        Assert.Equal(saved.EmailJobId, email.GetProperty("job_id").GetString());
        Assert.Equal("ncc@example.com", email.GetProperty("to")[0].GetString());
        Assert.Equal("Hỏi hàng IQ-RE-1-A", email.GetProperty("subject").GetString());
        Assert.Contains("Máy lạnh", email.GetProperty("body").GetString());

        var file = payload.GetProperty("attachments")[0];
        Assert.Equal("purchase", file.GetProperty("source").GetString());
        Assert.Equal("bang-ve.pdf", file.GetProperty("filename").GetString());
        Assert.False(file.TryGetProperty("content", out _)); // event không chứa nội dung tệp
    }

    [Fact]
    public async Task Send_fails_without_writing_an_event_when_an_attachment_file_is_missing()
    {
        var inquiry = await SeedInquiryAsync(withAttachment: false);

        var result = await Controller().SendInquiry(inquiry.Id, CancellationToken.None);

        Assert.IsType<BadRequestObjectResult>(result);
        Assert.Empty(await _db.OutboxEvents.ToListAsync());
        Assert.Null((await _db.SupplierInquiries.AsNoTracking().SingleAsync()).EmailJobId);
    }

    [Fact]
    public async Task Send_is_refused_while_the_previous_email_event_is_still_pending()
    {
        var inquiry = await SeedInquiryAsync(emailJobId: "job-1");
        _http.Respond(HttpStatusCode.NotFound);

        var result = await Controller().SendInquiry(inquiry.Id, CancellationToken.None);

        Assert.IsType<BadRequestObjectResult>(result);
        Assert.Empty(await _db.OutboxEvents.ToListAsync());
    }

    [Fact]
    public async Task Send_is_allowed_again_when_the_previous_event_was_never_processed_for_a_long_time()
    {
        var inquiry = await SeedInquiryAsync(emailJobId: "lost-job", updatedAt: DateTime.UtcNow.AddMinutes(-30));
        _http.Respond(HttpStatusCode.NotFound);

        var result = await Controller().SendInquiry(inquiry.Id, CancellationToken.None);

        Assert.IsType<AcceptedResult>(result);
        var saved = await _db.SupplierInquiries.AsNoTracking().SingleAsync();
        Assert.NotEqual("lost-job", saved.EmailJobId);
        Assert.Single(await _db.OutboxEvents.ToListAsync());
    }

    [Fact]
    public async Task Sync_reports_requested_while_notification_has_no_record_yet()
    {
        var inquiry = await SeedInquiryAsync(emailJobId: "job-1");
        _http.Respond(HttpStatusCode.NotFound);

        var result = await Controller().SyncEmail(inquiry.Id, CancellationToken.None);

        var ok = Assert.IsType<OkObjectResult>(result);
        Assert.Contains("REQUESTED", JsonSerializer.Serialize(ok.Value));
        Assert.Equal("DRAFT", (await _db.SupplierInquiries.AsNoTracking().SingleAsync()).Status);
    }

    [Fact]
    public async Task Internal_attachment_endpoint_streams_the_stored_file()
    {
        var inquiry = await SeedInquiryAsync();
        var attachmentId = (await _db.ItemInquiryAttachments.AsNoTracking().SingleAsync()).Id;

        var result = await new InternalAttachmentsController(_db, new FakeEnv(_contentRoot)).Download(attachmentId, CancellationToken.None);

        var file = Assert.IsType<PhysicalFileResult>(result);
        Assert.Equal("application/pdf", file.ContentType);
        Assert.Equal("bang-ve.pdf", file.FileDownloadName);
        Assert.IsType<NotFoundResult>(await new InternalAttachmentsController(_db, new FakeEnv(_contentRoot)).Download(999, CancellationToken.None));
        Assert.NotNull(inquiry);
    }

    private sealed class FakeEnv(string root) : IWebHostEnvironment
    {
        public string ApplicationName { get; set; } = "Purchase.Tests";
        public IFileProvider WebRootFileProvider { get; set; } = new NullFileProvider();
        public string WebRootPath { get; set; } = root;
        public string EnvironmentName { get; set; } = "Testing";
        public string ContentRootPath { get; set; } = root;
        public IFileProvider ContentRootFileProvider { get; set; } = new NullFileProvider();
    }

    private sealed class FakeHttpFactory : IHttpClientFactory
    {
        private HttpStatusCode _status = HttpStatusCode.OK;
        public List<HttpRequestMessage> Requests { get; } = [];

        public void Respond(HttpStatusCode status) => _status = status;

        public HttpClient CreateClient(string name) => new(new Handler(this));

        private sealed class Handler(FakeHttpFactory owner) : HttpMessageHandler
        {
            protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken ct)
            {
                owner.Requests.Add(request);
                return Task.FromResult(new HttpResponseMessage(owner._status) { Content = new StringContent("{}") });
            }
        }
    }
}
