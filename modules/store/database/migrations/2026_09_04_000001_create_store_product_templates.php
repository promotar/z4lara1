<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_product_templates')) {
            Schema::create('store_product_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('category', 80)->default('collection');
                $table->string('status', 20)->default('draft')->index();
                $table->longText('html_code')->nullable();
                $table->longText('css_code')->nullable();
                $table->longText('js_code')->nullable();
                $table->string('preview_image')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->boolean('is_system')->default(false)->index();
                $table->string('system_key', 80)->nullable()->unique();
                $table->timestamps();

                $table->index(['category', 'status'], 'store_tpl_category_status_idx');
            });
        }

        $now = now();
        DB::table('store_product_templates')->updateOrInsert(
            ['system_key' => 'products'],
            [
                'name' => 'Default Product Collection',
                'slug' => 'default-products',
                'category' => 'collection',
                'status' => 'active',
                'html_code' => $this->resource('products.html'),
                'css_code' => $this->resource('products.css'),
                'js_code' => $this->resource('products.js'),
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_templates');
    }

    private function resource(string $file): string
    {
        $path = dirname(__DIR__, 2).'/resources/default-templates/'.$file;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
};
