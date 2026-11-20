<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_sitemap_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('urls_count')->default(0);
            $table->unsignedInteger('chunks_count')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status'], 'seo_sitemap_runs_site_status_index');
        });

        Schema::create('seo_sitemap_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('seo_sitemap_runs')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('urls_count')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->longText('content');
            $table->timestamps();

            $table->unique(['run_id', 'chunk_index'], 'seo_sitemap_chunks_run_index_unique');
            $table->index(['site_id', 'run_id'], 'seo_sitemap_chunks_site_run_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_sitemap_chunks');
        Schema::dropIfExists('seo_sitemap_runs');
    }
};
