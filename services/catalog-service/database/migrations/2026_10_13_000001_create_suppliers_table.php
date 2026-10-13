<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('tax_code', 50)->nullable();
            $table->string('supplier_group', 100)->nullable()->default('Vật tư & Thiết bị');
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_holder', 150)->nullable();
            $table->string('bank_branch', 150)->nullable();
            $table->string('payment_terms', 100)->nullable()->default('Net 30');
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, INACTIVE
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('name');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
