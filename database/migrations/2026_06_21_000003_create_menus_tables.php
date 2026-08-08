<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            Schema::create('menus', function (Blueprint $table): void {
                $table->id();
                $table->string('key');
                $table->string('name');
                $table->string('location')->index();
                $table->text('description')->nullable();
                $table->string('source')->nullable()->index();
                $table->foreignId('plugin_id')->nullable()->constrained('plugins')->nullOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0)->index();
                $table->timestamps();

                $table->unique(['key', 'location']);
            });
        }

        if (! Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
                $table->foreignId('plugin_id')->nullable()->constrained('plugins')->nullOnDelete();
                $table->string('title');
                $table->string('label')->nullable();
                $table->string('type')->default('link')->index();
                $table->string('url')->nullable();
                $table->string('route_name')->nullable();
                $table->json('route_params')->nullable();
                $table->string('icon')->nullable();
                $table->string('target')->nullable();
                $table->string('permission')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
