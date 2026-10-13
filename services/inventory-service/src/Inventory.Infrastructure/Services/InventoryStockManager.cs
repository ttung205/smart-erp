using Inventory.Application.Common;
using Inventory.Application.Common.Commands;
using Inventory.Application.Interfaces;
using Inventory.Domain.Entities;
using Inventory.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Infrastructure.Services;

/// <summary>
/// Quản lý biến động 2 tầng tồn kho: Tồn kho thực tế (Physical) và Tồn kho khả dụng (Reservation).
/// Tự động cập nhật số dư và ghi sổ cái giao dịch bất biến tương ứng.
/// </summary>
public class InventoryStockManager : IInventoryStockManager
{
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

        var existingTransaction = await _context.InventoryStockTransactions
            .AsNoTracking()
            .FirstOrDefaultAsync(t => t.DocumentType == command.DocumentType
                && t.DocumentId == command.DocumentId
                && t.WarehouseId == command.WarehouseId
                && t.ProductId == command.ProductId, ct);

        if (existingTransaction != null)
        {
            if (existingTransaction.QuantityDelta != command.QuantityDelta)
            {
                throw new InvalidOperationException(
                    $"Chung tu {command.DocumentId} da duoc ghi so voi so luong khac.");
            }

            return new StockOperationResult
            {
                WarehouseId = existingTransaction.WarehouseId,
                ProductId = existingTransaction.ProductId,
                QuantityOnHand = existingTransaction.BalanceAfter,
                OperationType = existingTransaction.DocumentType,
                QuantityDelta = existingTransaction.QuantityDelta,
                ReferenceDoc = existingTransaction.DocumentId,
                MovementId = existingTransaction.Id,
                Timestamp = existingTransaction.CreatedAt
            };
        }

        var stock = await _context.InventoryStocks
            .FirstOrDefaultAsync(s => s.WarehouseId == command.WarehouseId && s.ProductId == command.ProductId, ct);

        if (stock == null)
        {
            stock = new InventoryStock
            {
                WarehouseId = command.WarehouseId,
                ProductId = command.ProductId,
                Quantity = 0,
                Origin = !string.IsNullOrWhiteSpace(command.Origin) ? command.Origin : "Việt Nam",
                UpdatedAt = DateTime.UtcNow
            };
            _context.InventoryStocks.Add(stock);
            await _context.SaveChangesAsync(ct);
        }
        else if (!string.IsNullOrWhiteSpace(command.Origin))
        {
            stock.Origin = command.Origin;
        }

        if (command.QuantityDelta < 0 && (stock.Quantity + command.QuantityDelta) < 0)
        {
            throw new InvalidOperationException(
                $"Tồn kho thực tế không đủ! Hiện có {stock.Quantity}, yêu cầu xuất {-command.QuantityDelta} (Sản phẩm ID {command.ProductId}, Kho ID {command.WarehouseId}).");
        }

        var balanceBefore = stock.Quantity;
        stock.Quantity += command.QuantityDelta;
        stock.UpdatedAt = DateTime.UtcNow;

        var transaction = new InventoryStockTransaction
        {
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            DocumentType = command.DocumentType,
            DocumentId = command.DocumentId,
            BalanceBefore = balanceBefore,
            QuantityDelta = command.QuantityDelta,
            BalanceAfter = stock.Quantity,
            Notes = command.Notes,
            CreatedBy = command.CreatedBy,
            CreatedAt = DateTime.UtcNow
        };

        _context.InventoryStockTransactions.Add(transaction);
        await _context.SaveChangesAsync(ct);
        await tx.CommitAsync(ct);

        return new StockOperationResult
        {
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            QuantityOnHand = stock.Quantity,
            OperationType = command.DocumentType,
            QuantityDelta = command.QuantityDelta,
            ReferenceDoc = command.DocumentId,
            MovementId = transaction.Id,
            Timestamp = DateTime.UtcNow
        };
    }

    /// <summary>
    /// Thay đổi số dư tồn kho khả dụng và tự động ghi sổ cái giao dịch khả dụng.
    /// </summary>
    public async Task<StockOperationResult> ChangeReservationStockAsync(ChangeStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        await using var tx = await _context.Database.BeginTransactionAsync(ct);

        var res = await _context.InventoryStockReservations
            .FirstOrDefaultAsync(r => r.WarehouseId == command.WarehouseId && r.ProductId == command.ProductId, ct);

        if (res == null)
        {
            res = new InventoryStockReservation
            {
                WarehouseId = command.WarehouseId,
                ProductId = command.ProductId,
                Quantity = 0,
                UpdatedAt = DateTime.UtcNow
            };
            _context.InventoryStockReservations.Add(res);
            await _context.SaveChangesAsync(ct);
        }

        if (command.QuantityDelta < 0 && (res.Quantity + command.QuantityDelta) < 0)
        {
            throw new InvalidOperationException(
                $"Tồn kho khả dụng không đủ! Hiện có {res.Quantity}, yêu cầu trừ {-command.QuantityDelta} (Sản phẩm ID {command.ProductId}, Kho ID {command.WarehouseId}).");
        }

        var balanceBefore = res.Quantity;
        res.Quantity += command.QuantityDelta;
        res.UpdatedAt = DateTime.UtcNow;

        var transaction = new InventoryStockReservationTransaction
        {
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            DocumentType = command.DocumentType,
            DocumentId = command.DocumentId,
            BalanceBefore = balanceBefore,
            QuantityDelta = command.QuantityDelta,
            BalanceAfter = res.Quantity,
            Notes = command.Notes,
            CreatedBy = command.CreatedBy,
            CreatedAt = DateTime.UtcNow
        };

        _context.InventoryStockReservationTransactions.Add(transaction);
        await _context.SaveChangesAsync(ct);
        await tx.CommitAsync(ct);

        return new StockOperationResult
        {
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            QuantityReserved = res.Quantity,
            OperationType = command.DocumentType,
            QuantityDelta = command.QuantityDelta,
            ReferenceDoc = command.DocumentId,
            MovementId = transaction.Id,
            Timestamp = DateTime.UtcNow
        };
    }

    /// <summary>
    /// Nhập kho: Tăng cả tồn thực tế và tồn khả dụng.
    /// </summary>
    public async Task<StockOperationResult> ReceiveStockAsync(ReceiveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        // 1. Tăng tồn thực tế
        var physicalResult = await ChangePhysicalStockAsync(new ChangeStockCommand(
            command.WarehouseId,
            command.ProductId,
            command.Quantity,
            "PURCHASE_RECEIPT",
            command.ReferenceDoc,
            "Nhập kho mua hàng (PO Receipt)"
        ), ct);

        // 2. Tăng tồn khả dụng
        await ChangeReservationStockAsync(new ChangeStockCommand(
            command.WarehouseId,
            command.ProductId,
            command.Quantity,
            "PURCHASE_RECEIPT",
            command.ReferenceDoc,
            "Tăng tồn khả dụng từ đơn nhập kho"
        ), ct);

        return physicalResult;
    }

    /// <summary>
    /// Giữ chỗ khi tạo đơn hàng: Trừ tồn khả dụng.
    /// </summary>
    public async Task<StockOperationResult> ReserveStockAsync(ReserveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        return await ChangeReservationStockAsync(new ChangeStockCommand(
            command.WarehouseId,
            command.ProductId,
            -command.Quantity,
            "SALES_ORDER",
            command.OrderId,
            "Giữ chỗ đơn bán hàng"
        ), ct);
    }

    /// <summary>
    /// Giữ chỗ hàng loạt.
    /// </summary>
    public async Task<IReadOnlyList<StockOperationResult>> BatchReserveStockAsync(BatchReserveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        var results = new List<StockOperationResult>();
        foreach (var item in command.Items)
        {
            var res = await ReserveStockAsync(new ReserveStockCommand(
                command.OrderId,
                item.WarehouseId,
                item.ProductId,
                item.Quantity,
                command.ExpiresAt
            ), ct);
            results.Add(res);
        }
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
            "CANCEL_ORDER",
            command.OrderId,
            "Hoàn trả tồn khả dụng do hủy đơn"
        ), ct);
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
}
