<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->decimal('price_index', 12, 4)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('brands', fn (Blueprint $table) => $table->dropColumn('price_index'));
    }
};
