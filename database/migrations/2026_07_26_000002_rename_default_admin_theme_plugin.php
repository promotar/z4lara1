<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_SLUG = 'art-inpa-admin-pro-theme';

    private const NEW_SLUG = 'admin-theme';

    public function up(): void
    {
        if (Schema::hasTable('plugins')) {
            $existingNew = DB::table('plugins')->where('slug', self::NEW_SLUG)->first();
            $existingOld = DB::table('plugins')->where('slug', self::OLD_SLUG)->first();

            if ($existingOld && ! $existingNew) {
                DB::table('plugins')
                    ->where('slug', self::OLD_SLUG)
                    ->update([
                        'name' => 'Admin Theme',
                        'slug' => self::NEW_SLUG,
                        'path' => base_path('modules/'.self::NEW_SLUG),
                        'manifest' => $this->renamedManifest($existingOld->manifest ?? null),
                        'updated_at' => now(),
                    ]);
            } elseif ($existingOld && $existingNew) {
                DB::table('plugins')->where('slug', self::OLD_SLUG)->delete();
            }
        }

        $this->renamePluginSlugReferences();
    }

    public function down(): void
    {
        //
    }

    private function renamePluginSlugReferences(): void
    {
        foreach ([
            'platform_plugin_registry_entries' => 'plugin_slug',
            'plugin_updates' => 'plugin_slug',
            'operation_logs' => 'target_slug',
            'backup_checkpoints' => 'target_slug',
            'licenses' => 'product_slug',
        ] as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if ($this->requiresUniqueSlug($table) && DB::table($table)->where($column, self::NEW_SLUG)->exists()) {
                DB::table($table)->where($column, self::OLD_SLUG)->delete();

                continue;
            }

            DB::table($table)->where($column, self::OLD_SLUG)->update([$column => self::NEW_SLUG]);
        }
    }

    private function requiresUniqueSlug(string $table): bool
    {
        return in_array($table, ['platform_plugin_registry_entries', 'plugin_updates', 'plugins'], true);
    }

    private function renamedManifest(mixed $value): string
    {
        $manifest = is_string($value) ? json_decode($value, true) : [];
        $manifest = is_array($manifest) ? $manifest : [];
        $manifest['name'] = 'Admin Theme';
        $manifest['slug'] = self::NEW_SLUG;
        $manifest['type'] = 'theme';
        $manifest['theme'] = [
            'scope' => 'admin',
            'default' => true,
        ];

        return json_encode($manifest, JSON_UNESCAPED_SLASHES);
    }
};
