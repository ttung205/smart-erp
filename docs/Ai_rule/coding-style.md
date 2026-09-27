# CODING STYLE GUIDELINES — PHP (Laravel) & C# (.NET 8)

> **Mục tiêu**: Đảm bảo sự nhất quán tuyệt đối về cách đặt tên, kiến trúc lớp, quản lý thời gian và phong cách viết mã sạch (Clean Code) giữa hai ngôn ngữ lập trình chủ lực trong dự án.

---

## 1. MA TRẬN ĐẶT TÊN NHẤT QUÁN (NAMING CONVENTIONS)

| Thành Phần Code | C# (.NET 8) | PHP (Laravel 11/12) | Ví Dụ C# | Ví Dụ PHP |
|---|---|---|---|---|
| **Class / Entity / Model** | PascalCase | PascalCase | `SalesOrder`, `InventoryStock` | `SalesOrder`, `InventoryStock` |
| **Interface** | `I` + PascalCase | `I` + PascalCase hoặc `...Interface` | `IStockRepository`, `ISagaManager` | `StockRepositoryInterface` |
| **Method / Function** | PascalCase (+ `Async` nếu bất đồng bộ) | camelCase | `ReserveStockAsync()`, `GetByIdAsync()` | `reserveStock()`, `getById()` |
| **Property / Field công khai** | PascalCase | camelCase | `QuantityOnHand`, `GrossPrice` | `$quantityOnHand`, `$grossPrice` |
| **Private Field** | `_` + camelCase | camelCase | `_stockRepository`, `_logger` | `$this->stockRepository` |
| **Local Variable & Parameter** | camelCase | camelCase | `orderId`, `requestedQty` | `$orderId`, `$requestedQty` |
| **Hằng số (Constants) & Enum** | PascalCase (C#) / SCREAMING_SNAKE (PHP) | SCREAMING_SNAKE_CASE | `OrderStatus.PendingReservation` | `OrderStatus::PENDING_RESERVATION` |
| **JSON Payload Keys trên Wire** | **snake_case** | **snake_case** | `[JsonPropertyName("product_id")]` | `'product_id' => $this->product_id` |
| **Database Table & Column** | snake_case | snake_case | `inventory_stocks.quantity_on_hand` | `inventory_stocks.quantity_on_hand` |

---

## 2. QUY CHUẨN C# (.NET 8 WEB API)

### 2.1. Cấm Trả Về Trực Tiếp Domain Entity Ra Controller
```csharp
// ❌ SAI LẦM: Trả trực tiếp Entity ra ngoài API
[HttpGet("{id}")]
public async Task<IActionResult> Get(int id)
{
    var order = await _orderRepo.GetByIdAsync(id);
    return Ok(order); // Vi phạm Clean Architecture & rò rỉ cấu trúc DB
}

// ✅ CHUẨN MỰC: Luôn chuyển đổi sang DTO / Response ViewModel
[HttpGet("{id}")]
public async Task<IActionResult> Get(int id, CancellationToken ct)
{
    var order = await _orderRepo.GetByIdAsync(id, ct);
    if (order is null) return NotFound(ApiEnvelope.Fail("RESOURCE_NOT_FOUND", "Không tìm thấy đơn hàng."));

    var responseDto = new SalesOrderResponseDto
    {
        Id = order.Id,
        OrderCode = order.Code,
        TotalAmount = order.TotalAmount,
        Status = order.Status.ToString()
    };
    return Ok(ApiEnvelope.Success(responseDto));
}
```

### 2.2. Quy Tắc Bắt Buộc: Truy Vấn Chỉ Đọc Phải Có `.AsNoTracking()`
Khi query lấy dữ liệu để hiển thị hoặc tính toán không cần lưu lại trạng thái (Change Tracking), **bắt buộc** gọi `.AsNoTracking()` để giải phóng bộ nhớ và tăng tốc độ tối đa:
```csharp
// ✅ CHUẨN HIỆU NĂNG CAO:
var stocks = await _context.InventoryStocks
    .AsNoTracking()
    .Where(s => s.WarehouseId == warehouseId)
    .ToListAsync(cancellationToken);
```

### 2.3. Quy Tắc Thời Gian: Luôn Dùng `DateTime.UtcNow`
* Nghiêm cấm dùng `DateTime.Now` (vì sẽ phụ thuộc vào múi giờ của máy chủ server, gây lệch sổ kho và sự kiện RabbitMQ).
* Luôn lưu và xử lý bằng `DateTime.UtcNow`.

### 2.4. Bất Đồng Bộ (Async/Await) & CancellationToken
* Mọi thao tác I/O (Database, Network, RabbitMQ) đều phải là `async/await`.
* Luôn truyền `CancellationToken` vào các hàm Async của EF Core (`ToListAsync`, `FirstOrDefaultAsync`, `SaveChangesAsync`).
* Tuyệt đối không dùng `.Result` hoặc `.Wait()` vì sẽ gây Thread Pool Starvation / Deadlock.

### 2.5. Cấu Hình JSON Naming Policy Snake Case
Trong `Program.cs`:
```csharp
builder.Services.AddControllers()
    .AddJsonOptions(options =>
    {
        options.JsonSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower;
        options.JsonSerializerOptions.DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull;
    });
```

---

## 3. QUY CHUẨN PHP (LARAVEL 11/12)

### 3.1. Phân Tách DTO & FormRequest
* Mọi request ghi dữ liệu (`POST`, `PUT`, `PATCH`) bắt buộc phải tạo `FormRequest` riêng để kiểm tra validate dữ liệu đầu vào.
* Phản hồi dữ liệu ra ngoài Controller bắt buộc dùng `JsonResource`:
```php
// ✅ CHUẨN MỰC:
public function show(int $id): JsonResponse
{
    $product = DB::table('products')->where('id', $id)->first();
    if (!$product) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Không tìm thấy sản phẩm.']
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => new ProductResource($product),
        'meta' => ['timestamp' => now()->toIso8601String()]
    ]);
}
```

### 3.2. Quy Tắc Thời Gian
* Luôn sử dụng `now()->toIso8601String()` hoặc `now()->utc()` để ghi nhận thời điểm committed dữ liệu.

### 3.3. Tối Ưu Truy Vấn Chỉ Đọc
* Không sử dụng `SELECT *` hoặc hydrate toàn bộ Eloquent Model nếu chỉ cần đọc vài trường dữ liệu.
* Dùng `DB::table(...)` hoặc `->select(['id', 'sku', 'name', 'gross_price'])` để đạt hiệu năng tối ưu.

---

## 4. NGUYÊN TẮC THIẾT KẾ ĐƠN GIẢN (KISS & DRY)

1. **Không tạo lớp trung gian thừa thãi (No Over-Engineering)**:
   - Nếu một thao tác nghiệp vụ đơn giản và không có kế hoạch thay đổi logic, không cần tạo ra 3-4 interface rỗng chỉ để wrap 1 dòng code.
   - Luôn đặt câu hỏi: *"Viết thế này có ai hiểu được ngay không? Có test được ngay không?"*.
2. **Không nuốt lỗi (No Silent Failures)**:
   - Nghiêm cấm đặt khối `try-catch` trống rỗng. Nếu bắt exception, phải ghi log kèm `correlation_id` hoặc re-throw custom domain exception.
