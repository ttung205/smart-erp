using Inventory.Application.Common.Commands;
using Inventory.Application.Common.Events;
using Inventory.Application.Interfaces;
using Inventory.Domain.Entities;
using Inventory.Domain.Exceptions;
using Inventory.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;

namespace Inventory.Infrastructure.Services;

/// <summary>
/// Xử lý sự kiện từ purchase-service (GoodsReceived) và catalog-service (ProductCreated), idempotent theo event_id.
/// Giữ hàng cho chứng từ bán hàng KHÔNG đi qua event: sales gọi REST POST /stocks/reserve/batch (architecture.md §8).
/// </summary>
public class EventConsumerHandler : IEventConsumerHandler
{
    private readonly InventoryDbContext _context;
    private readonly IInventoryStockManager _stockManager;
    private readonly IOutboxService _outboxService;
    private readonly ILogger<EventConsumerHandler> _logger;
    private const string ConsumerName = "inventory-service";

    public EventConsumerHandler(
        InventoryDbContext context,
        IInventoryStockManager stockManager,
        IOutboxService outboxService,
        ILogger<EventConsumerHandler> logger)
    {
        _context = context ?? throw new ArgumentNullException(nameof(context));
        _stockManager = stockManager ?? throw new ArgumentNullException(nameof(stockManager));
        _outboxService = outboxService ?? throw new ArgumentNullException(nameof(outboxService));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
    }

    /// <inheritdoc />
    public async Task HandleGoodsReceivedAsync(CloudEventEnvelope<GoodsReceivedPayload> envelope, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        var receipt = envelope.Payload;

        _logger.LogInformation("[{CorrelationId}] Bắt đầu xử lý GoodsReceived cho phiếu nhập {ReceiptId} từ PO {PoId}",
            envelope.CorrelationId, receipt.ReceiptId, receipt.PoId);

        // 1. Idempotency Check
        var alreadyProcessed = await _context.ProcessedEvents
            .AnyAsync(p => p.EventId == envelope.EventId && p.ConsumerName == ConsumerName, ct);

        if (alreadyProcessed)
        {
            _logger.LogWarning("[{CorrelationId}] Sự kiện GoodsReceived {EventId} đã được xử lý trước đó. Bỏ qua.",
                envelope.CorrelationId, envelope.EventId);
            return;
        }

        // 2+3. Nhập kho và đánh dấu idempotency trong MỘT transaction: crash giữa chừng thì rollback hết,
        // message được giao lại và xử lý đúng một lần (không cộng tồn hai lần).
        await using var tx = await _context.Database.BeginTransactionAsync(ct);
        try
        {
            foreach (var item in receipt.Items)
            {
                var receiveCmd = new ReceiveStockCommand(
                    ReferenceDoc: receipt.ReceiptId,
                    WarehouseId: receipt.WarehouseId,
                    ProductId: item.ProductId,
                    Quantity: item.QuantityReceived,
                    UserId: receipt.ReceivedBy
                );

                await _stockManager.ReceiveStockAsync(receiveCmd, ct);
            }

            _context.ProcessedEvents.Add(new ProcessedEvent
            {
                EventId = envelope.EventId,
                ConsumerName = ConsumerName,
                ProcessedAt = DateTime.UtcNow
            });

            await _context.SaveChangesAsync(ct);
            await tx.CommitAsync(ct);
        }
        catch (DbUpdateException)
        {
            if (!await WasProcessedConcurrentlyAsync(envelope.EventId, ct)) throw;

            // Bản sao của cùng event vừa được xử lý ở luồng khác (khóa chính processed_events): transaction này đã rollback.
            _context.ChangeTracker.Clear();
            _logger.LogWarning("[{CorrelationId}] Sự kiện GoodsReceived {EventId} vừa được xử lý đồng thời. Bỏ qua.",
                envelope.CorrelationId, envelope.EventId);
            return;
        }

        _logger.LogInformation("[{CorrelationId}] Xử lý GoodsReceived THÀNH CÔNG cho phiếu {ReceiptId}. Đã cập nhật tồn OnHand và Thẻ kho.",
            envelope.CorrelationId, receipt.ReceiptId);
    }

    /// <inheritdoc />
    public async Task HandleProductCreatedAsync(CloudEventEnvelope<ProductCreatedPayload> envelope, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        var product = envelope.Payload;

        _logger.LogInformation("[{CorrelationId}] Bắt đầu xử lý ProductCreated cho sản phẩm {ProductId} (SKU: {Sku}, Origin: {Origin})",
            envelope.CorrelationId, product.ProductId, product.Sku, product.Origin);

        // 1. Idempotency Check
        var alreadyProcessed = await _context.ProcessedEvents
            .AnyAsync(p => p.EventId == envelope.EventId && p.ConsumerName == ConsumerName, ct);

        if (alreadyProcessed)
        {
            _logger.LogWarning("[{CorrelationId}] Sự kiện ProductCreated {EventId} đã được xử lý trước đó. Bỏ qua.",
                envelope.CorrelationId, envelope.EventId);
            return;
        }

        // 2. Lấy danh sách kho đang hoạt động
        var warehouses = await _context.Warehouses
            .Where(w => w.IsActive)
            .ToListAsync(ct);

        if (warehouses.Count == 0)
        {
            _logger.LogWarning("[{CorrelationId}] Không tìm thấy kho nào đang hoạt động để khởi tạo tồn kho cho sản phẩm {ProductId}.",
                envelope.CorrelationId, product.ProductId);
        }

        var origin = string.IsNullOrWhiteSpace(product.Origin) ? "Việt Nam" : product.Origin.Trim();

        foreach (var warehouse in warehouses)
        {
            // Kiểm tra và tạo dòng tồn kho vật lý (OnHand = 0)
            var stockExists = await _context.InventoryStocks
                .AnyAsync(s => s.WarehouseId == warehouse.Id && s.ProductId == product.ProductId, ct);

            if (!stockExists)
            {
                _context.InventoryStocks.Add(new InventoryStock(
                    warehouseId: warehouse.Id,
                    productId: product.ProductId,
                    initialQuantity: 0,
                    origin: origin
                ));
            }

            // Kiểm tra và tạo dòng tồn kho khả dụng (Available = 0)
            var reservationExists = await _context.InventoryStockReservations
                .AnyAsync(r => r.WarehouseId == warehouse.Id && r.ProductId == product.ProductId, ct);

            if (!reservationExists)
            {
                _context.InventoryStockReservations.Add(new InventoryStockReservation(
                    warehouseId: warehouse.Id,
                    productId: product.ProductId,
                    initialQuantity: 0
                ));
            }
        }

        // 3. Đánh dấu Idempotency
        _context.ProcessedEvents.Add(new ProcessedEvent
        {
            EventId = envelope.EventId,
            ConsumerName = ConsumerName,
            ProcessedAt = DateTime.UtcNow
        });

        try
        {
            await _context.SaveChangesAsync(ct);
        }
        catch (DbUpdateException)
        {
            if (!await WasProcessedConcurrentlyAsync(envelope.EventId, ct)) throw;

            _context.ChangeTracker.Clear();
            _logger.LogWarning("[{CorrelationId}] Sự kiện ProductCreated {EventId} vừa được xử lý đồng thời. Bỏ qua.",
                envelope.CorrelationId, envelope.EventId);
            return;
        }

        _logger.LogInformation("[{CorrelationId}] Khởi tạo tồn kho THÀNH CÔNG cho sản phẩm {ProductId} (SKU: {Sku}) tại {WarehouseCount} kho.",
            envelope.CorrelationId, product.ProductId, product.Sku, warehouses.Count);
    }

    /// <summary>Sau khi lưu lỗi: event đã có bản ghi processed_events do luồng khác commit trước?</summary>
    private async Task<bool> WasProcessedConcurrentlyAsync(string eventId, CancellationToken ct)
    {
        _context.ChangeTracker.Clear();
        return await _context.ProcessedEvents.AsNoTracking()
            .AnyAsync(p => p.EventId == eventId && p.ConsumerName == ConsumerName, ct);
    }
}
