<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_templates')) {
            return;
        }

        Schema::create('blog_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 80)->default('custom');
            $table->string('status', 20)->default('active');
            $table->longText('html_code')->nullable();
            $table->longText('css_code')->nullable();
            $table->foreignId('preview_image_id')->nullable()->constrained('blog_media')->nullOnDelete();
            $table->string('preview_image')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_templates');
    }
};
