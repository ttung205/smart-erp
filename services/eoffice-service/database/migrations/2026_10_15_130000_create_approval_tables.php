<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->unsignedSmallInteger('step_order')->default(1);
            $table->unsignedBigInteger('approver_id');
            $table->string('approver_name', 150);
            $table->string('status', 20)->default('PENDING');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['approver_id', 'status']);
        });

        Schema::create('approval_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name', 150);
            $table->string('action', 30);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
        Schema::dropIfExists('approval_steps');
    }
};
