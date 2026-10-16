using System.Text;
using System.Text.Json;
using Chat.Application.Common.Interfaces;
using Chat.Application.DTOs;
using Chat.Domain.Entities;
using Chat.Domain.Enums;
using Chat.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using RabbitMQ.Client;
using RabbitMQ.Client.Events;

namespace Chat.Infrastructure.Messaging;

public class RabbitMqBotConsumerWorker : BackgroundService
{
    private readonly RabbitMqConnection _connection;
    private readonly IServiceScopeFactory _scopeFactory;
    private readonly ILogger<RabbitMqBotConsumerWorker> _logger;

    public RabbitMqBotConsumerWorker(
        RabbitMqConnection connection,
        IServiceScopeFactory scopeFactory,
        ILogger<RabbitMqBotConsumerWorker> logger)
    {
        _connection = connection;
        _scopeFactory = scopeFactory;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("Khởi động RabbitMqBotConsumerWorker...");

        while (!stoppingToken.IsCancellationRequested)
        {
            if (!_connection.IsConnected)
            {
                if (!_connection.TryConnect())
                {
                    try
                    {
                        await Task.Delay(TimeSpan.FromSeconds(5), stoppingToken);
                    }
                    catch (OperationCanceledException)
                    {
                        break;
                    }
                    continue;
                }
            }

            try
            {
                using var channel = _connection.CreateModel();
                if (channel == null)
                {
                    await Task.Delay(TimeSpan.FromSeconds(5), stoppingToken);
                    continue;
                }

                channel.BasicQos(prefetchSize: 0, prefetchCount: 10, global: false);

                var consumer = new AsyncEventingBasicConsumer(channel);
                consumer.Received += async (_, ea) =>
                {
                    try
                    {
                        var rawJson = Encoding.UTF8.GetString(ea.Body.ToArray());
                        var routingKey = ea.RoutingKey;

                        _logger.LogInformation("Nhận event RabbitMQ: {RoutingKey}", routingKey);

                        await ProcessEventMessageAsync(routingKey, rawJson, stoppingToken);

                        channel.BasicAck(ea.DeliveryTag, multiple: false);
                    }
                    catch (Exception ex)
                    {
                        _logger.LogError(ex, "Lỗi khi xử lý tin nhắn sự kiện RabbitMQ: {RoutingKey}", ea.RoutingKey);
                        channel.BasicNack(ea.DeliveryTag, multiple: false, requeue: false);
                    }
                };

                channel.BasicConsume(
                    queue: RabbitMqConnection.ChatQueueName,
                    autoAck: false,
                    consumer: consumer);

                _logger.LogInformation("RabbitMqBotConsumerWorker đã bắt đầu lắng nghe queue {Queue}", RabbitMqConnection.ChatQueueName);

                // Giữ vòng lặp chạy đến khi bị cancel hoặc channel bị đóng
                while (!stoppingToken.IsCancellationRequested && channel.IsOpen)
                {
                    await Task.Delay(1000, stoppingToken);
                }
            }
            catch (OperationCanceledException)
            {
                break;
            }
            catch (Exception ex)
            {
                _logger.LogWarning(ex, "Mất kết nối hoặc lỗi trong vòng lặp RabbitMQ consumer. Thử kết nối lại sau 5s...");
                try
                {
                    await Task.Delay(TimeSpan.FromSeconds(5), stoppingToken);
                }
                catch (OperationCanceledException)
                {
                    break;
                }
            }
        }

        _logger.LogInformation("RabbitMqBotConsumerWorker đã dừng.");
    }

    private async Task ProcessEventMessageAsync(string routingKey, string rawJson, CancellationToken ct)
    {
        string targetDepartment = "general";
        string botContent = string.Empty;

        using var doc = JsonDocument.Parse(rawJson);
        var root = doc.RootElement;

        // Trích xuất payload (hỗ trợ cả dạng bọc envelope payload hoặc root object)
        JsonElement payload = root;
        if (root.TryGetProperty("payload", out var pElement) && pElement.ValueKind == JsonValueKind.Object)
        {
            payload = pElement;
        }

        if (routingKey.StartsWith("catalog.product", StringComparison.OrdinalIgnoreCase))
        {
            targetDepartment = "inventory";
            var sku = GetString(payload, "sku");
            var name = GetString(payload, "name", "designation");
            var unit = GetString(payload, "unit", "unit_code", "com");
            var price = GetDecimal(payload, "gross_price", "price");

            botContent = $"[Sản Phẩm Mới] Mặt hàng '{name}' (SKU: {sku}) vừa được khởi tạo thành công trong hệ thống. Đơn giá: {price:N0} đ. Đề nghị bộ phận Kho chuẩn bị vị trí lưu trữ.";
        }
        else if (routingKey.StartsWith("purchase.order", StringComparison.OrdinalIgnoreCase))
        {
            targetDepartment = "purchase";
            var poCode = GetString(payload, "code", "po_code", "purchase_order_code");
            var supplier = GetString(payload, "supplier_name", "supplier");
            var total = GetDecimal(payload, "grand_total", "total_amount");

            botContent = $"[Đơn Mua Hàng] Đơn mua hàng '{poCode}' {(string.IsNullOrEmpty(supplier) ? "" : $"từ NCC {supplier} ")}vừa được cập nhật/phê duyệt (Tổng tiền: {total:N0} đ). Bộ phận Mua hàng chuẩn bị tiến độ nhận hàng.";
        }
        else if (routingKey.StartsWith("inventory.stock", StringComparison.OrdinalIgnoreCase))
        {
            targetDepartment = "inventory";
            var sku = GetString(payload, "sku");
            var availableQty = GetInt(payload, "available_quantity", "quantity_on_hand");
            var minStock = GetInt(payload, "min_stock");

            if (routingKey.Contains("low", StringComparison.OrdinalIgnoreCase) || availableQty <= minStock)
            {
                botContent = $"[Cảnh Báo Tồn Kho] Mặt hàng SKU '{sku}' hiện tại khả dụng còn {availableQty}, đã chạm ngưỡng tồn an toàn ({minStock}). Đề nghị lập đề xuất mua hàng (PR) ngay.";
            }
            else
            {
                botContent = $"[Biến Động Kho] Cập nhật tồn kho SKU '{sku}': Khả dụng hiện tại là {availableQty}.";
            }
        }
        else if (routingKey.StartsWith("sales.order", StringComparison.OrdinalIgnoreCase))
        {
            targetDepartment = "sales";
            var soCode = GetString(payload, "code", "so_code", "order_code");
            var total = GetDecimal(payload, "total_amount", "grand_total");
            var customer = GetString(payload, "customer_name", "customer");

            botContent = $"[Đơn Bán Hàng] Đơn bán hàng '{soCode}' {(string.IsNullOrEmpty(customer) ? "" : $"của khách {customer} ")}đã được khởi tạo/xác nhận thành công ({total:N0} đ).";
        }
        else if (routingKey.StartsWith("eoffice.leave", StringComparison.OrdinalIgnoreCase))
        {
            targetDepartment = "general";
            var employee = GetString(payload, "employee_name", "user_name");
            var fromDate = GetString(payload, "from_date", "start_date");
            var toDate = GetString(payload, "to_date", "end_date");

            botContent = $"[Nghỉ Phép] Đơn nghỉ phép của nhân sự '{employee}' đã được phê duyệt (từ ngày {fromDate} đến {toDate}).";
        }
        else
        {
            targetDepartment = "general";
            var eventType = root.TryGetProperty("event_type", out var et) ? et.GetString() : routingKey;
            botContent = $"[Hệ Thống ERP] Sự kiện nghiệp vụ '{eventType}' vừa diễn ra trong hệ thống.";
        }

        // Lưu bản ghi ChatMessage và phát Realtime qua SignalR Hub
        using var scope = _scopeFactory.CreateScope();
        var context = scope.ServiceProvider.GetRequiredService<ChatDbContext>();
        var hubService = scope.ServiceProvider.GetRequiredService<IChatHubService>();

        var deptCodeLower = targetDepartment.ToLower();
        var conversation = await context.Conversations
            .FirstOrDefaultAsync(c => c.DepartmentCode == deptCodeLower || c.Code == $"dept:{deptCodeLower}", ct);

        if (conversation == null)
        {
            // Fallback sang kênh #chung
            conversation = await context.Conversations
                .FirstOrDefaultAsync(c => c.Code == "dept:general", ct);
        }

        if (conversation == null)
        {
            _logger.LogWarning("Không tìm thấy kênh phù hợp để bot phát thông báo: {Dept}", targetDepartment);
            return;
        }

        var botMessage = new ChatMessage
        {
            Id = Guid.NewGuid(),
            ConversationId = conversation.Id,
            SenderId = 0,
            SenderName = "ERP System Bot",
            SenderAvatar = "/images/avatars/bot-avatar.png",
            MessageType = MessageType.SystemBot,
            Content = botContent,
            MetadataJson = rawJson,
            CreatedAt = DateTime.UtcNow
        };

        await context.ChatMessages.AddAsync(botMessage, ct);
        await context.SaveChangesAsync(ct);

        var dto = new ChatMessageDto
        {
            Id = botMessage.Id,
            ConversationId = botMessage.ConversationId,
            SenderId = botMessage.SenderId,
            SenderName = botMessage.SenderName,
            SenderAvatar = botMessage.SenderAvatar,
            MessageType = botMessage.MessageType.ToString().ToUpper(),
            Content = botMessage.Content,
            MetadataJson = botMessage.MetadataJson,
            CreatedAt = botMessage.CreatedAt,
            ReadBy = new List<int>()
        };

        await hubService.SendMessageToConversationAsync(conversation.Id, dto);
        if (!string.IsNullOrEmpty(conversation.DepartmentCode))
        {
            await hubService.SendMessageToChannelAsync(conversation.DepartmentCode, dto);
        }

        _logger.LogInformation("Bot đã gửi tin nhắn tự động vào kênh {Channel}: {Content}", conversation.Name, botContent);
    }

    private static string GetString(JsonElement element, params string[] propertyNames)
    {
        foreach (var prop in propertyNames)
        {
            if (element.TryGetProperty(prop, out var val))
            {
                var str = val.GetString();
                if (!string.IsNullOrEmpty(str)) return str;
            }
        }
        return string.Empty;
    }

    private static decimal GetDecimal(JsonElement element, params string[] propertyNames)
    {
        foreach (var prop in propertyNames)
        {
            if (element.TryGetProperty(prop, out var val))
            {
                if (val.ValueKind == JsonValueKind.Number && val.TryGetDecimal(out var dec)) return dec;
                if (val.ValueKind == JsonValueKind.String && decimal.TryParse(val.GetString(), out var sDec)) return sDec;
            }
        }
        return 0;
    }

    private static int GetInt(JsonElement element, params string[] propertyNames)
    {
        foreach (var prop in propertyNames)
        {
            if (element.TryGetProperty(prop, out var val))
            {
                if (val.ValueKind == JsonValueKind.Number && val.TryGetInt32(out var i)) return i;
                if (val.ValueKind == JsonValueKind.String && int.TryParse(val.GetString(), out var sI)) return sI;
            }
        }
        return 0;
    }
}
