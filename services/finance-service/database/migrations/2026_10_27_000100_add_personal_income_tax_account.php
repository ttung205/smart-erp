<?php

use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Thêm tài khoản 3335 (Thuế thu nhập cá nhân) để hạch toán thuế khấu trừ từ lương. Không đổi dữ liệu hiện có.
     */
    public function up(): void
    {
        ChartOfAccount::firstOrCreate(['code' => '3335'], [
            'name' => 'Thuế thu nhập cá nhân', 'parent_id' => null, 'category' => 'LIABILITY', 'normal_balance' => 'CREDIT',
            'is_postable' => true, 'is_system' => true, 'is_custom' => false, 'tracks_customer' => false,
            'tracks_supplier' => false, 'is_active' => true, 'regime' => 'TT99_2025',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ChartOfAccount::where('code', '3335')->whereDoesntHave('children')->delete();
    }
};
