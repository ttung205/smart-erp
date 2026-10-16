using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Design;

namespace Chat.Infrastructure.Persistence;

/// <summary>Cho lệnh dotnet ef tạo migration mà không cần khởi động Chat.Api.</summary>
public class DesignTimeDbContextFactory : IDesignTimeDbContextFactory<ChatDbContext>
{
    public ChatDbContext CreateDbContext(string[] args) =>
        new(new DbContextOptionsBuilder<ChatDbContext>().UseSqlite("Data Source=chat-messenger.db").Options);
}
