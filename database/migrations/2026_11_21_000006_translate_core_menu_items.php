<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill Arabic/English labels for the core menu items that ship without
     * translations. Existing administrator values are never overwritten.
     */
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        $labels = [
            'dashboard' => ['en' => 'Dashboard', 'ar' => 'لوحة التحكم'],
            'admin.users.index' => ['en' => 'Users', 'ar' => 'المستخدمون'],
            'admin.roles.index' => ['en' => 'Roles', 'ar' => 'الأدوار'],
            'admin.permissions.index' => ['en' => 'Permissions', 'ar' => 'الصلاحيات'],
            'front.account' => ['en' => 'My Account', 'ar' => 'حسابي'],
        ];

        foreach ($labels as $route => $translations) {
            $items = DB::table('menu_items')->where('route_name', $route)->get(['id', 'title_translations']);

            foreach ($items as $item) {
                $current = json_decode((string) $item->title_translations, true);
                $current = is_array($current) ? $current : [];
                $merged = $current;

                foreach ($translations as $locale => $label) {
                    if (trim((string) ($merged[$locale] ?? '')) === '') {
                        $merged[$locale] = $label;
                    }
                }

                if ($merged !== $current) {
                    DB::table('menu_items')->where('id', $item->id)->update([
                        'title_translations' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Menu translations are content; keep them on rollback.
    }
};
