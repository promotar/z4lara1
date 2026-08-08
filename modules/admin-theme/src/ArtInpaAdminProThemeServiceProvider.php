<?php

namespace Modules\ArtInpaAdminProTheme;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ArtInpaAdminProThemeServiceProvider extends ServiceProvider
{
    private const CURRENT_SLUG = 'admin-theme';

    private const LEGACY_SLUG = 'art-inpa-admin-pro-theme';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->removeLegacyPluginRecord();
        View::addNamespace('admin-theme', dirname(__DIR__).'/resources/views');

        View::composer(['layouts.app', 'components.page-builder-focus-layout'], function (): void {
            $css = $this->stylesheet();

            if ($css === null) {
                return;
            }

            view()->startPush('styles');
            echo '<style data-plugin-admin-style="admin-theme">'.PHP_EOL.$css.PHP_EOL.'</style>';
            echo '<style data-plugin-admin-settings="admin-theme">'.PHP_EOL
                .$this->app->make(ThemeSettings::class)->css()
                .PHP_EOL.'</style>';
            view()->stopPush();
        });
    }

    private function removeLegacyPluginRecord(): void
    {
        try {
            if (! Schema::hasTable('plugins')) {
                return;
            }

            $currentPluginId = DB::table('plugins')
                ->where('slug', self::CURRENT_SLUG)
                ->value('id');
            $legacyPluginId = DB::table('plugins')
                ->where('slug', self::LEGACY_SLUG)
                ->value('id');

            if (! $currentPluginId || ! $legacyPluginId || $currentPluginId === $legacyPluginId) {
                return;
            }

            DB::transaction(function () use ($legacyPluginId): void {
                if (Schema::hasTable('plugin_updates')) {
                    DB::table('plugin_updates')->where('plugin_id', $legacyPluginId)->delete();
                }

                foreach (['menu_items', 'menus'] as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'plugin_id')) {
                        DB::table($table)->where('plugin_id', $legacyPluginId)->delete();
                    }
                }

                if (Schema::hasTable('platform_plugin_registry_entries')) {
                    DB::table('platform_plugin_registry_entries')
                        ->where('plugin_slug', self::LEGACY_SLUG)
                        ->delete();
                }

                DB::table('plugins')->where('id', $legacyPluginId)->delete();
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function stylesheet(): ?string
    {
        $path = __DIR__.'/../resources/css/admin-theme.css';

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return (string) file_get_contents($path);
    }
}
