<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licenses')) {
            return;
        }

        Schema::create('licenses', function (Blueprint $table): void {
            $table->id();
            $table->string('license_key')->unique();
            $table->string('product_type', 50);
            $table->string('product_slug');
            $table->string('domain')->nullable();
            $table->string('status', 50)->default('inactive');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['product_type', 'product_slug']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
