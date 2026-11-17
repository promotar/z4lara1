<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_media_metadata')) {
            return;
        }

        Schema::create('platform_media_metadata', function (Blueprint $table): void {
            $table->id();
            $table->string('url')->unique();
            $table->string('alt_text')->default('');
            $table->string('title')->default('');
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_media_metadata');
    }
};
