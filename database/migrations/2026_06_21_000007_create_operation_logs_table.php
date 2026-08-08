<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_logs')) {
            return;
        }

        Schema::create('operation_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_type');
            $table->string('target_type')->nullable();
            $table->string('target_slug')->nullable();
            $table->string('status', 50);
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['operation_type', 'status']);
            $table->index(['target_type', 'target_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
