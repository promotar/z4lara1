<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        $overviewId = DB::table('menus')
            ->where('location', 'admin')
            ->where('key', 'platform.admin.overview')
            ->value('id');

        if ($overviewId === null) {
            return;
        }

        $existing = DB::table('menu_items')
            ->join('menus', 'menus.id', '=', 'menu_items.menu_id')
            ->where('menus.location', 'admin')
            ->where('menu_items.route_name', 'front.account')
            ->value('menu_items.id');

        $payload = [
            'menu_id' => $overviewId,
            'parent_id' => null,
            'plugin_id' => null,
            'title' => 'My Account',
            'label' => 'My Account',
            'type' => 'route',
            'url' => null,
            'route_name' => 'front.account',
            'route_params' => null,
            'icon' => 'A',
            'target' => '_self',
            'permission' => null,
            'metadata' => json_encode(['admin_slot' => 'account'], JSON_UNESCAPED_SLASHES),
            'is_active' => true,
            'sort_order' => 30,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            // Preserve the administrator's existing label, icon, state, section,
            // and ordering. The route identity itself is the idempotency key.
            return;
        }

        DB::table('menu_items')->insert($payload + ['created_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        DB::table('menu_items')
            ->where('route_name', 'front.account')
            ->where('metadata', json_encode(['admin_slot' => 'account'], JSON_UNESCAPED_SLASHES))
            ->delete();
    }
};
