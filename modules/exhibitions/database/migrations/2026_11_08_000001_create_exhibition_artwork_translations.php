<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exhibition_artwork_translations')) {
            return;
        }

        Schema::create('exhibition_artwork_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('artwork_id');
            $table->string('locale', 8);
            $table->string('title', 180)->nullable();
            $table->text('short_description')->nullable();
            $table->longText('detailed_description')->nullable();
            $table->longText('artwork_story')->nullable();
            $table->longText('inspiration_source')->nullable();
            $table->text('care_instructions')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->string('image_alt_text', 180)->nullable();
            $table->json('keywords')->nullable();
            $table->text('shipping_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestampsTz();

            $table->unique(['artwork_id', 'locale'], 'ex_artwork_translation_unique');
            $table->index('locale', 'ex_artwork_translation_locale_idx');
            $table->foreign('artwork_id', 'ex_artwork_translation_artwork_fk')
                ->references('id')
                ->on('exhibition_artworks')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artwork_translations');
    }
};
