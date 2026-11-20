<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_artwork_details') && Schema::hasTable('store_artwork_reviews')) {
            return;
        }

        Schema::create('store_artwork_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('store_products')->cascadeOnDelete();
            $table->unsignedBigInteger('owner_user_id')->index();
            $table->string('workflow_state', 32)->default('draft')->index();
            $table->unsignedSmallInteger('completion_year')->nullable();
            $table->string('work_type', 80)->nullable();
            $table->string('collection_name', 160)->nullable();
            $table->string('edition_type', 24)->default('original');
            $table->unsignedInteger('edition_number')->nullable();
            $table->unsignedInteger('edition_total')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('artistic_style', 100)->nullable();
            $table->string('subject', 160)->nullable();
            $table->string('technique', 160)->nullable();
            $table->string('material', 160)->nullable();
            $table->string('surface', 120)->nullable();
            $table->json('primary_colors')->nullable();
            $table->string('orientation', 24)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('depth', 10, 2)->nullable();
            $table->string('dimension_unit', 8)->default('cm');
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 8)->default('kg');
            $table->boolean('is_framed')->default(false);
            $table->string('frame_material', 120)->nullable();
            $table->string('condition', 80)->nullable();
            $table->boolean('ready_to_hang')->default(false);
            $table->longText('artwork_story')->nullable();
            $table->longText('inspiration_source')->nullable();
            $table->text('care_instructions')->nullable();
            $table->unsignedBigInteger('requested_price_minor')->nullable();
            $table->char('currency', 3)->default('JOD');
            $table->string('availability', 32)->nullable();
            $table->unsignedSmallInteger('preparation_days')->nullable();
            $table->text('shipping_notes')->nullable();
            $table->boolean('ownership_confirmed')->default(false);
            $table->boolean('sale_rights_confirmed')->default(false);
            $table->text('authenticity_notes')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'workflow_state'], 'store_artwork_owner_state_idx');
        });

        Schema::create('store_artwork_reviews', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('artwork_detail_id')->constrained('store_artwork_details')->cascadeOnDelete();
            $table->unsignedBigInteger('reviewer_user_id');
            $table->string('decision', 32);
            $table->text('notes')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['artwork_detail_id', 'decided_at'], 'store_artwork_review_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_artwork_reviews');
        Schema::dropIfExists('store_artwork_details');
    }
};
