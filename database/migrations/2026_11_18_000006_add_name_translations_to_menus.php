<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus') || Schema::hasColumn('menus', 'name_translations')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            // locale => section/menu name. The base `name` keeps the default
            // language value so lists and fallbacks keep working untouched.
            $table->json('name_translations')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasColumn('menus', 'name_translations')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropColumn('name_translations');
        });
    }
};
