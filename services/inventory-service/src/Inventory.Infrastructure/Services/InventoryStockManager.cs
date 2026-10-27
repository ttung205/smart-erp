using Inventory.Application.Common;
using Inventory.Application.Common.Commands;
using Inventory.Application.Interfaces;
using Inventory.Domain.Entities;
using Inventory.Domain.Exceptions;
using Inventory.Domain.Extensions;
using Inventory.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Infrastructure.Services;

/// <summary>
/// Quản lý biến động 2 tầng tồn kho: Tồn kho thực tế (Physical) và Tồn kho khả dụng (Reservation).
/// Tự động cập nhật số dư và ghi sổ cái giao dịch bất biến tương ứng.
///
/// Mọi thay đổi số dư dùng câu UPDATE nguyên tử có điều kiện (quantity = quantity + delta WHERE quantity + delta >= 0),
/// không đọc lên bộ nhớ rồi ghi lại, nên hai thao tác đồng thời không ghi đè lẫn nhau và không bao giờ âm kho.
/// Idempotent theo (document_type, document_id, warehouse_id, product_id): gọi lại cùng chứng từ không ghi sổ hai lần.
/// </summary>
public class InventoryStockManager : IInventoryStockManager
{
    // Giữ nguyên "SALES_ORDER" để chứng từ đã giữ chỗ trước đây vẫn được nhận ra khi gọi lại (idempotent)
    private const string ReserveDocumentType = "SALES_ORDER";
    private const string ReleaseDocumentType = "CANCEL_ORDER";

    private readonly InventoryDbContext _context;

    public InventoryStockManager(InventoryDbContext context)
    {
        _context = context ?? throw new ArgumentNullException(nameof(context));
    }

    /// <summary>
    /// Thay đổi số dư tồn kho thực tế và tự động ghi sổ cái giao dịch thực tế.
    /// </summary>
    public async Task<StockOperationResult> ChangePhysicalStockAsync(ChangeStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        await using var tx = await _context.Database.BeginTransactionAsync(ct);
        var result = await ApplyPhysicalDeltaAsync(command, ct);
        await tx.CommitAsync(ct);

        return result;
    }

    /// <summary>
    /// Thay đổi số dư tồn kho khả dụng và tự động ghi sổ cái giao dịch khả dụng.
    /// </summary>
    public async Task<StockOperationResult> ChangeReservationStockAsync(ChangeStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        await using var tx = await _context.Database.BeginTransactionAsync(ct);
        var result = await ApplyReservationDeltaAsync(command, ct);
        await tx.CommitAsync(ct);

        return result;
    }

    /// <summary>
    /// Nhập kho: Tăng cả tồn thực tế và tồn khả dụng.
    /// </summary>
    public async Task<StockOperationResult> ReceiveStockAsync(ReceiveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        // Nếu caller (vd consumer GoodsReceived) đã mở transaction thì tham gia vào đó để nhập kho và đánh dấu
        // đã xử lý event cùng commit/rollback; không thì tự mở transaction như trước.
        var ownsTransaction = _context.Database.CurrentTransaction is null;
        await using var tx = ownsTransaction ? await _context.Database.BeginTransactionAsync(ct) : null;

        var physicalResult = await ApplyPhysicalDeltaAsync(new ChangeStockCommand(
            command.WarehouseId,
            command.ProductId,
            command.Quantity,
            "PURCHASE_RECEIPT",
            command.ReferenceDoc,
            "Nhập kho mua hàng (PO Receipt)"
        ), ct);

        await ApplyReservationDeltaAsync(new ChangeStockCommand(
            command.WarehouseId,
            command.ProductId,
            command.Quantity,
            "PURCHASE_RECEIPT",
            command.ReferenceDoc,
            "Tăng tồn khả dụng từ đơn nhập kho"
        ), ct);

        if (tx is not null) await tx.CommitAsync(ct);
        return physicalResult;
    }

    /// <summary>
    /// Giữ chỗ một mặt hàng cho chứng từ bán hàng: Trừ tồn khả dụng.
    /// </summary>
    public async Task<StockOperationResult> ReserveStockAsync(ReserveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        var results = await BatchReserveStockAsync(new BatchReserveStockCommand(
            command.OrderId,
            new[] { new ReserveStockItem(command.WarehouseId, command.ProductId, command.Quantity) },
            command.ExpiresAt,
            command.UserId
        ), ct);

        return results[0];
    }

    /// <summary>
    /// Giữ chỗ cả chứng từ bán hàng trong MỘT transaction: đủ hàng cho mọi dòng mới giữ,
    /// thiếu một dòng thì rollback toàn bộ (InsufficientStockException → 409).
    /// Dòng trùng sản phẩm/kho được gộp; khóa theo thứ tự (kho, sản phẩm) để tránh deadlock.
    /// Gọi lại cùng chứng từ: dòng đã giữ trước đó được bỏ qua, chỉ giữ phần còn thiếu.
    /// </summary>
    public async Task<IReadOnlyList<StockOperationResult>> BatchReserveStockAsync(BatchReserveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        try
        {
            return await BatchReserveOnceAsync(command, ct);
        }
        catch (Exception ex) when (ex is DbUpdateException or InsufficientStockException)
        {
            // Hai request giữ hàng cùng chứng từ chạy song song (vd Sales retry khi lần đầu chưa trả lời):
            // request về sau bị rollback, do đụng unique index của sổ cái hoặc do request kia vừa lấy hết hàng.
            // Nếu chứng từ đã được giữ thì đọc lại để trả kết quả idempotent; còn lại là hết hàng thật.
            _context.ChangeTracker.Clear();
            if (ex is InsufficientStockException && !await IsDocumentReservedAsync(command.OrderId, ct))
            {
                throw;
            }

            return await BatchReserveOnceAsync(command, ct);
        }
    }

    private Task<bool> IsDocumentReservedAsync(string documentId, CancellationToken ct) =>
        _context.InventoryStockReservationTransactions.AsNoTracking()
            .AnyAsync(t => t.DocumentType == ReserveDocumentType && t.DocumentId == documentId, ct);

    private async Task<IReadOnlyList<StockOperationResult>> BatchReserveOnceAsync(BatchReserveStockCommand command, CancellationToken ct)
    {

        var lines = command.Items
            .GroupBy(i => (i.WarehouseId, i.ProductId))
            .Select(g => new ReserveStockItem(g.Key.WarehouseId, g.Key.ProductId, g.Sum(i => i.Quantity)))
            .ToDeterministicLockOrder();

        if (lines.Count == 0 || lines.Any(l => l.Quantity <= 0))
        {
            throw new InvalidStockOperationException("Số lượng giữ chỗ của mỗi mặt hàng phải lớn hơn 0.");
        }

        await using var tx = await _context.Database.BeginTransactionAsync(ct);

        var results = new List<StockOperationResult>(lines.Count);
        foreach (var line in lines)
        {
            results.Add(await ApplyReservationDeltaAsync(new ChangeStockCommand(
                line.WarehouseId,
                line.ProductId,
                -line.Quantity,
                ReserveDocumentType,
                command.OrderId,
                "Giữ chỗ chứng từ bán hàng",
                command.UserId
            ), ct));
        }

        await tx.CommitAsync(ct);
        return results;
    }

    /// <summary>
    /// Hủy đơn hàng / Hoàn trả: Cộng lại tồn khả dụng.
    /// </summary>
    public async Task<StockOperationResult> ReleaseStockAsync(ReleaseStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        return await ChangeReservationStockAsync(new ChangeStockCommand(
            command.WarehouseId,
            command.ProductId,
            +command.Quantity,
            ReleaseDocumentType,
            command.OrderId,
            "Hoàn trả tồn khả dụng do hủy đơn"
        ), ct);
    }

    /// <summary>
    /// Hủy chứng từ bán hàng: nhả đúng số lượng đã giữ cho chứng từ (theo sổ cái giữ chỗ), trong một transaction.
    /// Idempotent: gọi lại không cộng hai lần; chứng từ chưa giữ được gì thì không làm gì.
    /// </summary>
    public async Task<IReadOnlyList<StockOperationResult>> ReleaseDocumentReservationAsync(string documentId, long? userId = null, CancellationToken ct = default)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(documentId);

        var reserved = (await _context.InventoryStockReservationTransactions
                .AsNoTracking()
                .Where(t => t.DocumentType == ReserveDocumentType && t.DocumentId == documentId)
                .ToListAsync(ct))
            .ToDeterministicLockOrder(t => t.WarehouseId, t => t.ProductId);

        await using var tx = await _context.Database.BeginTransactionAsync(ct);

        var results = new List<StockOperationResult>(reserved.Count);
        foreach (var line in reserved)
        {
            results.Add(await ApplyReservationDeltaAsync(new ChangeStockCommand(
                line.WarehouseId,
                line.ProductId,
                -line.QuantityDelta,
                ReleaseDocumentType,
                documentId,
                "Nhả giữ chỗ do hủy chứng từ bán hàng",
                userId
            ), ct));
        }

        await tx.CommitAsync(ct);
        return results;
    }

    public async Task<IReadOnlyList<OpenDocumentReservation>> GetOpenDocumentReservationsAsync(DateTime reservedFrom, DateTime reservedTo, CancellationToken ct = default)
    {
        var reserved = await _context.InventoryStockReservationTransactions
            .AsNoTracking()
            .Where(t => t.DocumentType == ReserveDocumentType && t.CreatedAt >= reservedFrom && t.CreatedAt <= reservedTo)
            .Select(t => new { t.DocumentId, t.WarehouseId, t.ProductId, t.QuantityDelta, t.CreatedAt })
            .ToListAsync(ct);
        if (reserved.Count == 0) return [];

        var documentIds = reserved.Select(t => t.DocumentId).Distinct().ToList();
        var released = (await _context.InventoryStockReservationTransactions
                .AsNoTracking()
                .Where(t => t.DocumentType == ReleaseDocumentType && documentIds.Contains(t.DocumentId))
                .Select(t => t.DocumentId)
                .Distinct()
                .ToListAsync(ct))
            .ToHashSet();

        return reserved
            .Where(t => !released.Contains(t.DocumentId))
            .GroupBy(t => t.DocumentId)
            .Select(g => new OpenDocumentReservation(
                g.Key,
                g.Min(t => t.CreatedAt),
                g.Select(t => new OpenDocumentReservationLine(t.WarehouseId, t.ProductId, -t.QuantityDelta)).ToList()))
            .OrderBy(d => d.ReservedAt)
            .ToList();
    }

    /// <summary>
    /// Ghi sổ xuất kho thực tế: Trừ tồn thực tế.
    /// </summary>
    public async Task<StockOperationResult> IssueStockAsync(IssueStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        return await ChangePhysicalStockAsync(new ChangeStockCommand(
            command.WarehouseId,
            command.ProductId,
            -command.Quantity,
            "DELIVERY_NOTE",
            command.ReferenceDoc,
            $"Xuất kho giao hàng (Đơn hàng: {command.OrderId ?? "N/A"})"
        ), ct);
    }

    public async Task<InventoryStock?> GetPhysicalStockAsync(long productId, long warehouseId, CancellationToken ct = default)
    {
        return await _context.InventoryStocks
            .AsNoTracking()
            .FirstOrDefaultAsync(s => s.ProductId == productId && s.WarehouseId == warehouseId, ct);
    }

    public async Task<InventoryStockReservation?> GetReservationStockAsync(long productId, long warehouseId, CancellationToken ct = default)
    {
        return await _context.InventoryStockReservations
            .AsNoTracking()
            .FirstOrDefaultAsync(r => r.ProductId == productId && r.WarehouseId == warehouseId, ct);
    }

    /// <summary>Ghi biến động tồn thực tế trong transaction hiện có của caller.</summary>
    private async Task<StockOperationResult> ApplyPhysicalDeltaAsync(ChangeStockCommand command, CancellationToken ct)
    {
        var existing = await _context.InventoryStockTransactions
            .AsNoTracking()
            .FirstOrDefaultAsync(t => t.DocumentType == command.DocumentType
                && t.DocumentId == command.DocumentId
                && t.WarehouseId == command.WarehouseId
                && t.ProductId == command.ProductId, ct);

        if (existing != null)
        {
            EnsureSameDelta(command, existing.QuantityDelta);
            return new StockOperationResult
            {
                WarehouseId = existing.WarehouseId,
                ProductId = existing.ProductId,
                QuantityOnHand = existing.BalanceAfter,
                OperationType = existing.DocumentType,
                QuantityDelta = existing.QuantityDelta,
                ReferenceDoc = existing.DocumentId,
                MovementId = existing.Id,
                Timestamp = existing.CreatedAt
            };
        }

        var stocks = _context.InventoryStocks
            .Where(s => s.WarehouseId == command.WarehouseId && s.ProductId == command.ProductId);

        if (!await stocks.AnyAsync(ct))
        {
            var stock = new InventoryStock(command.WarehouseId, command.ProductId, 0,
                !string.IsNullOrWhiteSpace(command.Origin) ? command.Origin : "Việt Nam");
            _context.InventoryStocks.Add(stock);
            await _context.SaveChangesAsync(ct);
            _context.Entry(stock).State = EntityState.Detached;
        }
        else if (!string.IsNullOrWhiteSpace(command.Origin))
        {
            await stocks.ExecuteUpdateAsync(s => s.SetProperty(x => x.Origin, command.Origin), ct);
        }

        var delta = command.QuantityDelta;
        var now = DateTime.UtcNow;
        var updated = await stocks
            .Where(s => delta >= 0 || s.Quantity >= -delta)
            .ExecuteUpdateAsync(s => s
                .SetProperty(x => x.Quantity, x => x.Quantity + delta)
                .SetProperty(x => x.UpdatedAt, now), ct);

        var balanceAfter = await stocks.AsNoTracking().Select(s => s.Quantity).FirstAsync(ct);
        if (updated == 0)
        {
            throw new InsufficientStockException(command.ProductId, command.WarehouseId, -delta, balanceAfter);
        }

        var ledger = new InventoryStockTransaction
        {
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            DocumentType = command.DocumentType,
            DocumentId = command.DocumentId,
            BalanceBefore = balanceAfter - delta,
            QuantityDelta = delta,
            BalanceAfter = balanceAfter,
            Notes = command.Notes,
            CreatedBy = command.CreatedBy,
            CreatedAt = now
        };
        _context.InventoryStockTransactions.Add(ledger);
        await _context.SaveChangesAsync(ct);

        return new StockOperationResult
        {
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            QuantityOnHand = balanceAfter,
            OperationType = command.DocumentType,
            QuantityDelta = delta,
            ReferenceDoc = command.DocumentId,
            MovementId = ledger.Id,
            Timestamp = now
        };
    }

    /// <summary>Ghi biến động tồn khả dụng trong transaction hiện có của caller.</summary>
    private async Task<StockOperationResult> ApplyReservationDeltaAsync(ChangeStockCommand command, CancellationToken ct)
    {
        var existing = await _context.InventoryStockReservationTransactions
            .AsNoTracking()
            .FirstOrDefaultAsync(t => t.DocumentType == command.DocumentType
                && t.DocumentId == command.DocumentId
                && t.WarehouseId == command.WarehouseId
                && t.ProductId == command.ProductId, ct);

        if (existing != null)
        {
            EnsureSameDelta(command, existing.QuantityDelta);
            return new StockOperationResult
            {
                WarehouseId = existing.WarehouseId,
                ProductId = existing.ProductId,
                QuantityReserved = existing.BalanceAfter,
                OperationType = existing.DocumentType,
                QuantityDelta = existing.QuantityDelta,
                ReferenceDoc = existing.DocumentId,
                MovementId = existing.Id,
                Timestamp = existing.CreatedAt
            };
        }

        var balances = _context.InventoryStockReservations
            .Where(r => r.WarehouseId == command.WarehouseId && r.ProductId == command.ProductId);

        if (!await balances.AnyAsync(ct))
        {
            var balance = new InventoryStockReservation(command.WarehouseId, command.ProductId, 0);
            _context.InventoryStockReservations.Add(balance);
            await _context.SaveChangesAsync(ct);
            _context.Entry(balance).State = EntityState.Detached;
        }

        var delta = command.QuantityDelta;
        var now = DateTime.UtcNow;
        var updated = await balances
            .Where(r => delta >= 0 || r.Quantity >= -delta)
            .ExecuteUpdateAsync(s => s
                .SetProperty(x => x.Quantity, x => x.Quantity + delta)
                .SetProperty(x => x.UpdatedAt, now), ct);

        var balanceAfter = await balances.AsNoTracking().Select(r => r.Quantity).FirstAsync(ct);
        if (updated == 0)
        {
            throw new InsufficientStockException(command.ProductId, command.WarehouseId, -delta, balanceAfter);
        }

        var ledger = new InventoryStockReservationTransaction
        {
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            DocumentType = command.DocumentType,
            DocumentId = command.DocumentId,
            BalanceBefore = balanceAfter - delta,
            QuantityDelta = delta,
            BalanceAfter = balanceAfter,
            Notes = command.Notes,
            CreatedBy = command.CreatedBy,
            CreatedAt = now
        };
        _context.InventoryStockReservationTransactions.Add(ledger);
        await _context.SaveChangesAsync(ct);

        return new StockOperationResult
        {
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            QuantityReserved = balanceAfter,
            OperationType = command.DocumentType,
            QuantityDelta = delta,
            ReferenceDoc = command.DocumentId,
            MovementId = ledger.Id,
            Timestamp = now
        };
    }

    private static void EnsureSameDelta(ChangeStockCommand command, int recordedDelta)
    {
        if (recordedDelta != command.QuantityDelta)
        {
            throw new InvalidStockOperationException("DOCUMENT_ALREADY_POSTED",
                $"Chứng từ {command.DocumentId} đã được ghi sổ sản phẩm {command.ProductId} với số lượng {Math.Abs(recordedDelta)}, khác số lượng {Math.Abs(command.QuantityDelta)} yêu cầu.");
        }
    }
}
