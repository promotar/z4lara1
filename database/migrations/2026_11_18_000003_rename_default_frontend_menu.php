<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The default frontend menu was seeded with an English name. Rename it only
 * when it still carries the untouched default (never overwrite a custom name).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $name = trans('core.menus.default_frontend_name', [], 'ar') ?: 'قائمة الواجهة';
        $description = trans('core.menus.default_frontend_description', [], 'ar') ?: 'قائمة تنقل قابلة للتعديل للواجهة.';

        DB::table('menus')
            ->where('key', 'platform.frontend')
            ->where('name', 'Frontend Menu')
            ->update(['name' => $name, 'description' => $description]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->where('key', 'platform.frontend')
            ->where('name', trans('core.menus.default_frontend_name', [], 'ar'))
            ->update(['name' => 'Frontend Menu', 'description' => 'Editable frontend navigation menu.']);
    }
};