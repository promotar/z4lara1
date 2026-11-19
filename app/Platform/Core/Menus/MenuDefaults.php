<?php

namespace App\Platform\Core\Menus;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Models\Menu;
use App\Platform\Core\Models\MenuItem;
use Throwable;

/**
 * Core menu defaults.
 *
 * These helpers are idempotent: they only add the missing items, so they are
 * safe to call from the installer, from an upgrade migration, or at runtime.
 */
final class MenuDefaults
{
    /**
     * The frontend menu that public layouts render in the header.
     *
     * Prefers the conventional `platform.header-menu` (used by the theme),
     * then the platform default menu, then the first active frontend menu.
     */
    public static function defaultLanguageMenu(): ?Menu
    {
        try {
            foreach (['platform.header-menu', 'platform.frontend'] as $key) {
                $menu = Menu::query()
                    ->where('key', $key)
                    ->where('location', 'frontend')
                    ->where('is_active', true)
                    ->first();

                if ($menu !== null) {
                    return $menu;
                }
            }

            return Menu::query()
                ->where('location', 'frontend')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ensure the given frontend menu exposes a language switcher.
     *
     * The public header renders top-level items only, so every available
     * language gets its own link that routes through `locale.switch`
     * (GET /locale/{locale}).
     *
     * @return int number of items created
     */
    public static function ensureLanguageItems(Menu $menu): int
    {
        if ($menu->location !== 'frontend') {
            return 0;
        }

        // Once an administrator removes the default language links, do not
        // recreate them on every settings page load.
        try {
            if (\Illuminate\Support\Facades\DB::table('platform_registry_entries')
                ->where('registry_type', 'menus')
                ->where('registry_key', 'language_switcher_removed')
                ->exists()) {
                return 0;
            }
        } catch (Throwable) {
            // Registry table not ready yet.
        }

        try {
            $already = MenuItem::query()
                ->where('menu_id', $menu->id)
                ->where('route_name', 'locale.switch')
                ->exists();

            if ($already) {
                return 0;
            }

            $locale = app(PlatformLocale::class);
            $languages = $locale->languages();

            if (count($languages) < 2) {
                return 0;
            }

            $sort = (int) MenuItem::query()->where('menu_id', $menu->id)->max('sort_order');

            $created = 0;

            foreach ($languages as $code => $label) {
                MenuItem::query()->create([
                    'menu_id' => $menu->id,
                    'parent_id' => null,
                    'plugin_id' => null,
                    'title' => (string) $label,
                    'label' => (string) $code,
                    'type' => 'route',
                    'url' => null,
                    'route_name' => 'locale.switch',
                    'route_params' => ['locale' => (string) $code],
                    'icon' => 'globe',
                    'target' => null,
                    'permission' => null,
                    'metadata' => ['system' => 'language'],
                    'is_active' => true,
                    'sort_order' => ++$sort,
                ]);

                $created++;
            }

            return $created;
        } catch (Throwable) {
            // Tables or service might not be ready (fresh install / partial upgrade).
            return 0;
        }
    }

    /**
     * Ensure the language switcher exists on the header menu (core default).
     */
    public static function ensureDefaultLanguageMenu(): int
    {
        $menu = self::defaultLanguageMenu();

        return $menu !== null ? self::ensureLanguageItems($menu) : 0;
    }
}
