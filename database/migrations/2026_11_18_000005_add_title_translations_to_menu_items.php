<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || Schema::hasColumn('menu_items', 'title_translations')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            // locale => display name. The base `title` column keeps the default
            // language value so lists and fallbacks keep working untouched.
            $table->json('title_translations')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'title_translations')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('title_translations');
        });
    }
};
