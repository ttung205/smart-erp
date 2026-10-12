using Inventory.Application.Common.Commands;
using Inventory.Application.Common.Models;
using Inventory.Application.DTOs;
using Inventory.Application.Interfaces;
using Inventory.Infrastructure.Persistence;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Api.Controllers;

[ApiController]
[Route("api/v1/inventory/[controller]")]
public class StocksController : ControllerBase
{
    private readonly InventoryDbContext _context;
    private readonly IInventoryStockManager _stockManager;
    private readonly IOutboxService _outboxService;

    public StocksController(InventoryDbContext context, IInventoryStockManager stockManager, IOutboxService outboxService)
    {
        _context = context;
        _stockManager = stockManager;
        _outboxService = outboxService;
    }

    private string GetCorrelationId()
    {
        return HttpContext.Items["CorrelationId"]?.ToString() 
            ?? HttpContext.Request.Headers["X-Correlation-ID"].FirstOrDefault() 
            ?? $"req_corr_{Guid.NewGuid():N}";
    }

    #region Query Side

    /// <summary>
    /// Tra cứu danh sách tồn kho theo bộ lọc kèm phân trang (kết hợp tồn thực tế và tồn khả dụng).
    /// </summary>
    [HttpGet]
    public async Task<IActionResult> GetStocks(
        [FromQuery] long? productId,
        [FromQuery] long? warehouseId,
        [FromQuery] bool? lowStock,
        [FromQuery] int page = 1,
        [FromQuery] int pageSize = 1000,
        CancellationToken ct = default)
    {
        page = Math.Max(1, page);
        pageSize = Math.Clamp(pageSize, 1, 1000);

        var sellableWarehouseIds = await _context.Warehouses
            .AsNoTracking()
            .Where(w => w.CanSell && w.IsActive)
            .Select(w => w.Id)
            .ToListAsync(ct);

        var rawReservations = await _context.InventoryStockReservations
            .AsNoTracking()
            .Where(r => sellableWarehouseIds.Contains(r.WarehouseId))
            .Select(r => new { r.ProductId, r.Quantity })
            .ToListAsync(ct);

        var salesAvailableDict = rawReservations
            .GroupBy(r => r.ProductId)
            .ToDictionary(g => g.Key, g => g.Sum(x => x.Quantity));

        var query = from s in _context.InventoryStocks.AsNoTracking().Include(s => s.Warehouse)
                    join r in _context.InventoryStockReservations.AsNoTracking()
                         on new { s.WarehouseId, s.ProductId } equals new { r.WarehouseId, r.ProductId } into grp
                    from res in grp.DefaultIfEmpty()
                    select new
                    {
                        s.Id,
                        s.ProductId,
                        s.WarehouseId,
                        Origin = s.Origin,
                        WarehouseCode = s.Warehouse != null ? s.Warehouse.Code : null,
                        WarehouseName = s.Warehouse != null ? s.Warehouse.Name : null,
                        CanSell = s.Warehouse != null && s.Warehouse.CanSell,
                        QuantityOnHand = s.Quantity,
                        Available = res != null ? res.Quantity : s.Quantity,
                        s.UpdatedAt
                    };

        if (productId.HasValue)
            query = query.Where(s => s.ProductId == productId.Value);

        if (warehouseId.HasValue)
            query = query.Where(s => s.WarehouseId == warehouseId.Value);

        var totalItems = await query.CountAsync(ct);
        var totalPages = (int)Math.Ceiling(totalItems / (double)pageSize);

        var rawList = await query
            .OrderBy(s => s.WarehouseId)
            .ThenBy(s => s.ProductId)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .ToListAsync(ct);

        var stocks = rawList.Select(s => new StockResponseDto
        {
            Id = s.Id,
            ProductId = s.ProductId,
            WarehouseId = s.WarehouseId,
            WarehouseCode = s.WarehouseCode,
            WarehouseName = s.WarehouseName,
            Origin = s.Origin,
            QuantityOnHand = s.QuantityOnHand,
            QuantityReserved = Math.Max(0, s.QuantityOnHand - s.Available),
            Available = s.Available,
            CanSell = s.CanSell,
            SalesAvailable = salesAvailableDict.GetValueOrDefault(s.ProductId, 0),
            MinStock = 0,
            IsLowStock = false,
            UpdatedAt = s.UpdatedAt
        }).ToList();

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds(),
            Pagination = new PaginationMeta
            {
                CurrentPage = page,
                PerPage = pageSize,
                TotalItems = totalItems,
                TotalPages = totalPages
            }
        };

        return Ok(ApiEnvelope<List<StockResponseDto>>.Ok(stocks, meta));
    }

    /// <summary>
    /// Chi tiết tồn kho của 1 sản phẩm tại tất cả các kho.
    /// </summary>
    [HttpGet("{productId:long}")]
    public async Task<IActionResult> GetStockByProductId(long productId, CancellationToken ct = default)
    {
        var rawStocks = await (from s in _context.InventoryStocks.AsNoTracking().Include(s => s.Warehouse)
                               join r in _context.InventoryStockReservations.AsNoTracking()
                                    on new { s.WarehouseId, s.ProductId } equals new { r.WarehouseId, r.ProductId } into grp
                               from res in grp.DefaultIfEmpty()
                               where s.ProductId == productId
                               orderby s.WarehouseId
                               select new
                               {
                                   s.WarehouseId,
                                   Origin = s.Origin,
                                   WarehouseCode = s.Warehouse != null ? s.Warehouse.Code : string.Empty,
                                   WarehouseName = s.Warehouse != null ? s.Warehouse.Name : string.Empty,
                                   QuantityOnHand = s.Quantity,
                                   Available = res != null ? res.Quantity : s.Quantity
                               }).ToListAsync(ct);

        if (rawStocks.Count == 0)
        {
            return NotFound(ApiEnvelope<object>.Fail(
                "RESOURCE_NOT_FOUND",
                $"Không tìm thấy dữ liệu tồn kho cho sản phẩm ID {productId}.",
                null,
                GetCorrelationId()));
        }

        var overview = new ProductStockOverviewDto
        {
            ProductId = productId,
            TotalOnHand = rawStocks.Sum(s => s.QuantityOnHand),
            TotalReserved = rawStocks.Sum(s => Math.Max(0, s.QuantityOnHand - s.Available)),
            TotalAvailable = rawStocks.Sum(s => s.Available),
            Warehouses = rawStocks.Select(s => new WarehouseStockBreakdownDto
            {
                WarehouseId = s.WarehouseId,
                WarehouseCode = s.WarehouseCode,
                Origin = s.Origin,
                WarehouseName = s.WarehouseName,
                QuantityOnHand = s.QuantityOnHand,
                QuantityReserved = Math.Max(0, s.QuantityOnHand - s.Available),
                Available = s.Available,
                MinStock = 10,
                IsLowStock = s.Available <= 10
            }).ToList()
        };

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<ProductStockOverviewDto>.Ok(overview, meta));
    }

    /// <summary>
    /// Sổ cái lịch sử giao dịch tồn kho thực tế (Audit Movements Ledger).
    /// </summary>
    [HttpGet("movements")]
    [HttpGet("transactions")]
    public async Task<IActionResult> GetMovements(
        [FromQuery] long? productId,
        [FromQuery] long? warehouseId,
        [FromQuery] string? movementType,
        [FromQuery] int page = 1,
        [FromQuery] int pageSize = 500,
        CancellationToken ct = default)
    {
        page = Math.Max(1, page);
        pageSize = Math.Clamp(pageSize, 1, 1000);

        var query = _context.InventoryStockTransactions
            .AsNoTracking()
            .Include(m => m.Warehouse)
            .AsQueryable();

        if (productId.HasValue)
            query = query.Where(m => m.ProductId == productId.Value);

        if (warehouseId.HasValue)
            query = query.Where(m => m.WarehouseId == warehouseId.Value);

        if (!string.IsNullOrWhiteSpace(movementType))
            query = query.Where(m => m.DocumentType == movementType);

        var totalItems = await query.CountAsync(ct);
        var totalPages = (int)Math.Ceiling(totalItems / (double)pageSize);

        var movements = await query
            .OrderByDescending(m => m.CreatedAt)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .Select(m => new StockMovementResponseDto
            {
                Id = m.Id,
                ProductId = m.ProductId,
                WarehouseId = m.WarehouseId,
                WarehouseName = m.Warehouse != null ? m.Warehouse.Name : null,
                MovementType = m.DocumentType,
                BalanceBefore = m.BalanceBefore,
                QuantityDelta = m.QuantityDelta,
                BalanceAfter = m.BalanceAfter,
                BalanceOnHandAfter = m.BalanceAfter,
                BalanceReservedAfter = 0,
                ReferenceDoc = m.DocumentId,
                CreatedAt = m.CreatedAt
            })
            .ToListAsync(ct);

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds(),
            Pagination = new PaginationMeta
            {
                CurrentPage = page,
                PerPage = pageSize,
                TotalItems = totalItems,
                TotalPages = totalPages
            }
        };

        return Ok(ApiEnvelope<List<StockMovementResponseDto>>.Ok(movements, meta));
    }

    /// <summary>
    /// Lấy danh sách các đơn hàng đang giữ chỗ cho sản phẩm này.
    /// </summary>
    [HttpGet("reservations/orders")]
    public async Task<IActionResult> GetReservationOrders(
        [FromQuery] long productId,
        [FromQuery] long? warehouseId,
        CancellationToken ct = default)
    {
        var query = _context.InventoryStockReservationTransactions
            .AsNoTracking()
            .Include(t => t.Warehouse)
            .Where(t => t.ProductId == productId && t.QuantityDelta < 0);

        if (warehouseId.HasValue)
            query = query.Where(t => t.WarehouseId == warehouseId.Value);

        var orders = await query
            .OrderByDescending(t => t.CreatedAt)
            .Select(t => new
            {
                order_id = t.DocumentId,
                document_type = t.DocumentType,
                warehouse_id = t.WarehouseId,
                warehouse_name = t.Warehouse != null ? t.Warehouse.Name : null,
                quantity = Math.Abs(t.QuantityDelta),
                notes = t.Notes,
                created_at = t.CreatedAt
            })
            .ToListAsync(ct);

        return Ok(ApiEnvelope<object>.Ok(orders));
    }

    #endregion

    #region Command Side (Atomic Operations)

    /// <summary>
    /// Biến động số dư tồn kho thực tế (Physical Stock Mutation).
    /// Tự động cập nhật bảng inventory_stocks và ghi sổ inventory_stocks_transaction.
    /// </summary>
    [HttpPost("change")]
    public async Task<IActionResult> ChangePhysicalStock([FromBody] ChangeStockRequest request, CancellationToken ct)
    {
        var command = new ChangeStockCommand(
            WarehouseId: request.WarehouseId,
            ProductId: request.ProductId,
            QuantityDelta: request.QuantityDelta,
            DocumentType: request.DocumentType,
            DocumentId: request.DocumentId,
            Notes: request.Notes,
            CreatedBy: request.CreatedBy,
            Origin: request.Origin
        );

        var result = await _stockManager.ChangePhysicalStockAsync(command, ct);

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<object>.Ok(new
        {
            warehouse_id = result.WarehouseId,
            product_id = result.ProductId,
            balance_on_hand_after = result.QuantityOnHand,
            quantity_delta = result.QuantityDelta,
            document_type = result.OperationType,
            document_id = result.ReferenceDoc,
            transaction_id = result.MovementId,
            timestamp = result.Timestamp
        }, meta));
    }

    /// <summary>
    /// Khóa giữ chỗ tồn kho (Reserve) theo đơn bán hàng SO.
    /// </summary>
    [HttpPost("reserve")]
    public async Task<IActionResult> ReserveStock([FromBody] ReserveStockRequest request, CancellationToken ct)
    {
        var command = new ReserveStockCommand(
            OrderId: request.OrderId,
            WarehouseId: request.WarehouseId,
            ProductId: request.ProductId,
            Quantity: request.Quantity,
            ExpiresAt: request.ExpiresAt
        );

        var result = await _stockManager.ReserveStockAsync(command, ct);

        var responseDto = new StockOperationResponseDto
        {
            ProductId = result.ProductId,
            WarehouseId = result.WarehouseId,
            QuantityOnHand = result.QuantityOnHand,
            QuantityReserved = result.QuantityReserved,
            Available = result.Available,
            OperationType = result.OperationType,
            QuantityDelta = result.QuantityDelta,
            ReferenceDoc = result.ReferenceDoc,
            MovementId = result.MovementId,
            ReservationId = result.ReservationId,
            Timestamp = result.Timestamp
        };

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<StockOperationResponseDto>.Ok(responseDto, meta));
    }

    /// <summary>
    /// Khóa giữ chỗ theo lô (Batch Reserve).
    /// </summary>
    [HttpPost("reserve/batch")]
    public async Task<IActionResult> BatchReserveStock([FromBody] BatchReserveStockRequest request, CancellationToken ct)
    {
        var items = request.Items.Select(i => new ReserveStockItem(i.WarehouseId, i.ProductId, i.Quantity)).ToList();
        var command = new BatchReserveStockCommand(request.OrderId, items, request.ExpiresAt);

        var results = await _stockManager.BatchReserveStockAsync(command, ct);

        var responseDtos = results.Select(r => new StockOperationResponseDto
        {
            ProductId = r.ProductId,
            WarehouseId = r.WarehouseId,
            QuantityOnHand = r.QuantityOnHand,
            QuantityReserved = r.QuantityReserved,
            Available = r.Available,
            OperationType = r.OperationType,
            QuantityDelta = r.QuantityDelta,
            ReferenceDoc = r.ReferenceDoc,
            MovementId = r.MovementId,
            ReservationId = r.ReservationId,
            Timestamp = r.Timestamp
        }).ToList();

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<List<StockOperationResponseDto>>.Ok(responseDtos, meta));
    }

    /// <summary>
    /// Giải phóng giữ chỗ (Release) khi đơn bán hàng bị hủy.
    /// </summary>
    [HttpPost("release")]
    public async Task<IActionResult> ReleaseStock([FromBody] ReleaseStockRequest request, CancellationToken ct)
    {
        var command = new ReleaseStockCommand(
            OrderId: request.OrderId,
            WarehouseId: request.WarehouseId,
            ProductId: request.ProductId,
            Quantity: request.Quantity
        );

        var result = await _stockManager.ReleaseStockAsync(command, ct);

        var responseDto = new StockOperationResponseDto
        {
            ProductId = result.ProductId,
            WarehouseId = result.WarehouseId,
            QuantityOnHand = result.QuantityOnHand,
            QuantityReserved = result.QuantityReserved,
            Available = result.Available,
            OperationType = result.OperationType,
            QuantityDelta = result.QuantityDelta,
            ReferenceDoc = result.ReferenceDoc,
            MovementId = result.MovementId,
            ReservationId = result.ReservationId,
            Timestamp = result.Timestamp
        };

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<StockOperationResponseDto>.Ok(responseDto, meta));
    }

    /// <summary>
    /// Phiếu nhập kho thực tế (Goods Receipt / Receive) từ đơn mua hàng PO.
    /// Tăng cả tồn thực tế và tồn khả dụng.
    /// </summary>
    [HttpPost("receive")]
    public async Task<IActionResult> ReceiveStock([FromBody] ReceiveStockRequest request, CancellationToken ct)
    {
        var command = new ReceiveStockCommand(
            ReferenceDoc: request.ReferenceDoc,
            WarehouseId: request.WarehouseId,
            ProductId: request.ProductId,
            Quantity: request.Quantity,
            MinStock: 0
        );

        var result = await _stockManager.ReceiveStockAsync(command, ct);

        var responseDto = new StockOperationResponseDto
        {
            ProductId = result.ProductId,
            WarehouseId = result.WarehouseId,
            QuantityOnHand = result.QuantityOnHand,
            QuantityReserved = result.QuantityReserved,
            Available = result.Available,
            OperationType = result.OperationType,
            QuantityDelta = result.QuantityDelta,
            ReferenceDoc = result.ReferenceDoc,
            MovementId = result.MovementId,
            Timestamp = result.Timestamp
        };

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<StockOperationResponseDto>.Ok(responseDto, meta));
    }

    /// <summary>
    /// Phiếu xuất kho thực tế (Goods Issue / Delivery) cho đơn hàng đã giữ chỗ.
    /// Trừ tồn thực tế.
    /// </summary>
    [HttpPost("issue")]
    public async Task<IActionResult> IssueStock([FromBody] IssueStockRequest request, CancellationToken ct)
    {
        var command = new IssueStockCommand(
            ReferenceDoc: request.ReferenceDoc,
            WarehouseId: request.WarehouseId,
            ProductId: request.ProductId,
            Quantity: request.Quantity,
            OrderId: request.OrderId
        );

        var result = await _stockManager.IssueStockAsync(command, ct);

        // Ghi nhận sự kiện Outbox GoodsIssued cho sales-service
        var goodsIssuedPayload = new Inventory.Application.Common.Events.GoodsIssuedPayload
        {
            IssueId = request.ReferenceDoc,
            OrderId = request.OrderId,
            WarehouseId = request.WarehouseId,
            IssuedAt = DateTime.UtcNow,
            Items = new List<Inventory.Application.Common.Events.GoodsIssuedItemPayload>
            {
                new()
                {
                    ProductId = result.ProductId,
                    QuantityIssued = Math.Abs(result.QuantityDelta),
                    BalanceOnHandAfter = result.QuantityOnHand,
                    BalanceReservedAfter = result.QuantityReserved
                }
            }
        };

        await _outboxService.SaveEventAsync(
            routingKey: "inventory.goods.issued",
            eventType: "GoodsIssued",
            aggregateType: "InventoryStock",
            aggregateId: request.ReferenceDoc,
            payload: goodsIssuedPayload,
            correlationId: GetCorrelationId(),
            ct: ct
        );

        var responseDto = new StockOperationResponseDto
        {
            ProductId = result.ProductId,
            WarehouseId = result.WarehouseId,
            QuantityOnHand = result.QuantityOnHand,
            QuantityReserved = result.QuantityReserved,
            Available = result.Available,
            OperationType = result.OperationType,
            QuantityDelta = result.QuantityDelta,
            ReferenceDoc = result.ReferenceDoc,
            MovementId = result.MovementId,
            ReservationId = result.ReservationId,
            Timestamp = result.Timestamp
        };

        var meta = new ApiMeta
        {
            CorrelationId = GetCorrelationId(),
            Timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
        };

        return Ok(ApiEnvelope<StockOperationResponseDto>.Ok(responseDto, meta));
    }

    #endregion
}
