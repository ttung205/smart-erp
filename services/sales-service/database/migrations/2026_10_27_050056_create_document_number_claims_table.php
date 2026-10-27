<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Số chứng từ đã cấp, ghi ngoài transaction nghiệp vụ: lập chứng từ thất bại (rollback) vẫn giữ số,
     * nên số không bao giờ bị cấp lại cho chứng từ khác (Inventory nhận diện việc giữ hàng theo số chứng từ).
     */
    public function up(): void
    {
        Schema::create('document_number_claims', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 30);
            $table->string('number', 60)->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_claims');
    }
};
