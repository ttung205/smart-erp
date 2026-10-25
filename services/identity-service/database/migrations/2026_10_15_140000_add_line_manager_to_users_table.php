<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'line_manager_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Quản lý trực tiếp: người duyệt đơn E-Office của nhân viên (ưu tiên hơn trưởng phòng)
                $table->foreignId('line_manager_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            });
        }

        // Dữ liệu sẵn có: nhân viên → trưởng phòng của mình; trưởng phòng → Super Admin
        $adminId = DB::table('users')->where('email', 'admin@erp.local')->value('id');
        $managerIds = DB::table('departments')->whereNotNull('manager_id')->pluck('manager_id', 'id');
        foreach (DB::table('users')->get(['id', 'department_id']) as $user) {
            $lineManagerId = $managerIds->contains($user->id) ? $adminId : ($managerIds[$user->department_id] ?? null);
            if ($lineManagerId && $lineManagerId !== $user->id) {
                DB::table('users')->where('id', $user->id)->update(['line_manager_id' => $lineManagerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('line_manager_id');
        });
    }
};
