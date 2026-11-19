<?php

namespace Modules\ArtInpaAdminProTheme\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\ArtInpaAdminProTheme\ThemeSettings;

class AdminThemeSettingsController
{
    public function index(ThemeSettings $settings): View
    {
        return view('admin-theme::settings', [
            'themeVersion' => '1.1.0',
            'definitions' => $settings->definitions(),
            'values' => $settings->values(),
        ]);
    }

    public function update(Request $request, ThemeSettings $settings): RedirectResponse
    {
        $fontFamilies = array_keys($settings->definitions()['font_family']['options']);
        $colorRule = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $validated = $request->validate([
            'sidebar_width' => ['required', 'integer', 'between:160,360'],
            'sidebar_background' => $colorRule,
            'sidebar_text_color' => $colorRule,
            'active_menu_color' => $colorRule,
            'primary_color' => $colorRule,
            'page_background' => $colorRule,
            'card_background' => $colorRule,
            'card_padding' => ['required', 'integer', 'between:0,64'],
            'card_margin' => ['required', 'integer', 'between:0,64'],
            'border_color' => $colorRule,
            'border_size' => ['required', 'integer', 'between:0,8'],
            'font_family' => ['required', Rule::in($fontFamilies)],
            'base_font_size' => ['required', 'integer', 'between:10,22'],
            'border_radius' => ['required', 'integer', 'between:0,32'],
            'content_padding' => ['required', 'integer', 'between:0,80'],
            'header_height' => ['required', 'integer', 'between:32,120'],
            'custom_css' => [
                'nullable',
                'string',
                'max:50000',
                fn (string $attribute, mixed $value, \Closure $fail) => stripos((string) $value, '</style') !== false
                    ? $fail('Custom CSS cannot contain a closing style tag.')
                    : null,
            ],
        ]);

        $settings->save($validated + ['custom_css' => '']);

        return back()->with('status', 'Theme settings saved successfully.');
    }

    public function reset(ThemeSettings $settings): RedirectResponse
    {
        $settings->reset();

        return back()->with('status', 'Theme settings restored to defaults.');
    }
}
