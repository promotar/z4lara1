<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_audit_runs')) {
            return;
        }

        Schema::create('seo_audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('status', 20)->default('completed')->index();
            $table->unsignedInteger('urls')->default(0);
            $table->unsignedInteger('records')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audit_runs');
    }
};
