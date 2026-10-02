using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using Purchase.Domain.Entities;

namespace Purchase.Infrastructure.Persistence.Configurations;

public class OutboxEventConfiguration : IEntityTypeConfiguration<OutboxEvent>
{
    public void Configure(EntityTypeBuilder<OutboxEvent> builder)
    {
        builder.ToTable("outbox_events");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.EventId)
            .IsRequired()
            .HasMaxLength(100);

        builder.HasIndex(x => x.EventId)
            .IsUnique();

        builder.Property(x => x.EventType).IsRequired().HasMaxLength(100);
        builder.Property(x => x.AggregateType).IsRequired().HasMaxLength(100);
        builder.Property(x => x.AggregateId).IsRequired().HasMaxLength(100);
        builder.Property(x => x.RoutingKey).IsRequired().HasMaxLength(150);
        builder.Property(x => x.Status).IsRequired().HasMaxLength(30).HasDefaultValue("PENDING");

        builder.HasIndex(x => new { x.Status, x.CreatedAt });
    }
}

public class ProcessedEventConfiguration : IEntityTypeConfiguration<ProcessedEvent>
{
    public void Configure(EntityTypeBuilder<ProcessedEvent> builder)
    {
        builder.ToTable("processed_events");

        builder.HasKey(x => new { x.EventId, x.ConsumerName });

        builder.Property(x => x.EventId).HasMaxLength(100);
        builder.Property(x => x.ConsumerName).HasMaxLength(100);
    }
}
