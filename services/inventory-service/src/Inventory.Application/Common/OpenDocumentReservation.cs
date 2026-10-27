namespace Inventory.Application.Common;

/// <summary>
/// Chứng từ đang giữ hàng (có dòng giữ chỗ, chưa có dòng nhả). Dùng để service sở hữu chứng từ đối soát:
/// chứng từ không còn tồn tại / đã hủy bên đó mà Inventory vẫn giữ hàng thì nhả ra.
/// </summary>
public sealed record OpenDocumentReservation(string DocumentId, DateTime ReservedAt, IReadOnlyList<OpenDocumentReservationLine> Lines);

public sealed record OpenDocumentReservationLine(long WarehouseId, long ProductId, int Quantity);
