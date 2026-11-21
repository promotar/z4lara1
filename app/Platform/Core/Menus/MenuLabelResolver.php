<?php

namespace App\Platform\Core\Menus;

use App\Platform\Core\Models\Plugin;

/**
 * Reads the per-plugin `lang/menus.json` file and returns the localized labels
 * used when a plugin menu or menu item is created for the first time.
 *
 * File shape:
 * {
 *   "menus": { "lms.admin": { "ar": "..." } },
 *   "items": { "admin.plugins.lms.courses": { "ar": "..." } }
 * }
 */
class MenuLabelResolver
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array<string, string>
     */
    public function forMenu(?Plugin $plugin, string $key): array
    {
        return $this->lookup($plugin, 'menus', $key);
    }

    /**
     * @return array<string, string>
     */
    public function forItem(?Plugin $plugin, ?string $route, ?string $url, string $title): array
    {
        $title = trim($title);
        $candidates = [];

        if ($route !== null && trim($route) !== '' && $title !== '') {
            $candidates[] = $route.'|'.$title;
        }

        if ($url !== null && trim($url) !== '' && $title !== '') {
            $candidates[] = $url.'|'.$title;
        }

        if ($title !== '') {
            $candidates[] = $title;
        }

        if ($route !== null && trim($route) !== '') {
            $candidates[] = $route;
        }

        if ($url !== null && trim($url) !== '') {
            $candidates[] = $url;
        }

        foreach (array_unique($candidates) as $candidate) {
            $labels = $this->lookup($plugin, 'items', $candidate);

            if ($labels !== []) {
                return $labels;
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function lookup(?Plugin $plugin, string $section, string $key): array
    {
        if ($plugin === null || trim($key) === '') {
            return [];
        }

        $data = $this->file($plugin->slug);
        $labels = $data[$section][$key] ?? null;

        if (! is_array($labels)) {
            return [];
        }

        $result = [];

        foreach ($labels as $locale => $label) {
            $locale = str_replace('_', '-', trim((string) $locale));
            $label = trim((string) $label);

            if ($locale !== '' && $label !== '') {
                $result[$locale] = $label;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function file(string $slug): array
    {
        if (array_key_exists($slug, self::$cache)) {
            return self::$cache[$slug];
        }

        $path = base_path('modules'.DIRECTORY_SEPARATOR.$slug.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'menus.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

        return self::$cache[$slug] = is_array($data) ? $data : [];
    }
}
