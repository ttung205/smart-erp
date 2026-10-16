using Chat.Application.Common.Interfaces;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Hosting;

namespace Chat.Infrastructure.Storage;

/// <summary>Lưu tệp trên đĩa (thư mục chat-files), tên tệp là GUID để không đoán được và không trùng.</summary>
public sealed class LocalFileStorage : IFileStorage
{
    private readonly string _root;

    public LocalFileStorage(IConfiguration configuration, IHostEnvironment env)
    {
        _root = Path.GetFullPath(configuration["Storage:Root"] ?? Path.Combine(env.ContentRootPath, "chat-files"));
        Directory.CreateDirectory(_root);
    }

    public async Task<string> SaveAsync(Stream content, string extension, CancellationToken ct)
    {
        var relative = Path.Combine(DateTime.UtcNow.ToString("yyyyMM"), $"{Guid.NewGuid():N}{extension}");
        var full = Resolve(relative);
        Directory.CreateDirectory(Path.GetDirectoryName(full)!);
        await using var output = File.Create(full);
        await content.CopyToAsync(output, ct);
        return relative.Replace('\\', '/');
    }

    public async Task<string> CopyAsync(string storagePath, CancellationToken ct)
    {
        await using var source = OpenRead(storagePath);
        return await SaveAsync(source, Path.GetExtension(storagePath), ct);
    }

    public Stream OpenRead(string storagePath) => File.OpenRead(Resolve(storagePath));

    public void Delete(string storagePath)
    {
        var full = Resolve(storagePath);
        if (File.Exists(full)) File.Delete(full);
    }

    /// <summary>Chặn đường dẫn thoát khỏi thư mục gốc.</summary>
    private string Resolve(string relative)
    {
        var full = Path.GetFullPath(Path.Combine(_root, relative));
        if (!full.StartsWith(_root + Path.DirectorySeparatorChar, StringComparison.OrdinalIgnoreCase))
            throw new InvalidOperationException("Đường dẫn tệp không hợp lệ.");
        return full;
    }
}
