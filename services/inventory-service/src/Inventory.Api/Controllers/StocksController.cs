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

    #region Query Side (Tối ưu hiệu năng cao với .AsNoTracking)

    /// <summary>
    /// Tra cứu danh sách tồn kho theo bộ lọc kèm phân trang.
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

        var query = _context.InventoryStocks
            .AsNoTracking()
            .Include(s => s.Warehouse)
            .AsQueryable();

        if (productId.HasValue)
            query = query.Where(s => s.ProductId == productId.Value);

        if (warehouseId.HasValue)
            query = query.Where(s => s.WarehouseId == warehouseId.Value);

        if (lowStock.HasValue && lowStock.Value)
            query = query.Where(s => (s.QuantityOnHand - s.QuantityReserved) <= s.MinStock);

        var totalItems = await query.CountAsync(ct);
        var totalPages = (int)Math.Ceiling(totalItems / (double)pageSize);

        var stocks = await query
            .OrderBy(s => s.WarehouseId)
            .ThenBy(s => s.ProductId)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .Select(s => new StockResponseDto
            {
                Id = s.Id,
                ProductId = s.ProductId,
                WarehouseId = s.WarehouseId,
                WarehouseCode = s.Warehouse != null ? s.Warehouse.Code : null,
                WarehouseName = s.Warehouse != null ? s.Warehouse.Name : null,
                QuantityOnHand = s.QuantityOnHand,
                QuantityReserved = s.QuantityReserved,
                Available = s.QuantityOnHand - s.QuantityReserved,
                MinStock = s.MinStock,
                IsLowStock = (s.QuantityOnHand - s.QuantityReserved) <= s.MinStock,
                Version = s.Version,
                UpdatedAt = s.UpdatedAt
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

        return Ok(ApiEnvelope<List<StockResponseDto>>.Ok(stocks, meta));
    }

    /// <summary>
    /// Chi tiết tồn kho của 1 sản phẩm tại tất cả các kho.
    /// </summary>
    [HttpGet("{productId:long}")]
    public async Task<IActionResult> GetStockByProductId(long productId, CancellationToken ct = default)
    {
        var stocks = await _context.InventoryStocks
            .AsNoTracking()
            .Include(s => s.Warehouse)
            .Where(s => s.ProductId == productId)
            .OrderBy(s => s.WarehouseId)
            .ToListAsync(ct);

        if (stocks.Count == 0)
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
            TotalOnHand = stocks.Sum(s => s.QuantityOnHand),
            TotalReserved = stocks.Sum(s => s.QuantityReserved),
            TotalAvailable = stocks.Sum(s => s.Available),
            Warehouses = stocks.Select(s => new WarehouseStockBreakdownDto
            {
                WarehouseId = s.WarehouseId,
                WarehouseCode = s.Warehouse?.Code ?? string.Empty,
                WarehouseName = s.Warehouse?.Name ?? string.Empty,
                QuantityOnHand = s.QuantityOnHand,
                QuantityReserved = s.QuantityReserved,
                Available = s.Available,
                MinStock = s.MinStock,
                IsLowStock = s.Available <= s.MinStock
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
    /// Lịch sử sổ cái thẻ kho (Audit Log).
    /// </summary>
    [HttpGet("movements")]
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

        var query = _context.StockMovements
            .AsNoTracking()
            .Include(m => m.Warehouse)
            .AsQueryable();

        if (productId.HasValue)
            query = query.Where(m => m.ProductId == productId.Value);

        if (warehouseId.HasValue)
            query = query.Where(m => m.WarehouseId == warehouseId.Value);

        if (!string.IsNullOrWhiteSpace(movementType))
            query = query.Where(m => m.MovementType == movementType);

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
                MovementType = m.MovementType,
                QuantityDelta = m.QuantityDelta,
                BalanceOnHandAfter = m.BalanceOnHandAfter,
                BalanceReservedAfter = m.BalanceReservedAfter,
                ReferenceDoc = m.ReferenceDoc,
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

    #endregion

    #region Command Side (Atomic Operations & SCM State Mutations)

    /// <summary>
    /// Khóa giữ chỗ tồn kho (Reserve) cho một sản phẩm theo đơn bán hàng SO.
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
    /// Khóa giữ chỗ theo lô (Batch Reserve) nhiều mặt hàng trong cùng đơn hàng (triệt tiêu Deadlock).
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
    /// Giải phóng giữ chỗ (Release) khi đơn bán hàng bị hủy hoặc quá hạn.
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
    /// </summary>
    [HttpPost("receive")]
    public async Task<IActionResult> ReceiveStock([FromBody] ReceiveStockRequest request, CancellationToken ct)
    {
        var command = new ReceiveStockCommand(
            ReferenceDoc: request.ReferenceDoc,
            WarehouseId: request.WarehouseId,
            ProductId: request.ProductId,
            Quantity: request.Quantity,
            MinStock: request.MinStock
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
