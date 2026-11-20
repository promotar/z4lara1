<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_audit_issues')) {
            return;
        }

        Schema::table('seo_audit_issues', function (Blueprint $table): void {
            if (! Schema::hasColumn('seo_audit_issues', 'content_type')) {
                $table->string('content_type', 80)->nullable()->after('module_slug');
            }

            if (! Schema::hasColumn('seo_audit_issues', 'content_id')) {
                $table->unsignedBigInteger('content_id')->nullable()->after('content_type');
            }

            if (! Schema::hasColumn('seo_audit_issues', 'admin_url')) {
                $table->string('admin_url', 2048)->nullable()->after('content_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('seo_audit_issues')) {
            return;
        }

        Schema::table('seo_audit_issues', function (Blueprint $table): void {
            foreach (['admin_url', 'content_id', 'content_type'] as $column) {
                if (Schema::hasColumn('seo_audit_issues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
