using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Purchase.Infrastructure.Persistence.Migrations
{
    /// <inheritdoc />
    public partial class InitialCreate : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.CreateTable(
                name: "outbox_events",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    event_id = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    event_type = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    aggregate_type = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    aggregate_id = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    routing_key = table.Column<string>(type: "TEXT", maxLength: 150, nullable: false),
                    payload = table.Column<string>(type: "TEXT", nullable: false),
                    status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false, defaultValue: "PENDING"),
                    retry_count = table.Column<int>(type: "INTEGER", nullable: false),
                    last_error = table.Column<string>(type: "TEXT", nullable: true),
                    published_at = table.Column<DateTime>(type: "TEXT", nullable: true),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_outbox_events", x => x.id);
                });

            migrationBuilder.CreateTable(
                name: "processed_events",
                columns: table => new
                {
                    event_id = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    consumer_name = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    processed_at = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_processed_events", x => new { x.event_id, x.consumer_name });
                });

            migrationBuilder.CreateTable(
                name: "purchase_orders",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    code = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                    supplier_id = table.Column<long>(type: "INTEGER", nullable: false),
                    warehouse_id = table.Column<long>(type: "INTEGER", nullable: false),
                    status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    order_date = table.Column<DateTime>(type: "TEXT", nullable: false),
                    expected_delivery_date = table.Column<DateTime>(type: "TEXT", nullable: true),
                    currency = table.Column<string>(type: "TEXT", maxLength: 10, nullable: false, defaultValue: "VND"),
                    subtotal_amount = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    tax_amount = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    discount_amount = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    grand_total = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    payment_terms = table.Column<string>(type: "TEXT", nullable: true),
                    notes = table.Column<string>(type: "TEXT", nullable: true),
                    created_by = table.Column<long>(type: "INTEGER", nullable: false),
                    approved_by = table.Column<long>(type: "INTEGER", nullable: true),
                    approved_at = table.Column<DateTime>(type: "TEXT", nullable: true),
                    version = table.Column<int>(type: "INTEGER", nullable: false),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_orders", x => x.id);
                });

            migrationBuilder.CreateTable(
                name: "purchase_requisitions",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    code = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                    warehouse_id = table.Column<long>(type: "INTEGER", nullable: false),
                    requested_by = table.Column<long>(type: "INTEGER", nullable: false),
                    request_date = table.Column<DateTime>(type: "TEXT", nullable: false),
                    expected_needed_date = table.Column<DateTime>(type: "TEXT", nullable: false),
                    priority = table.Column<string>(type: "TEXT", maxLength: 20, nullable: false),
                    reason = table.Column<string>(type: "TEXT", nullable: true),
                    notes = table.Column<string>(type: "TEXT", nullable: true),
                    approval_status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    order_status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    approved_by = table.Column<long>(type: "INTEGER", nullable: true),
                    approved_at = table.Column<DateTime>(type: "TEXT", nullable: true),
                    rejected_reason = table.Column<string>(type: "TEXT", nullable: true),
                    version = table.Column<int>(type: "INTEGER", nullable: false),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_requisitions", x => x.id);
                });

            migrationBuilder.CreateTable(
                name: "goods_receipts",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    code = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                    purchase_order_id = table.Column<long>(type: "INTEGER", nullable: false),
                    warehouse_id = table.Column<long>(type: "INTEGER", nullable: false),
                    received_by = table.Column<long>(type: "INTEGER", nullable: false),
                    received_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    delivery_note_number = table.Column<string>(type: "TEXT", maxLength: 100, nullable: true),
                    status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    inventory_sync_status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    notes = table.Column<string>(type: "TEXT", nullable: true),
                    version = table.Column<int>(type: "INTEGER", nullable: false),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_goods_receipts", x => x.id);
                    table.ForeignKey(
                        name: "FK_goods_receipts_purchase_orders_purchase_order_id",
                        column: x => x.purchase_order_id,
                        principalTable: "purchase_orders",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "purchase_order_items",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    purchase_order_id = table.Column<long>(type: "INTEGER", nullable: false),
                    product_id = table.Column<long>(type: "INTEGER", nullable: false),
                    sku = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    product_name = table.Column<string>(type: "TEXT", maxLength: 255, nullable: false),
                    unit = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false, defaultValue: "Cái"),
                    unit_cost = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    quantity_ordered = table.Column<int>(type: "INTEGER", nullable: false),
                    quantity_received = table.Column<int>(type: "INTEGER", nullable: false),
                    quantity_invoiced = table.Column<int>(type: "INTEGER", nullable: false),
                    tax_rate = table.Column<decimal>(type: "TEXT", precision: 5, scale: 2, nullable: false),
                    discount_percent = table.Column<decimal>(type: "TEXT", precision: 5, scale: 2, nullable: false),
                    subtotal = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    notes = table.Column<string>(type: "TEXT", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_order_items", x => x.id);
                    table.ForeignKey(
                        name: "FK_purchase_order_items_purchase_orders_purchase_order_id",
                        column: x => x.purchase_order_id,
                        principalTable: "purchase_orders",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "purchase_requisition_items",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    purchase_requisition_id = table.Column<long>(type: "INTEGER", nullable: false),
                    product_id = table.Column<long>(type: "INTEGER", nullable: false),
                    sku = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    product_name = table.Column<string>(type: "TEXT", maxLength: 255, nullable: false),
                    unit = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false, defaultValue: "Cái"),
                    quantity_requested = table.Column<int>(type: "INTEGER", nullable: false),
                    quantity_approved = table.Column<int>(type: "INTEGER", nullable: false),
                    quantity_ordered = table.Column<int>(type: "INTEGER", nullable: false),
                    estimated_unit_cost = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: true),
                    notes = table.Column<string>(type: "TEXT", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_requisition_items", x => x.id);
                    table.ForeignKey(
                        name: "FK_purchase_requisition_items_purchase_requisitions_purchase_requisition_id",
                        column: x => x.purchase_requisition_id,
                        principalTable: "purchase_requisitions",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "purchase_invoices",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    code = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                    purchase_order_id = table.Column<long>(type: "INTEGER", nullable: false),
                    goods_receipt_id = table.Column<long>(type: "INTEGER", nullable: true),
                    supplier_id = table.Column<long>(type: "INTEGER", nullable: false),
                    supplier_invoice_number = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    invoice_date = table.Column<DateTime>(type: "TEXT", nullable: false),
                    due_date = table.Column<DateTime>(type: "TEXT", nullable: true),
                    subtotal_amount = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    tax_amount = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    grand_total = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    matching_status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    notes = table.Column<string>(type: "TEXT", nullable: true),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_invoices", x => x.id);
                    table.ForeignKey(
                        name: "FK_purchase_invoices_goods_receipts_goods_receipt_id",
                        column: x => x.goods_receipt_id,
                        principalTable: "goods_receipts",
                        principalColumn: "id");
                    table.ForeignKey(
                        name: "FK_purchase_invoices_purchase_orders_purchase_order_id",
                        column: x => x.purchase_order_id,
                        principalTable: "purchase_orders",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "goods_receipt_items",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    goods_receipt_id = table.Column<long>(type: "INTEGER", nullable: false),
                    purchase_order_item_id = table.Column<long>(type: "INTEGER", nullable: false),
                    product_id = table.Column<long>(type: "INTEGER", nullable: false),
                    sku = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    product_name = table.Column<string>(type: "TEXT", maxLength: 255, nullable: false),
                    unit = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false, defaultValue: "Cái"),
                    quantity_ordered = table.Column<int>(type: "INTEGER", nullable: false),
                    quantity_received = table.Column<int>(type: "INTEGER", nullable: false),
                    quantity_rejected = table.Column<int>(type: "INTEGER", nullable: false),
                    unit_cost = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    batch_number = table.Column<string>(type: "TEXT", maxLength: 100, nullable: true),
                    expiry_date = table.Column<DateTime>(type: "TEXT", nullable: true),
                    notes = table.Column<string>(type: "TEXT", nullable: true)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_goods_receipt_items", x => x.id);
                    table.ForeignKey(
                        name: "FK_goods_receipt_items_goods_receipts_goods_receipt_id",
                        column: x => x.goods_receipt_id,
                        principalTable: "goods_receipts",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                    table.ForeignKey(
                        name: "FK_goods_receipt_items_purchase_order_items_purchase_order_item_id",
                        column: x => x.purchase_order_item_id,
                        principalTable: "purchase_order_items",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "purchase_order_pr_allocations",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    purchase_order_id = table.Column<long>(type: "INTEGER", nullable: false),
                    purchase_order_item_id = table.Column<long>(type: "INTEGER", nullable: false),
                    purchase_requisition_id = table.Column<long>(type: "INTEGER", nullable: false),
                    purchase_requisition_item_id = table.Column<long>(type: "INTEGER", nullable: false),
                    allocated_quantity = table.Column<int>(type: "INTEGER", nullable: false),
                    status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false),
                    created_at = table.Column<DateTime>(type: "TEXT", nullable: false),
                    updated_at = table.Column<DateTime>(type: "TEXT", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_order_pr_allocations", x => x.id);
                    table.ForeignKey(
                        name: "FK_purchase_order_pr_allocations_purchase_order_items_purchase_order_item_id",
                        column: x => x.purchase_order_item_id,
                        principalTable: "purchase_order_items",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                    table.ForeignKey(
                        name: "FK_purchase_order_pr_allocations_purchase_orders_purchase_order_id",
                        column: x => x.purchase_order_id,
                        principalTable: "purchase_orders",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                    table.ForeignKey(
                        name: "FK_purchase_order_pr_allocations_purchase_requisition_items_purchase_requisition_item_id",
                        column: x => x.purchase_requisition_item_id,
                        principalTable: "purchase_requisition_items",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                    table.ForeignKey(
                        name: "FK_purchase_order_pr_allocations_purchase_requisitions_purchase_requisition_id",
                        column: x => x.purchase_requisition_id,
                        principalTable: "purchase_requisitions",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "purchase_invoice_items",
                columns: table => new
                {
                    id = table.Column<long>(type: "INTEGER", nullable: false)
                        .Annotation("Sqlite:Autoincrement", true),
                    purchase_invoice_id = table.Column<long>(type: "INTEGER", nullable: false),
                    purchase_order_item_id = table.Column<long>(type: "INTEGER", nullable: false),
                    goods_receipt_item_id = table.Column<long>(type: "INTEGER", nullable: true),
                    product_id = table.Column<long>(type: "INTEGER", nullable: false),
                    sku = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                    product_name = table.Column<string>(type: "TEXT", maxLength: 255, nullable: false),
                    unit = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false, defaultValue: "Cái"),
                    quantity_invoiced = table.Column<int>(type: "INTEGER", nullable: false),
                    unit_cost = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    tax_rate = table.Column<decimal>(type: "TEXT", precision: 5, scale: 2, nullable: false),
                    subtotal = table.Column<decimal>(type: "TEXT", precision: 15, scale: 2, nullable: false),
                    matching_status = table.Column<string>(type: "TEXT", maxLength: 30, nullable: false, defaultValue: "MATCHED")
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_purchase_invoice_items", x => x.id);
                    table.ForeignKey(
                        name: "FK_purchase_invoice_items_goods_receipt_items_goods_receipt_item_id",
                        column: x => x.goods_receipt_item_id,
                        principalTable: "goods_receipt_items",
                        principalColumn: "id");
                    table.ForeignKey(
                        name: "FK_purchase_invoice_items_purchase_invoices_purchase_invoice_id",
                        column: x => x.purchase_invoice_id,
                        principalTable: "purchase_invoices",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                    table.ForeignKey(
                        name: "FK_purchase_invoice_items_purchase_order_items_purchase_order_item_id",
                        column: x => x.purchase_order_item_id,
                        principalTable: "purchase_order_items",
                        principalColumn: "id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_items_goods_receipt_id",
                table: "goods_receipt_items",
                column: "goods_receipt_id");

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_items_product_id",
                table: "goods_receipt_items",
                column: "product_id");

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipt_items_purchase_order_item_id",
                table: "goods_receipt_items",
                column: "purchase_order_item_id");

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_code",
                table: "goods_receipts",
                column: "code",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_purchase_order_id",
                table: "goods_receipts",
                column: "purchase_order_id");

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_received_at",
                table: "goods_receipts",
                column: "received_at");

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_status",
                table: "goods_receipts",
                column: "status");

            migrationBuilder.CreateIndex(
                name: "IX_goods_receipts_warehouse_id",
                table: "goods_receipts",
                column: "warehouse_id");

            migrationBuilder.CreateIndex(
                name: "IX_outbox_events_event_id",
                table: "outbox_events",
                column: "event_id",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_outbox_events_status_created_at",
                table: "outbox_events",
                columns: new[] { "status", "created_at" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoice_items_goods_receipt_item_id",
                table: "purchase_invoice_items",
                column: "goods_receipt_item_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoice_items_product_id",
                table: "purchase_invoice_items",
                column: "product_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoice_items_purchase_invoice_id",
                table: "purchase_invoice_items",
                column: "purchase_invoice_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoice_items_purchase_order_item_id",
                table: "purchase_invoice_items",
                column: "purchase_order_item_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoices_code",
                table: "purchase_invoices",
                column: "code",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoices_goods_receipt_id",
                table: "purchase_invoices",
                column: "goods_receipt_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoices_purchase_order_id",
                table: "purchase_invoices",
                column: "purchase_order_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoices_status",
                table: "purchase_invoices",
                column: "status");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoices_supplier_id",
                table: "purchase_invoices",
                column: "supplier_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_invoices_supplier_invoice_number",
                table: "purchase_invoices",
                column: "supplier_invoice_number");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_items_product_id",
                table: "purchase_order_items",
                column: "product_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_items_purchase_order_id",
                table: "purchase_order_items",
                column: "purchase_order_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_pr_allocations_purchase_order_id",
                table: "purchase_order_pr_allocations",
                column: "purchase_order_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_pr_allocations_purchase_order_item_id",
                table: "purchase_order_pr_allocations",
                column: "purchase_order_item_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_pr_allocations_purchase_requisition_id",
                table: "purchase_order_pr_allocations",
                column: "purchase_requisition_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_order_pr_allocations_purchase_requisition_item_id_status",
                table: "purchase_order_pr_allocations",
                columns: new[] { "purchase_requisition_item_id", "status" });

            migrationBuilder.CreateIndex(
                name: "IX_purchase_orders_code",
                table: "purchase_orders",
                column: "code",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_orders_order_date",
                table: "purchase_orders",
                column: "order_date");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_orders_status",
                table: "purchase_orders",
                column: "status");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_orders_supplier_id",
                table: "purchase_orders",
                column: "supplier_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_orders_warehouse_id",
                table: "purchase_orders",
                column: "warehouse_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_requisition_items_product_id",
                table: "purchase_requisition_items",
                column: "product_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_requisition_items_purchase_requisition_id",
                table: "purchase_requisition_items",
                column: "purchase_requisition_id");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_requisitions_approval_status",
                table: "purchase_requisitions",
                column: "approval_status");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_requisitions_code",
                table: "purchase_requisitions",
                column: "code",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_purchase_requisitions_expected_needed_date",
                table: "purchase_requisitions",
                column: "expected_needed_date");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_requisitions_order_status",
                table: "purchase_requisitions",
                column: "order_status");

            migrationBuilder.CreateIndex(
                name: "IX_purchase_requisitions_warehouse_id",
                table: "purchase_requisitions",
                column: "warehouse_id");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "outbox_events");

            migrationBuilder.DropTable(
                name: "processed_events");

            migrationBuilder.DropTable(
                name: "purchase_invoice_items");

            migrationBuilder.DropTable(
                name: "purchase_order_pr_allocations");

            migrationBuilder.DropTable(
                name: "goods_receipt_items");

            migrationBuilder.DropTable(
                name: "purchase_invoices");

            migrationBuilder.DropTable(
                name: "purchase_requisition_items");

            migrationBuilder.DropTable(
                name: "purchase_order_items");

            migrationBuilder.DropTable(
                name: "goods_receipts");

            migrationBuilder.DropTable(
                name: "purchase_requisitions");

            migrationBuilder.DropTable(
                name: "purchase_orders");
        }
    }
}
