using Chat.Domain.Entities;
using MongoDB.Bson;
using MongoDB.Bson.Serialization;
using MongoDB.Bson.Serialization.Conventions;
using MongoDB.Bson.Serialization.Serializers;
using MongoDB.Driver;

namespace Chat.Infrastructure.Persistence;

/// <summary>
/// Kết nối MongoDB của chat-service: các collection, index và bộ đếm id.
/// Id hội thoại, tin nhắn, tệp đính kèm là số nguyên tăng dần (client so sánh id để biết tin mới/cũ, con trỏ đã đọc
/// cũng dựa vào đó), nên sinh từ collection "counters" thay vì ObjectId.
/// </summary>
public sealed class MongoChatContext
{
    private static readonly object MappingLock = new();
    private static bool _mapped;

    public IMongoDatabase Database { get; }
    public IMongoCollection<Conversation> Conversations { get; }
    public IMongoCollection<ChatMessage> Messages { get; }
    public IMongoCollection<MessageHide> Hides { get; }
    public IMongoCollection<ContactAlias> Aliases { get; }
    private IMongoCollection<BsonDocument> Counters { get; }

    public MongoChatContext(IMongoClient client, string databaseName)
    {
        RegisterMapping();
        Database = client.GetDatabase(databaseName);
        Conversations = Database.GetCollection<Conversation>("conversations");
        Messages = Database.GetCollection<ChatMessage>("chat_messages");
        Hides = Database.GetCollection<MessageHide>("message_hides");
        Aliases = Database.GetCollection<ContactAlias>("contact_aliases");
        Counters = Database.GetCollection<BsonDocument>("counters");
    }

    /// <summary>Tên thuộc tính Id thành _id; enum lưu dạng chuỗi; mọi thời điểm là UTC để JSON có hậu tố Z.</summary>
    private static void RegisterMapping()
    {
        lock (MappingLock)
        {
            if (_mapped) return;
            ConventionRegistry.Register("chat-conventions", new ConventionPack
            {
                new EnumRepresentationConvention(BsonType.String),
                new IgnoreExtraElementsConvention(true)
            }, type => type.Namespace == "Chat.Domain.Entities");
            BsonSerializer.RegisterSerializer(new DateTimeSerializer(DateTimeKind.Utc));
            _mapped = true;
        }
    }

    public async Task EnsureIndexesAsync(CancellationToken ct = default)
    {
        var conversations = Builders<Conversation>.IndexKeys;
        var messages = Builders<ChatMessage>.IndexKeys;

        await Conversations.Indexes.CreateManyAsync(
        [
            // Mỗi cặp người dùng chỉ có một hội thoại 1-1; nhóm không có DirectKey nên không bị tính
            new CreateIndexModel<Conversation>(conversations.Ascending(c => c.DirectKey), new CreateIndexOptions<Conversation>
            {
                Unique = true,
                PartialFilterExpression = new BsonDocument("DirectKey", new BsonDocument("$type", "string"))
            }),
            new CreateIndexModel<Conversation>(conversations.Ascending("Members.UserId")),
            new CreateIndexModel<Conversation>(conversations.Ascending(c => c.SubjectType).Ascending(c => c.SubjectId)),
        ], ct);

        await Messages.Indexes.CreateManyAsync(
        [
            new CreateIndexModel<ChatMessage>(messages.Ascending(m => m.ConversationId).Descending(m => m.Id)),
            new CreateIndexModel<ChatMessage>(messages.Ascending(m => m.ConversationId).Ascending(m => m.PinnedAt)),
            new CreateIndexModel<ChatMessage>(messages.Ascending("Attachments._id")),
        ], ct);

        await Hides.Indexes.CreateOneAsync(new CreateIndexModel<MessageHide>(
            Builders<MessageHide>.IndexKeys.Ascending(h => h.UserId).Ascending(h => h.ConversationId)), cancellationToken: ct);
        await Aliases.Indexes.CreateOneAsync(new CreateIndexModel<ContactAlias>(
            Builders<ContactAlias>.IndexKeys.Ascending(a => a.OwnerId)), cancellationToken: ct);
    }

    /// <summary>Cấp <paramref name="count"/> id liên tiếp và trả về id đầu tiên.</summary>
    public async Task<long> NextIdsAsync(string sequence, int count, CancellationToken ct)
    {
        var counter = await Counters.FindOneAndUpdateAsync(
            Builders<BsonDocument>.Filter.Eq("_id", sequence),
            Builders<BsonDocument>.Update.Inc("seq", (long)count),
            new FindOneAndUpdateOptions<BsonDocument> { IsUpsert = true, ReturnDocument = ReturnDocument.After },
            ct);
        return counter["seq"].ToInt64() - count + 1;
    }

    public async Task<bool> PingAsync(CancellationToken ct)
    {
        await Database.RunCommandAsync<BsonDocument>(new BsonDocument("ping", 1), cancellationToken: ct);
        return true;
    }
}
