<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_page_revisions')) {
            return;
        }

        Schema::create('platform_page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('platform_pages')->cascadeOnDelete();
            $table->string('title');
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            $table->longText('page_builder_json')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_page_revisions');
    }
};
