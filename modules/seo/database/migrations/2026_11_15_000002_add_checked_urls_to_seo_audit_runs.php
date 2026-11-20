<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_audit_runs') || Schema::hasColumn('seo_audit_runs', 'checked_urls')) {
            return;
        }

        Schema::table('seo_audit_runs', function (Blueprint $table): void {
            $table->longText('checked_urls')->nullable()->after('duration_ms');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('seo_audit_runs', 'checked_urls')) {
            Schema::table('seo_audit_runs', function (Blueprint $table): void {
                $table->dropColumn('checked_urls');
            });
        }
    }
};
