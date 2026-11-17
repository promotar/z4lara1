<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group_key');
            $table->string('setting_key');
            $table->string('label');
            $table->string('type', 50);
            $table->json('value')->nullable();
            $table->json('default_value')->nullable();
            $table->json('options')->nullable();
            $table->text('help_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['group_key', 'setting_key']);
            $table->index(['group_key', 'sort_order']);
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
