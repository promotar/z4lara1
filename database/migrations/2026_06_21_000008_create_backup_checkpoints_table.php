<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backup_checkpoints')) {
            return;
        }

        Schema::create('backup_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_type');
            $table->string('target_type')->nullable();
            $table->string('target_slug')->nullable();
            $table->string('checkpoint_type');
            $table->string('status', 50);
            $table->string('path')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['operation_type', 'status']);
            $table->index(['target_type', 'target_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_checkpoints');
    }
};
