<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_tool_audit_logs')) {
            return;
        }

        Schema::create('ai_tool_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('tool_name')->index();
            $table->string('intent')->index();
            $table->boolean('allowed')->default(false)->index();
            $table->string('denied_reason')->nullable();
            $table->json('input_summary')->nullable();
            $table->unsignedInteger('result_count')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_audit_logs');
    }
};
