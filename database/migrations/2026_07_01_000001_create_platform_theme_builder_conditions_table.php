<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_theme_builder_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('platform_pages')->cascadeOnDelete();
            $table->string('operator')->default('include');
            $table->string('scope')->default('entire_site');
            $table->string('target_value')->nullable();
            $table->timestamps();

            $table->unique('page_id');
            $table->index(['operator', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_theme_builder_conditions');
    }
};
