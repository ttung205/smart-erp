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
/// Xử lý các sự kiện nghiệp vụ hướng sự kiện (Event-Driven Saga) với tính Idempotency và Atomic Invariants.
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
    public async Task HandleSalesOrderCreatedAsync(CloudEventEnvelope<SalesOrderCreatedPayload> envelope, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        var order = envelope.Payload;

        _logger.LogInformation("[{CorrelationId}] Bắt đầu xử lý SalesOrderCreated cho đơn hàng {OrderId} (EventId: {EventId})",
            envelope.CorrelationId, order.OrderId, envelope.EventId);

        // 1. Idempotency Check: Kiểm tra sự kiện đã từng được xử lý hay chưa
        var alreadyProcessed = await _context.ProcessedEvents
            .AnyAsync(p => p.EventId == envelope.EventId && p.ConsumerName == ConsumerName, ct);

        if (alreadyProcessed)
        {
            _logger.LogWarning("[{CorrelationId}] Sự kiện {EventId} đã được xử lý trước đó. Bỏ qua để đảm bảo Idempotency.",
                envelope.CorrelationId, envelope.EventId);
            return;
        }

        try
        {
            // 2. Chuyển đổi danh sách mặt hàng sang lệnh BatchReserve
            var reserveItems = order.Items.Select(item => new ReserveStockItem(
                WarehouseId: item.WarehouseId,
                ProductId: item.ProductId,
                Quantity: item.Quantity
            )).ToList();

            var batchCommand = new BatchReserveStockCommand(
                OrderId: order.OrderId,
                Items: reserveItems
            );

            // 3. Thực thi giữ kho nguyên tử & triệt tiêu Deadlock qua Resource Lock Ordering
            var reserveResults = await _stockManager.BatchReserveStockAsync(batchCommand, ct);

            // 4. Nếu thành công: Chuẩn bị payload StockReserved và ghi Outbox
            var reservationDetails = reserveResults.Select(r => new StockReservationDetailPayload
            {
                ProductId = r.ProductId,
                WarehouseId = r.WarehouseId,
                QuantityReserved = r.QuantityDelta,
                RemainingAvailable = r.Available
            }).ToList();

            var reservedPayload = new StockReservedPayload
            {
                OrderId = order.OrderId,
                ReservedAt = DateTime.UtcNow,
                Reservations = reservationDetails
            };

            await _outboxService.SaveEventAsync(
                routingKey: "inventory.stock.reserved",
                eventType: "StockReserved",
                aggregateType: "SalesOrder",
                aggregateId: order.OrderId,
                payload: reservedPayload,
                correlationId: envelope.CorrelationId,
                ct: ct
            );

            _logger.LogInformation("[{CorrelationId}] Giữ kho THÀNH CÔNG cho đơn hàng {OrderId}. Đã ghi Outbox StockReserved.",
                envelope.CorrelationId, order.OrderId);
        }
        catch (InsufficientStockException ex)
        {
            // 5. Nếu thất bại do thiếu tồn kho: Ghi Outbox StockReservationFailed (Saga Compensating Event)
            _logger.LogWarning("[{CorrelationId}] Giữ kho THẤT BẠI cho đơn hàng {OrderId}: {Message}. Ghi Outbox StockReservationFailed.",
                envelope.CorrelationId, order.OrderId, ex.Message);

            var failedPayload = new StockReservationFailedPayload
            {
                OrderId = order.OrderId,
                FailedAt = DateTime.UtcNow,
                Reason = ex.ErrorCode,
                FailedItems = new List<FailedStockItemPayload>
                {
                    new()
                    {
                        ProductId = ex.ProductId,
                        WarehouseId = ex.WarehouseId,
                        RequestedQuantity = ex.RequestedQuantity,
                        AvailableQuantity = ex.AvailableQuantity
                    }
                }
            };

            await _outboxService.SaveEventAsync(
                routingKey: "inventory.stock.reservation_failed",
                eventType: "StockReservationFailed",
                aggregateType: "SalesOrder",
                aggregateId: order.OrderId,
                payload: failedPayload,
                correlationId: envelope.CorrelationId,
                ct: ct
            );
        }

        // 6. Ghi nhận đã xử lý sự kiện thành công vào bảng processed_events
        _context.ProcessedEvents.Add(new ProcessedEvent
        {
            EventId = envelope.EventId,
            ConsumerName = ConsumerName,
            ProcessedAt = DateTime.UtcNow
        });

        await _context.SaveChangesAsync(ct);
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

        // 2. Nhập kho cộng dồn tồn kho vật lý và tự động ghi thẻ kho cho từng sản phẩm
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

        // 3. Đánh dấu Idempotency
        _context.ProcessedEvents.Add(new ProcessedEvent
        {
            EventId = envelope.EventId,
            ConsumerName = ConsumerName,
            ProcessedAt = DateTime.UtcNow
        });

        await _context.SaveChangesAsync(ct);

        _logger.LogInformation("[{CorrelationId}] Xử lý GoodsReceived THÀNH CÔNG cho phiếu {ReceiptId}. Đã cập nhật tồn OnHand và Thẻ kho.",
            envelope.CorrelationId, receipt.ReceiptId);
    }
}
