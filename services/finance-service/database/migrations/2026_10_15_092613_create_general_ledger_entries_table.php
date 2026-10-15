<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('general_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 120)->unique();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('document_number', 50);
            $table->date('entry_date')->index();
            $table->foreignId('debit_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('credit_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('VND');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });

        DB::table('finance_voucher_lines as line')
            ->join('finance_vouchers as voucher', 'voucher.id', '=', 'line.voucher_id')
            ->where('voucher.status', 'POSTED')
            ->select('line.*', 'voucher.voucher_number', 'voucher.voucher_date', 'voucher.accounting_date',
                'voucher.currency', 'voucher.party_type', 'voucher.party_id', 'voucher.description as voucher_description')
            ->orderBy('line.id')
            ->chunk(500, function ($lines): void {
                foreach ($lines as $line) {
                    DB::table('general_ledger_entries')->insertOrIgnore([
                        'source_key' => 'VOUCHER_LINE:'.$line->id,
                        'source_type' => 'VOUCHER', 'source_id' => $line->voucher_id,
                        'document_number' => $line->voucher_number,
                        'entry_date' => $line->accounting_date ?: $line->voucher_date,
                        'debit_account_id' => $line->debit_account_id,
                        'credit_account_id' => $line->credit_account_id,
                        'amount' => $line->amount, 'currency' => $line->currency,
                        'customer_id' => $line->party_type === 'CUSTOMER' ? $line->party_id : null,
                        'description' => $line->description ?: $line->voucher_description,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_ledger_entries');
    }
};
