<?php

namespace Tests\Feature;

use App\Models\CustomerReceivable;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RevenueReportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReceivableTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuing_vat_invoice_creates_customer_receivable(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-AR-001',
            'invoice_date' => '2026-10-13',
            'payment_terms' => 'Công nợ 15 ngày',
            'created_by' => 'Test User',
        ]);
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => 100,
            'sku' => 'SKU-AR-100',
            'product_name' => 'Sản phẩm công nợ',
            'quantity' => 2,
            'unit_price' => 100,
        ]);
        $reportItem = RevenueReportItem::create([
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $item->id,
            'account_code' => '1312',
            'quantity' => 2,
            'remaining_invoice_quantity' => 2,
            'unit_price' => 100,
            'vat_rate' => 10,
        ]);

        $response = $this->withHeaders($this->jwtHeaders(['invoice.manage']))->postJson('/api/vat-invoices', [
            'invoice_date' => '2026-10-13',
            'sales_invoice_id' => $invoice->id,
            'items' => [[
                'revenue_report_item_id' => $reportItem->id,
                'sales_invoice_item_id' => $item->id,
                'quantity' => 2,
            ]],
        ]);

        $response->assertCreated()->assertJsonPath('data.receivable.outstanding_amount', 220);
        $this->assertDatabaseHas('customer_receivables', [
            'invoice_date' => '2026-10-13',
            'due_date' => '2026-10-28',
            'original_amount' => 220,
            'paid_amount' => 0,
            'outstanding_amount' => 220,
            'status' => 'OPEN',
        ]);
    }

    public function test_finance_user_can_track_receivables_and_overdue_summary(): void
    {
        CustomerReceivable::factory()->create([
            'customer_name' => 'Công ty Quá Hạn',
            'invoice_date' => today()->subDays(40),
            'due_date' => today()->subDays(10),
            'original_amount' => 1_100_000,
            'paid_amount' => 0,
            'outstanding_amount' => 1_100_000,
        ]);

        $response = $this->withHeaders($this->jwtHeaders(['finance.view']))
            ->getJson('/api/customer-receivables');

        $response->assertOk()
            ->assertJsonPath('summary.total_receivable', 1_100_000)
            ->assertJsonPath('summary.total_paid', 0)
            ->assertJsonPath('summary.total_outstanding', 1_100_000)
            ->assertJsonPath('summary.overdue_outstanding', 1_100_000)
            ->assertJsonPath('data.0.effective_status', 'OVERDUE');
    }
}
