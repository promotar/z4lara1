<?php

namespace Modules\ArtInpaAdminProTheme;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ThemeSettings
{
    private const GROUP = 'admin_theme';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'sidebar_width' => ['label' => 'Sidebar width', 'type' => 'number', 'default' => 224, 'min' => 160, 'max' => 360, 'unit' => 'px'],
            'sidebar_background' => ['label' => 'Sidebar background', 'type' => 'color', 'default' => '#650606'],
            'sidebar_text_color' => ['label' => 'Sidebar text color', 'type' => 'color', 'default' => '#ffffff'],
            'active_menu_color' => ['label' => 'Active menu color', 'type' => 'color', 'default' => '#a90806'],
            'primary_color' => ['label' => 'Primary color', 'type' => 'color', 'default' => '#9a0000'],
            'page_background' => ['label' => 'Page background', 'type' => 'color', 'default' => '#f7f5f4'],
            'card_background' => ['label' => 'Card background', 'type' => 'color', 'default' => '#ffffff'],
            'card_padding' => ['label' => 'Card padding', 'type' => 'number', 'default' => 20, 'min' => 0, 'max' => 64, 'unit' => 'px'],
            'card_margin' => ['label' => 'Card margin', 'type' => 'number', 'default' => 0, 'min' => 0, 'max' => 64, 'unit' => 'px'],
            'border_color' => ['label' => 'Border color', 'type' => 'color', 'default' => '#e8e1df'],
            'border_size' => ['label' => 'Border size', 'type' => 'number', 'default' => 1, 'min' => 0, 'max' => 8, 'unit' => 'px'],
            'font_family' => [
                'label' => 'Font family',
                'type' => 'select',
                'default' => 'Inter, Arial, sans-serif',
                'options' => [
                    'Inter, Arial, sans-serif' => 'Inter',
                    'Arial, sans-serif' => 'Arial',
                    'Tahoma, Arial, sans-serif' => 'Tahoma',
                    'Georgia, serif' => 'Georgia',
                    '"Courier New", monospace' => 'Courier New',
                ],
            ],
            'base_font_size' => ['label' => 'Base font size', 'type' => 'number', 'default' => 14, 'min' => 10, 'max' => 22, 'unit' => 'px'],
            'border_radius' => ['label' => 'Border radius', 'type' => 'number', 'default' => 8, 'min' => 0, 'max' => 32, 'unit' => 'px'],
            'content_padding' => ['label' => 'Content padding', 'type' => 'number', 'default' => 24, 'min' => 0, 'max' => 80, 'unit' => 'px'],
            'header_height' => ['label' => 'Header height', 'type' => 'number', 'default' => 56, 'min' => 32, 'max' => 120, 'unit' => 'px'],
            'custom_css' => ['label' => 'Custom CSS editor', 'type' => 'textarea', 'default' => ''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = collect($this->definitions())
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['default']])
            ->all();

        if (! Schema::hasTable('platform_settings')) {
            return $values;
        }

        $stored = DB::table('platform_settings')
            ->where('group_key', self::GROUP)
            ->pluck('value', 'setting_key');

        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $decoded = json_decode((string) $value, true);
            $values[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $now = now();

        foreach ($this->definitions() as $key => $definition) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $payload = [
                'label' => $definition['label'],
                'type' => $definition['type'],
                'value' => json_encode($values[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'default_value' => json_encode($definition['default'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'options' => isset($definition['options'])
                    ? json_encode($definition['options'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null,
                'help_text' => null,
                'sort_order' => array_search($key, array_keys($this->definitions()), true) * 10,
                'is_public' => false,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('platform_settings', 'module')) {
                $payload['module'] = 'admin-theme';
            }

            if (Schema::hasColumn('platform_settings', 'category')) {
                $payload['category'] = 'appearance';
            }

            if (Schema::hasColumn('platform_settings', 'ui_component')) {
                $payload['ui_component'] = $definition['type'];
            }

            if (Schema::hasColumn('platform_settings', 'unit')) {
                $payload['unit'] = $definition['unit'] ?? null;
            }

            DB::table('platform_settings')->updateOrInsert(
                ['group_key' => self::GROUP, 'setting_key' => $key],
                $payload + ['created_at' => $now],
            );
        }
    }

    public function reset(): void
    {
        $this->save(
            collect($this->definitions())
                ->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['default']])
                ->all(),
        );
    }

    public function css(): string
    {
        $values = $this->values();
        $number = fn (string $key): int => (int) $values[$key];
        $color = fn (string $key): string => $this->safeColor((string) $values[$key], (string) $this->definitions()[$key]['default']);
        $font = in_array($values['font_family'], array_keys($this->definitions()['font_family']['options']), true)
            ? $values['font_family']
            : $this->definitions()['font_family']['default'];
        $customCss = str_ireplace('</style', '', (string) $values['custom_css']);

        return <<<CSS
:root {
    --ainpa-admin-sidebar-width: {$number('sidebar_width')}px;
    --ainpa-admin-topbar-height: {$number('header_height')}px;
    --ainpa-admin-sidebar: {$color('sidebar_background')};
    --ainpa-admin-sidebar-deep: color-mix(in srgb, {$color('sidebar_background')} 78%, #240000);
    --ainpa-admin-sidebar-text: {$color('sidebar_text_color')};
    --ainpa-admin-active-menu: {$color('active_menu_color')};
    --ainpa-primary: {$color('primary_color')};
    --ainpa-admin-primary: {$color('primary_color')};
    --ainpa-page-bg: {$color('page_background')};
    --ainpa-admin-bg: {$color('page_background')};
    --ainpa-surface: {$color('card_background')};
    --ainpa-admin-surface: {$color('card_background')};
    --ainpa-border: {$color('border_color')};
    --ainpa-admin-border: {$color('border_color')};
    --ainpa-admin-radius: {$number('border_radius')}px;
}
html body {
    background: {$color('page_background')} !important;
    font-family: {$font} !important;
    font-size: {$number('base_font_size')}px !important;
}
html body .z4-admin-bar {
    height: {$number('header_height')}px !important;
}
html body main {
    padding: {$number('content_padding')}px !important;
}
html body main section,
html body main .bg-white,
html body .admin-theme-settings-panel {
    background: {$color('card_background')} !important;
    border-color: {$color('border_color')} !important;
    border-radius: {$number('border_radius')}px !important;
    border-width: {$number('border_size')}px !important;
}
html body main section {
    margin: {$number('card_margin')}px !important;
    padding: {$number('card_padding')}px !important;
}
{$customCss}
CSS;
    }

    private function safeColor(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : $fallback;
    }
}
