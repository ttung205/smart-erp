<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vat_invoice_id')->unique()->constrained('vat_invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name');
            $table->string('customer_tax_code', 50)->nullable();
            $table->string('invoice_number', 50)->index();
            $table->string('invoice_series', 30)->nullable();
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('currency', 10)->default('VND');
            $table->decimal('original_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamps();
        });

        DB::table('vat_invoices')
            ->leftJoin('invoices', 'vat_invoices.sales_invoice_id', '=', 'invoices.id')
            ->where('vat_invoices.status', 'ISSUED')
            ->select('vat_invoices.*', 'invoices.payment_terms')
            ->orderBy('vat_invoices.id')
            ->chunkById(100, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    preg_match('/(\d+)/', (string) $invoice->payment_terms, $matches);
                    $paymentTermDays = isset($matches[1]) ? max((int) $matches[1], 0) : 30;
                    $invoiceDate = Carbon::parse($invoice->invoice_date);

                    DB::table('customer_receivables')->insertOrIgnore([
                        'vat_invoice_id' => $invoice->id,
                        'customer_id' => $invoice->customer_id,
                        'customer_name' => $invoice->customer_name ?: 'Khách hàng',
                        'customer_tax_code' => $invoice->customer_tax_code,
                        'invoice_number' => $invoice->invoice_number,
                        'invoice_series' => $invoice->invoice_series,
                        'invoice_date' => $invoiceDate->toDateString(),
                        'due_date' => $invoiceDate->copy()->addDays($paymentTermDays)->toDateString(),
                        'currency' => $invoice->currency ?: 'VND',
                        'original_amount' => $invoice->total_amount,
                        'paid_amount' => 0,
                        'outstanding_amount' => $invoice->total_amount,
                        'status' => 'OPEN',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'vat_invoices.id', 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_receivables');
    }
};
