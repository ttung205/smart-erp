using Chat.Domain.Entities;
using Chat.Domain.Enums;
using Microsoft.EntityFrameworkCore;

namespace Chat.Infrastructure.Persistence;

public static class DbInitializer
{
    public static async Task SeedAsync(ChatDbContext context)
    {
        // Tự động tạo DB nếu chưa có
        await context.Database.EnsureCreatedAsync();

        // Cấu hình WAL mode và busy timeout để tăng cường hiệu năng & tránh lock file
        try
        {
            await context.Database.ExecuteSqlRawAsync("PRAGMA journal_mode = WAL;");
            await context.Database.ExecuteSqlRawAsync("PRAGMA busy_timeout = 5000;");
        }
        catch
        {
            // Bỏ qua nếu DB provider không hỗ trợ PRAGMA
        }

        // Kiểm tra xem đã có dữ liệu kênh mặc định chưa
        if (await context.Conversations.AnyAsync())
        {
            return;
        }

        var defaultChannels = new List<Conversation>
        {
            new()
            {
                Id = Guid.NewGuid(),
                Type = ConversationType.Channel,
                Code = "dept:general",
                Name = "#chung",
                DepartmentCode = "general",
                Description = "Kênh thông báo & trao đổi toàn bộ cán bộ nhân viên công ty",
                CreatedAt = DateTime.UtcNow
            },
            new()
            {
                Id = Guid.NewGuid(),
                Type = ConversationType.Channel,
                Code = "dept:inventory",
                Name = "#kho-van",
                DepartmentCode = "inventory",
                Description = "Kênh trao đổi bộ phận Kho Vận & Điều Vận (LOGISTIC)",
                CreatedAt = DateTime.UtcNow
            },
            new()
            {
                Id = Guid.NewGuid(),
                Type = ConversationType.Channel,
                Code = "dept:purchase",
                Name = "#thu-mua",
                DepartmentCode = "purchase",
                Description = "Kênh trao đổi bộ phận Mua Hàng & Cung Ứng (PURCHASE)",
                CreatedAt = DateTime.UtcNow
            },
            new()
            {
                Id = Guid.NewGuid(),
                Type = ConversationType.Channel,
                Code = "dept:sales",
                Name = "#kinh-doanh",
                DepartmentCode = "sales",
                Description = "Kênh trao đổi bộ phận Kinh Doanh & Bán Hàng (SALES)",
                CreatedAt = DateTime.UtcNow
            },
            new()
            {
                Id = Guid.NewGuid(),
                Type = ConversationType.Channel,
                Code = "dept:finance",
                Name = "#tai-chinh",
                DepartmentCode = "finance",
                Description = "Kênh trao đổi bộ phận Tài Chính & Kế Toán (FINANCE)",
                CreatedAt = DateTime.UtcNow
            },
            new()
            {
                Id = Guid.NewGuid(),
                Type = ConversationType.Channel,
                Code = "dept:technical",
                Name = "#ky-thuat",
                DepartmentCode = "technical",
                Description = "Kênh trao đổi bộ phận Kỹ Thuật & Công Nghệ (TECHNICAL)",
                CreatedAt = DateTime.UtcNow
            }
        };

        await context.Conversations.AddRangeAsync(defaultChannels);
        await context.SaveChangesAsync();

        // Tạo tin nhắn chào mừng mặc định của hệ thống trong kênh #chung
        var generalChannel = defaultChannels.First(c => c.Code == "dept:general");
        var welcomeMessage = new ChatMessage
        {
            Id = Guid.NewGuid(),
            ConversationId = generalChannel.Id,
            SenderId = 0,
            SenderName = "ERP System Bot",
            SenderAvatar = "/images/avatars/bot-avatar.png",
            MessageType = MessageType.SystemBot,
            Content = "Chào mừng bạn đến với hệ thống Chat Realtime nội bộ Smart Distributed ERP! Mọi thông báo nghiệp vụ liên quan đến Đơn hàng, Mua hàng và Cảnh báo tồn kho sẽ được thông báo tự động tại các kênh phòng ban tương ứng.",
            CreatedAt = DateTime.UtcNow
        };

        await context.ChatMessages.AddAsync(welcomeMessage);
        await context.SaveChangesAsync();
    }
}
