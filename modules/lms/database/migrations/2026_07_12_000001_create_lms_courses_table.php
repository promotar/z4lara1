<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_courses')) {
            return;
        }

        Schema::create('lms_courses', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->json('outcomes')->nullable();
            $table->json('requirements')->nullable();
            $table->json('target_audience')->nullable();
            $table->json('faqs')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('publish_date')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('visibility')->default('public');
            $table->string('featured_image_path')->nullable();
            $table->string('featured_image_name')->nullable();
            $table->json('featured_image_meta')->nullable();
            $table->string('promo_video_url')->nullable();
            $table->string('promo_video_path')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('level')->nullable()->index();
            $table->string('language')->nullable()->index();
            $table->string('instructor_name')->nullable();
            $table->string('instructor_title')->nullable();
            $table->string('pricing_type')->default('free');
            $table->decimal('regular_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('enrollment_limit_type')->default('unlimited');
            $table->unsignedInteger('enrollment_limit')->nullable();
            $table->string('enrollment_type')->default('open');
            $table->string('access_duration')->default('lifetime');
            $table->string('course_progress')->default('complete_previous');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_courses');
    }
};
