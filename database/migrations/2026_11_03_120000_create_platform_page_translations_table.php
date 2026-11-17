<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_page_translations')) {
            return;
        }

        Schema::create('platform_page_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->string('locale', 16);
            $table->string('title')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('vvvebjs_html')->nullable();
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'locale']);
            $table->index('locale');
            $table->index('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_page_translations');
    }
};
