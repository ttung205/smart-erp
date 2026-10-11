using Inventory.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace Inventory.Infrastructure.Persistence;

public class InventoryDbContext : DbContext
{
    public InventoryDbContext(DbContextOptions<InventoryDbContext> options) : base(options)
    {
    }

    public DbSet<Warehouse> Warehouses => Set<Warehouse>();
    public DbSet<InventoryStock> InventoryStocks => Set<InventoryStock>();
    public DbSet<InventoryStockReservation> InventoryStockReservations => Set<InventoryStockReservation>();
    public DbSet<InventoryStockTransaction> InventoryStockTransactions => Set<InventoryStockTransaction>();
    public DbSet<InventoryStockReservationTransaction> InventoryStockReservationTransactions => Set<InventoryStockReservationTransaction>();
    public DbSet<OutboxEvent> OutboxEvents => Set<OutboxEvent>();
    public DbSet<ProcessedEvent> ProcessedEvents => Set<ProcessedEvent>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);

        // 1. Warehouse Configuration
        modelBuilder.Entity<Warehouse>(entity =>
        {
            entity.ToTable("warehouses");
            entity.HasKey(w => w.Id);
            entity.Property(w => w.Id).HasColumnName("id");
            entity.Property(w => w.Code).HasColumnName("code").HasMaxLength(50).IsRequired();
            entity.Property(w => w.Name).HasColumnName("name").HasMaxLength(255).IsRequired();
            entity.Property(w => w.Location).HasColumnName("location").HasMaxLength(500);
            entity.Property(w => w.ManagerId).HasColumnName("manager_id");
            entity.Property(w => w.IsActive).HasColumnName("is_active").HasDefaultValue(true);
            entity.Property(w => w.CanSell).HasColumnName("can_sell").HasDefaultValue(true);
            entity.Property(w => w.CreatedAt).HasColumnName("created_at");
            entity.Property(w => w.UpdatedAt).HasColumnName("updated_at");

            entity.HasIndex(w => w.Code).IsUnique();
        });

        // 2. InventoryStock Configuration (Physical On-Hand Balance)
        modelBuilder.Entity<InventoryStock>(entity =>
        {
            entity.ToTable("inventory_stocks");
            entity.HasKey(s => s.Id);
            entity.Property(s => s.Id).HasColumnName("id");
            entity.Property(s => s.WarehouseId).HasColumnName("warehouse_id").IsRequired();
            entity.Property(s => s.ProductId).HasColumnName("product_id").IsRequired();
            entity.Property(s => s.Quantity).HasColumnName("quantity").HasDefaultValue(0);
            entity.Property(s => s.UpdatedAt).HasColumnName("updated_at");

            // Unique constraint: warehouse_id + product_id
            entity.HasIndex(s => new { s.WarehouseId, s.ProductId }).IsUnique();

            // Foreign Key to Warehouse
            entity.HasOne(s => s.Warehouse)
                  .WithMany(w => w.Stocks)
                  .HasForeignKey(s => s.WarehouseId)
                  .OnDelete(DeleteBehavior.Restrict);
        });

        // 3. InventoryStockReservation Configuration (Available / Reservation Balance)
        modelBuilder.Entity<InventoryStockReservation>(entity =>
        {
            entity.ToTable("inventory_stocks_reservation");
            entity.HasKey(r => r.Id);
            entity.Property(r => r.Id).HasColumnName("id");
            entity.Property(r => r.WarehouseId).HasColumnName("warehouse_id").IsRequired();
            entity.Property(r => r.ProductId).HasColumnName("product_id").IsRequired();
            entity.Property(r => r.Quantity).HasColumnName("quantity").HasDefaultValue(0);
            entity.Property(r => r.UpdatedAt).HasColumnName("updated_at");

            // Unique constraint: warehouse_id + product_id
            entity.HasIndex(r => new { r.WarehouseId, r.ProductId }).IsUnique();

            entity.HasOne(r => r.Warehouse)
                  .WithMany(w => w.Reservations)
                  .HasForeignKey(r => r.WarehouseId)
                  .OnDelete(DeleteBehavior.Restrict);
        });

        // 4. InventoryStockTransaction Configuration (Physical Stock Ledger)
        modelBuilder.Entity<InventoryStockTransaction>(entity =>
        {
            entity.ToTable("inventory_stocks_transaction");
            entity.HasKey(m => m.Id);
            entity.Property(m => m.Id).HasColumnName("id");
            entity.Property(m => m.WarehouseId).HasColumnName("warehouse_id").IsRequired();
            entity.Property(m => m.ProductId).HasColumnName("product_id").IsRequired();
            entity.Property(m => m.DocumentType).HasColumnName("document_type").HasMaxLength(100).IsRequired();
            entity.Property(m => m.DocumentId).HasColumnName("document_id").HasMaxLength(100).IsRequired();
            entity.Property(m => m.BalanceBefore).HasColumnName("balance_before").IsRequired();
            entity.Property(m => m.QuantityDelta).HasColumnName("quantity_delta").IsRequired();
            entity.Property(m => m.BalanceAfter).HasColumnName("balance_after").IsRequired();
            entity.Property(m => m.Notes).HasColumnName("notes").HasMaxLength(500);
            entity.Property(m => m.CreatedBy).HasColumnName("created_by");
            entity.Property(m => m.CreatedAt).HasColumnName("created_at");

            entity.HasIndex(m => new { m.ProductId, m.WarehouseId, m.CreatedAt });
            entity.HasIndex(m => new { m.DocumentType, m.DocumentId });

            entity.HasOne(m => m.Warehouse)
                  .WithMany()
                  .HasForeignKey(m => m.WarehouseId)
                  .OnDelete(DeleteBehavior.Restrict);
        });

        // 5. InventoryStockReservationTransaction Configuration (Reservation Stock Ledger)
        modelBuilder.Entity<InventoryStockReservationTransaction>(entity =>
        {
            entity.ToTable("inventory_stocks_reservation_transaction");
            entity.HasKey(m => m.Id);
            entity.Property(m => m.Id).HasColumnName("id");
            entity.Property(m => m.WarehouseId).HasColumnName("warehouse_id").IsRequired();
            entity.Property(m => m.ProductId).HasColumnName("product_id").IsRequired();
            entity.Property(m => m.DocumentType).HasColumnName("document_type").HasMaxLength(100).IsRequired();
            entity.Property(m => m.DocumentId).HasColumnName("document_id").HasMaxLength(100).IsRequired();
            entity.Property(m => m.BalanceBefore).HasColumnName("balance_before").IsRequired();
            entity.Property(m => m.QuantityDelta).HasColumnName("quantity_delta").IsRequired();
            entity.Property(m => m.BalanceAfter).HasColumnName("balance_after").IsRequired();
            entity.Property(m => m.Notes).HasColumnName("notes").HasMaxLength(500);
            entity.Property(m => m.CreatedBy).HasColumnName("created_by");
            entity.Property(m => m.CreatedAt).HasColumnName("created_at");

            entity.HasIndex(m => new { m.ProductId, m.WarehouseId, m.CreatedAt });
            entity.HasIndex(m => new { m.DocumentType, m.DocumentId });

            entity.HasOne(m => m.Warehouse)
                  .WithMany()
                  .HasForeignKey(m => m.WarehouseId)
                  .OnDelete(DeleteBehavior.Restrict);
        });

        // 6. OutboxEvent Configuration
        modelBuilder.Entity<OutboxEvent>(entity =>
        {
            entity.ToTable("outbox_events");
            entity.HasKey(e => e.Id);
            entity.Property(e => e.Id).HasColumnName("id");
            entity.Property(e => e.EventId).HasColumnName("event_id").HasMaxLength(100).IsRequired();
            entity.Property(e => e.EventType).HasColumnName("event_type").HasMaxLength(100).IsRequired();
            entity.Property(e => e.AggregateType).HasColumnName("aggregate_type").HasMaxLength(100).IsRequired();
            entity.Property(e => e.AggregateId).HasColumnName("aggregate_id").HasMaxLength(100).IsRequired();
            entity.Property(e => e.RoutingKey).HasColumnName("routing_key").HasMaxLength(150).IsRequired();
            entity.Property(e => e.Payload).HasColumnName("payload").IsRequired();
            entity.Property(e => e.Status).HasColumnName("status").HasMaxLength(30).HasDefaultValue("PENDING");
            entity.Property(e => e.RetryCount).HasColumnName("retry_count").HasDefaultValue(0);
            entity.Property(e => e.LastError).HasColumnName("last_error");
            entity.Property(e => e.PublishedAt).HasColumnName("published_at");
            entity.Property(e => e.CreatedAt).HasColumnName("created_at");

            entity.HasIndex(e => e.EventId).IsUnique();
            entity.HasIndex(e => new { e.Status, e.CreatedAt });
        });

        // 7. ProcessedEvent Configuration (Idempotency)
        modelBuilder.Entity<ProcessedEvent>(entity =>
        {
            entity.ToTable("processed_events");
            entity.HasKey(p => new { p.EventId, p.ConsumerName });
            entity.Property(p => p.EventId).HasColumnName("event_id").HasMaxLength(100);
            entity.Property(p => p.ConsumerName).HasColumnName("consumer_name").HasMaxLength(100);
            entity.Property(p => p.ProcessedAt).HasColumnName("processed_at");
        });
    }
}
