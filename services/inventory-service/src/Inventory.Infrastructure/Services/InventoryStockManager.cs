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
/// Động cơ quản lý tồn kho nguyên tử (Atomic Concurrency Engine) và Sổ cái thẻ kho bất biến (Immutable Stock Ledger).
/// </summary>
public class InventoryStockManager : IInventoryStockManager
{
    private readonly InventoryDbContext _context;

    public InventoryStockManager(InventoryDbContext context)
    {
        _context = context ?? throw new ArgumentNullException(nameof(context));
    }

    /// <inheritdoc />
    public async Task<StockOperationResult> ReserveStockAsync(ReserveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        if (command.Quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng giữ kho phải lớn hơn 0 (nhận: {command.Quantity}).");

        await using var tx = await _context.Database.BeginTransactionAsync(ct);

        var now = DateTime.UtcNow;

        // 1. Atomic SQL Guard: Chỉ tăng QuantityReserved khi (OnHand - Reserved) >= Quantity
        var affected = await _context.InventoryStocks
            .Where(s => s.ProductId == command.ProductId
                     && s.WarehouseId == command.WarehouseId
                     && (s.QuantityOnHand - s.QuantityReserved) >= command.Quantity)
            .ExecuteUpdateAsync(setters => setters
                .SetProperty(s => s.QuantityReserved, s => s.QuantityReserved + command.Quantity)
                .SetProperty(s => s.Version, s => s.Version + 1)
                .SetProperty(s => s.UpdatedAt, now), ct);

        if (affected == 0)
        {
            // Kiểm tra nguyên nhân thất bại để trả về lỗi chính xác
            var existingStock = await _context.InventoryStocks
                .AsNoTracking()
                .FirstOrDefaultAsync(s => s.ProductId == command.ProductId && s.WarehouseId == command.WarehouseId, ct);

            if (existingStock is null)
            {
                throw new InsufficientStockException(command.ProductId, command.WarehouseId, command.Quantity, 0);
            }

            throw new InsufficientStockException(
                command.ProductId,
                command.WarehouseId,
                command.Quantity,
                existingStock.Available);
        }

        // 2. Lấy snapshot số dư sau cập nhật
        var updatedStock = await _context.InventoryStocks
            .AsNoTracking()
            .FirstAsync(s => s.ProductId == command.ProductId && s.WarehouseId == command.WarehouseId, ct);

        // 3. Tạo bản ghi giữ chỗ StockReservation
        var reservation = new StockReservation
        {
            OrderId = command.OrderId,
            WarehouseId = command.WarehouseId,
            ProductId = command.ProductId,
            Quantity = command.Quantity,
            Status = "ACTIVE",
            ExpiresAt = command.ExpiresAt,
            CreatedAt = now,
            UpdatedAt = now
        };
        _context.StockReservations.Add(reservation);

        // 4. Ghi Sổ Cái Bất Biến (Immutable Stock Ledger)
        var movement = new StockMovement
        {
            ProductId = command.ProductId,
            WarehouseId = command.WarehouseId,
            MovementType = "SO_RESERVE",
            QuantityDelta = command.Quantity,
            BalanceOnHandAfter = updatedStock.QuantityOnHand,
            BalanceReservedAfter = updatedStock.QuantityReserved,
            ReferenceDoc = command.OrderId,
            CreatedBy = command.UserId,
            CreatedAt = now
        };
        _context.StockMovements.Add(movement);

        await _context.SaveChangesAsync(ct);
        await tx.CommitAsync(ct);

        return new StockOperationResult
        {
            ProductId = updatedStock.ProductId,
            WarehouseId = updatedStock.WarehouseId,
            QuantityOnHand = updatedStock.QuantityOnHand,
            QuantityReserved = updatedStock.QuantityReserved,
            OperationType = "SO_RESERVE",
            QuantityDelta = command.Quantity,
            ReferenceDoc = command.OrderId,
            MovementId = movement.Id,
            ReservationId = reservation.Id,
            Timestamp = now
        };
    }

    /// <inheritdoc />
    public async Task<IReadOnlyList<StockOperationResult>> BatchReserveStockAsync(BatchReserveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        if (command.Items is null || command.Items.Count == 0)
            throw new InvalidStockOperationException("Danh sách mặt hàng giữ kho không được để trống.");

        // 1. Gộp các dòng có cùng (WarehouseId, ProductId)
        var consolidated = command.Items
            .GroupBy(x => new { x.WarehouseId, x.ProductId })
            .Select(g => new ReserveStockItem(g.Key.WarehouseId, g.Key.ProductId, g.Sum(x => x.Quantity)))
            .ToList();

        // 2. Sắp xếp thứ tự khóa tài nguyên xác định để TRIỆT TIÊU DEADLOCK 100%
        var orderedItems = consolidated.ToDeterministicLockOrder();

        await using var tx = await _context.Database.BeginTransactionAsync(ct);

        var now = DateTime.UtcNow;
        var results = new List<StockOperationResult>(orderedItems.Count);

        foreach (var item in orderedItems)
        {
            if (item.Quantity <= 0)
                throw new InvalidStockOperationException($"Số lượng giữ kho của sản phẩm {item.ProductId} phải > 0.");

            // Atomic Guard trên từng item theo thứ tự cố định
            var affected = await _context.InventoryStocks
                .Where(s => s.ProductId == item.ProductId
                         && s.WarehouseId == item.WarehouseId
                         && (s.QuantityOnHand - s.QuantityReserved) >= item.Quantity)
                .ExecuteUpdateAsync(setters => setters
                    .SetProperty(s => s.QuantityReserved, s => s.QuantityReserved + item.Quantity)
                    .SetProperty(s => s.Version, s => s.Version + 1)
                    .SetProperty(s => s.UpdatedAt, now), ct);

            if (affected == 0)
            {
                var existing = await _context.InventoryStocks
                    .AsNoTracking()
                    .FirstOrDefaultAsync(s => s.ProductId == item.ProductId && s.WarehouseId == item.WarehouseId, ct);

                var available = existing?.Available ?? 0;
                throw new InsufficientStockException(item.ProductId, item.WarehouseId, item.Quantity, available);
            }

            var updatedStock = await _context.InventoryStocks
                .AsNoTracking()
                .FirstAsync(s => s.ProductId == item.ProductId && s.WarehouseId == item.WarehouseId, ct);

            var reservation = new StockReservation
            {
                OrderId = command.OrderId,
                WarehouseId = item.WarehouseId,
                ProductId = item.ProductId,
                Quantity = item.Quantity,
                Status = "ACTIVE",
                ExpiresAt = command.ExpiresAt,
                CreatedAt = now,
                UpdatedAt = now
            };
            _context.StockReservations.Add(reservation);

            var movement = new StockMovement
            {
                ProductId = item.ProductId,
                WarehouseId = item.WarehouseId,
                MovementType = "SO_RESERVE",
                QuantityDelta = item.Quantity,
                BalanceOnHandAfter = updatedStock.QuantityOnHand,
                BalanceReservedAfter = updatedStock.QuantityReserved,
                ReferenceDoc = command.OrderId,
                CreatedBy = command.UserId,
                CreatedAt = now
            };
            _context.StockMovements.Add(movement);

            results.Add(new StockOperationResult
            {
                ProductId = item.ProductId,
                WarehouseId = item.WarehouseId,
                QuantityOnHand = updatedStock.QuantityOnHand,
                QuantityReserved = updatedStock.QuantityReserved,
                OperationType = "SO_RESERVE",
                QuantityDelta = item.Quantity,
                ReferenceDoc = command.OrderId,
                Timestamp = now
            });
        }

        await _context.SaveChangesAsync(ct);
        await tx.CommitAsync(ct);

        return results.AsReadOnly();
    }

    /// <inheritdoc />
    public async Task<StockOperationResult> ReleaseStockAsync(ReleaseStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        if (command.Quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng giải phóng phải lớn hơn 0 (nhận: {command.Quantity}).");

        await using var tx = await _context.Database.BeginTransactionAsync(ct);

        var now = DateTime.UtcNow;

        // 1. Atomic SQL Guard: Chỉ giảm QuantityReserved khi QuantityReserved >= Quantity
        var affected = await _context.InventoryStocks
            .Where(s => s.ProductId == command.ProductId
                     && s.WarehouseId == command.WarehouseId
                     && s.QuantityReserved >= command.Quantity)
            .ExecuteUpdateAsync(setters => setters
                .SetProperty(s => s.QuantityReserved, s => s.QuantityReserved - command.Quantity)
                .SetProperty(s => s.Version, s => s.Version + 1)
                .SetProperty(s => s.UpdatedAt, now), ct);

        if (affected == 0)
        {
            var existing = await _context.InventoryStocks
                .AsNoTracking()
                .FirstOrDefaultAsync(s => s.ProductId == command.ProductId && s.WarehouseId == command.WarehouseId, ct);

            var currentReserved = existing?.QuantityReserved ?? 0;
            throw new InvalidStockOperationException(
                $"Không thể giải phóng {command.Quantity} sản phẩm ID {command.ProductId} tại kho {command.WarehouseId} vì số lượng giữ hiện tại là {currentReserved}.");
        }

        var updatedStock = await _context.InventoryStocks
            .AsNoTracking()
            .FirstAsync(s => s.ProductId == command.ProductId && s.WarehouseId == command.WarehouseId, ct);

        // 2. Cập nhật reservation tương ứng
        var reservation = await _context.StockReservations
            .Where(r => r.OrderId == command.OrderId
                     && r.ProductId == command.ProductId
                     && r.WarehouseId == command.WarehouseId
                     && r.Status == "ACTIVE")
            .OrderByDescending(r => r.CreatedAt)
            .FirstOrDefaultAsync(ct);

        if (reservation is not null)
        {
            reservation.Status = "RELEASED";
            reservation.UpdatedAt = now;
        }

        // 3. Ghi sổ cái thẻ kho
        var movement = new StockMovement
        {
            ProductId = command.ProductId,
            WarehouseId = command.WarehouseId,
            MovementType = "SO_RELEASE",
            QuantityDelta = -command.Quantity,
            BalanceOnHandAfter = updatedStock.QuantityOnHand,
            BalanceReservedAfter = updatedStock.QuantityReserved,
            ReferenceDoc = command.OrderId,
            CreatedBy = command.UserId,
            CreatedAt = now
        };
        _context.StockMovements.Add(movement);

        await _context.SaveChangesAsync(ct);
        await tx.CommitAsync(ct);

        return new StockOperationResult
        {
            ProductId = updatedStock.ProductId,
            WarehouseId = updatedStock.WarehouseId,
            QuantityOnHand = updatedStock.QuantityOnHand,
            QuantityReserved = updatedStock.QuantityReserved,
            OperationType = "SO_RELEASE",
            QuantityDelta = -command.Quantity,
            ReferenceDoc = command.OrderId,
            MovementId = movement.Id,
            ReservationId = reservation?.Id,
            Timestamp = now
        };
    }

    /// <inheritdoc />
    public async Task<StockOperationResult> ReceiveStockAsync(ReceiveStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        if (command.Quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng nhập kho phải lớn hơn 0 (nhận: {command.Quantity}).");

        await using var tx = await _context.Database.BeginTransactionAsync(ct);

        var now = DateTime.UtcNow;

        // 1. Thử Atomic Update cộng dồn QuantityOnHand nếu bản ghi đã tồn tại
        var affected = await _context.InventoryStocks
            .Where(s => s.ProductId == command.ProductId && s.WarehouseId == command.WarehouseId)
            .ExecuteUpdateAsync(setters => setters
                .SetProperty(s => s.QuantityOnHand, s => s.QuantityOnHand + command.Quantity)
                .SetProperty(s => s.Version, s => s.Version + 1)
                .SetProperty(s => s.UpdatedAt, now), ct);

        InventoryStock updatedStock;

        if (affected == 0)
        {
            // 2. Nếu chưa có bản ghi (sản phẩm mới nhập lần đầu vào kho này), tạo mới
            var newStock = new InventoryStock(
                command.ProductId,
                command.WarehouseId,
                initialOnHand: command.Quantity,
                minStock: command.MinStock);

            _context.InventoryStocks.Add(newStock);
            await _context.SaveChangesAsync(ct);
            updatedStock = newStock;
        }
        else
        {
            updatedStock = await _context.InventoryStocks
                .AsNoTracking()
                .FirstAsync(s => s.ProductId == command.ProductId && s.WarehouseId == command.WarehouseId, ct);
        }

        // 3. Ghi Sổ Cái Bất Biến (Immutable Stock Ledger)
        var movement = new StockMovement
        {
            ProductId = command.ProductId,
            WarehouseId = command.WarehouseId,
            MovementType = "PO_RECEIPT",
            QuantityDelta = command.Quantity,
            BalanceOnHandAfter = updatedStock.QuantityOnHand,
            BalanceReservedAfter = updatedStock.QuantityReserved,
            ReferenceDoc = command.ReferenceDoc,
            CreatedBy = command.UserId,
            CreatedAt = now
        };
        _context.StockMovements.Add(movement);

        await _context.SaveChangesAsync(ct);
        await tx.CommitAsync(ct);

        return new StockOperationResult
        {
            ProductId = updatedStock.ProductId,
            WarehouseId = updatedStock.WarehouseId,
            QuantityOnHand = updatedStock.QuantityOnHand,
            QuantityReserved = updatedStock.QuantityReserved,
            OperationType = "PO_RECEIPT",
            QuantityDelta = command.Quantity,
            ReferenceDoc = command.ReferenceDoc,
            MovementId = movement.Id,
            Timestamp = now
        };
    }

    /// <inheritdoc />
    public async Task<StockOperationResult> IssueStockAsync(IssueStockCommand command, CancellationToken ct = default)
    {
        ArgumentNullException.ThrowIfNull(command);

        if (command.Quantity <= 0)
            throw new InvalidStockOperationException($"Số lượng xuất kho phải lớn hơn 0 (nhận: {command.Quantity}).");

        await using var tx = await _context.Database.BeginTransactionAsync(ct);

        var now = DateTime.UtcNow;

        // 1. Atomic SQL Guard: Giảm đồng thời cả QuantityOnHand và QuantityReserved
        var affected = await _context.InventoryStocks
            .Where(s => s.ProductId == command.ProductId
                     && s.WarehouseId == command.WarehouseId
                     && s.QuantityOnHand >= command.Quantity
                     && s.QuantityReserved >= command.Quantity)
            .ExecuteUpdateAsync(setters => setters
                .SetProperty(s => s.QuantityOnHand, s => s.QuantityOnHand - command.Quantity)
                .SetProperty(s => s.QuantityReserved, s => s.QuantityReserved - command.Quantity)
                .SetProperty(s => s.Version, s => s.Version + 1)
                .SetProperty(s => s.UpdatedAt, now), ct);

        if (affected == 0)
        {
            var existing = await _context.InventoryStocks
                .AsNoTracking()
                .FirstOrDefaultAsync(s => s.ProductId == command.ProductId && s.WarehouseId == command.WarehouseId, ct);

            if (existing is null)
                throw new InsufficientStockException(command.ProductId, command.WarehouseId, command.Quantity, 0);

            if (existing.QuantityOnHand < command.Quantity)
                throw new InsufficientStockException(command.ProductId, command.WarehouseId, command.Quantity, existing.QuantityOnHand);

            throw new InvalidStockOperationException(
                $"Không thể xuất kho {command.Quantity} sản phẩm ID {command.ProductId} vì số lượng đã giữ chỗ chỉ là {existing.QuantityReserved}.");
        }

        var updatedStock = await _context.InventoryStocks
            .AsNoTracking()
            .FirstAsync(s => s.ProductId == command.ProductId && s.WarehouseId == command.WarehouseId, ct);

        // 2. Cập nhật StockReservation nếu có OrderId chỉ định
        StockReservation? reservation = null;
        if (!string.IsNullOrWhiteSpace(command.OrderId))
        {
            reservation = await _context.StockReservations
                .Where(r => r.OrderId == command.OrderId
                         && r.ProductId == command.ProductId
                         && r.WarehouseId == command.WarehouseId
                         && r.Status == "ACTIVE")
                .OrderByDescending(r => r.CreatedAt)
                .FirstOrDefaultAsync(ct);

            if (reservation is not null)
            {
                reservation.Status = "FULFILLED";
                reservation.UpdatedAt = now;
            }
        }

        // 3. Ghi Sổ Cái Bất Biến (Immutable Stock Ledger)
        var movement = new StockMovement
        {
            ProductId = command.ProductId,
            WarehouseId = command.WarehouseId,
            MovementType = "SO_ISSUE",
            QuantityDelta = -command.Quantity,
            BalanceOnHandAfter = updatedStock.QuantityOnHand,
            BalanceReservedAfter = updatedStock.QuantityReserved,
            ReferenceDoc = command.ReferenceDoc,
            CreatedBy = command.UserId,
            CreatedAt = now
        };
        _context.StockMovements.Add(movement);

        await _context.SaveChangesAsync(ct);
        await tx.CommitAsync(ct);

        return new StockOperationResult
        {
            ProductId = updatedStock.ProductId,
            WarehouseId = updatedStock.WarehouseId,
            QuantityOnHand = updatedStock.QuantityOnHand,
            QuantityReserved = updatedStock.QuantityReserved,
            OperationType = "SO_ISSUE",
            QuantityDelta = -command.Quantity,
            ReferenceDoc = command.ReferenceDoc,
            MovementId = movement.Id,
            ReservationId = reservation?.Id,
            Timestamp = now
        };
    }

    /// <inheritdoc />
    public async Task<InventoryStock?> GetStockAsync(long productId, long warehouseId, CancellationToken ct = default)
    {
        return await _context.InventoryStocks
            .AsNoTracking()
            .Include(s => s.Warehouse)
            .FirstOrDefaultAsync(s => s.ProductId == productId && s.WarehouseId == warehouseId, ct);
    }
}
