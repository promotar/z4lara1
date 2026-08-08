<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_theme_builder_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_type');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('source_type')->default('blank');
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            $table->json('page_builder_json')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['template_type', 'status']);
        });

        Schema::create('platform_theme_builder_template_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('platform_theme_builder_templates')->cascadeOnDelete();
            $table->string('operator')->default('include');
            $table->string('scope')->default('entire_site');
            $table->string('target_value')->nullable();
            $table->timestamps();

            $table->unique('template_id');
            $table->index(['operator', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_theme_builder_template_conditions');
        Schema::dropIfExists('platform_theme_builder_templates');
    }
};
