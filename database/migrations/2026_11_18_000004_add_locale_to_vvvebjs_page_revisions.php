<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vvvebjs_page_revisions')) {
            return;
        }

        if (! Schema::hasColumn('vvvebjs_page_revisions', 'locale')) {
            Schema::table('vvvebjs_page_revisions', function (Blueprint $table): void {
                $table->string('locale', 16)->nullable()->after('page_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('vvvebjs_page_revisions') || ! Schema::hasColumn('vvvebjs_page_revisions', 'locale')) {
            return;
        }

        Schema::table('vvvebjs_page_revisions', function (Blueprint $table): void {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });
    }
};
