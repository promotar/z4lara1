<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_schema_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('name');
            $table->string('key', 120);
            $table->string('type', 80)->nullable();
            $table->string('module_slug', 120)->nullable();
            $table->string('route_pattern', 255)->nullable();
            $table->longText('schema_json');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'key'], 'seo_schema_templates_site_key_unique');
            $table->index(['site_id', 'is_active', 'priority'], 'seo_schema_templates_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_schema_templates');
    }
};
