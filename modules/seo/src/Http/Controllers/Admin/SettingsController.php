<?php

namespace Modules\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SeoDefaults;
use Modules\Seo\Services\SeoSiteResolver;
use Throwable;

final class SettingsController extends Controller
{
    public function edit(SeoDefaults $defaults): View
    {
        return view('seo::admin.settings', [
            'settings' => $defaults->forSite(),
        ]);
    }

    public function update(Request $request, SeoSiteResolver $sites): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'default_description' => ['nullable', 'string', 'max:500'],
            'default_og_image' => ['nullable', 'string', 'max:2048'],
            'title_template' => ['nullable', 'string', 'max:255'],
            'description_template' => ['nullable', 'string', 'max:255'],
            'robots_behavior' => ['required', 'in:index_follow,index_nofollow,noindex_follow,noindex_nofollow'],
            'canonical_behavior' => ['required', 'in:auto,request,disabled'],
            'og_enabled' => ['nullable', 'boolean'],
            'social_title' => ['nullable', 'string', 'max:255'],
            'social_description' => ['nullable', 'string', 'max:500'],
            'social_image' => ['nullable', 'string', 'max:2048'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'organization_logo' => ['nullable', 'string', 'max:2048'],
            'organization_url' => ['nullable', 'url', 'max:2048'],
            'organization_email' => ['nullable', 'email', 'max:255'],
            'organization_phone' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $siteId = $sites->id();

            SeoSetting::putValue($siteId, 'seo', 'general', 'site_name', (string) ($data['site_name'] ?? ''), 'string');
            SeoSetting::putValue($siteId, 'seo', 'general', 'default_description', (string) ($data['default_description'] ?? ''), 'string');
            SeoSetting::putValue($siteId, 'seo', 'general', 'title_template', (string) ($data['title_template'] ?? ''), 'string');
            SeoSetting::putValue($siteId, 'seo', 'general', 'description_template', (string) ($data['description_template'] ?? ''), 'string');
            SeoSetting::putValue($siteId, 'seo', 'general', 'robots_behavior', (string) $data['robots_behavior'], 'string');
            SeoSetting::putValue($siteId, 'seo', 'general', 'canonical_behavior', (string) $data['canonical_behavior'], 'string');

            [$index, $follow] = match ((string) $data['robots_behavior']) {
                'noindex_follow' => [false, true],
                'index_nofollow' => [true, false],
                'noindex_nofollow' => [false, false],
                default => [true, true],
            };

            SeoSetting::putValue($siteId, 'seo', 'robots', 'index', $index, 'boolean');
            SeoSetting::putValue($siteId, 'seo', 'robots', 'follow', $follow, 'boolean');

            $this->saveImage($request, $siteId, 'general', 'default_og_image', 'default_og_image_file', (string) ($data['default_og_image'] ?? ''));

            SeoSetting::putValue($siteId, 'seo', 'social', 'og_enabled', (bool) ($data['og_enabled'] ?? false), 'boolean');
            SeoSetting::putValue($siteId, 'seo', 'social', 'title', (string) ($data['social_title'] ?? ''), 'string');
            SeoSetting::putValue($siteId, 'seo', 'social', 'description', (string) ($data['social_description'] ?? ''), 'string');
            $this->saveImage($request, $siteId, 'social', 'image', 'social_image_file', (string) ($data['social_image'] ?? ''));

            SeoSetting::putValue($siteId, 'seo', 'organization', 'name', (string) ($data['organization_name'] ?? ''), 'string');
            SeoSetting::putValue($siteId, 'seo', 'organization', 'url', (string) ($data['organization_url'] ?? ''), 'string');
            SeoSetting::putValue($siteId, 'seo', 'organization', 'email', (string) ($data['organization_email'] ?? ''), 'string');
            SeoSetting::putValue($siteId, 'seo', 'organization', 'phone', (string) ($data['organization_phone'] ?? ''), 'string');
            $this->saveImage($request, $siteId, 'organization', 'logo', 'organization_logo_file', (string) ($data['organization_logo'] ?? ''));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['seo' => $exception->getMessage()]);
        }

        return back()->with('status', __('seo::ui.saved'));
    }

    public function export(SeoSiteResolver $sites): JsonResponse
    {
        $siteId = $sites->id();

        $settings = SeoSetting::query()
            ->where('site_id', $siteId)
            ->where('module_slug', 'seo')
            ->orderBy('group_key')
            ->orderBy('setting_key')
            ->get(['group_key', 'setting_key', 'value', 'type', 'locale'])
            ->map(static fn (SeoSetting $setting): array => [
                'group_key' => $setting->group_key,
                'setting_key' => $setting->setting_key,
                'value' => $setting->value,
                'type' => $setting->type,
                'locale' => $setting->locale,
            ])
            ->all();

        return response()->json([
            'format' => 'artinpa-seo-settings',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'settings' => $settings,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="seo-settings-'.now()->format('Y-m-d').'.json"',
        ]);
    }

    public function import(Request $request, SeoSiteResolver $sites): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:2048'],
            'payload' => ['nullable', 'string'],
        ]);

        $raw = '';

        if ($request->hasFile('file')) {
            $raw = (string) file_get_contents((string) $request->file('file')->getRealPath());
        } elseif (isset($data['payload'])) {
            $raw = (string) $data['payload'];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return back()->withErrors(['file' => __('seo::ui.import_invalid_json')]);
        }

        $settings = $decoded['settings'] ?? $decoded;

        if (! is_array($settings)) {
            return back()->withErrors(['file' => __('seo::ui.import_invalid_json')]);
        }

        $siteId = $sites->id();
        $imported = 0;

        foreach ($settings as $setting) {
            if (! is_array($setting)) {
                continue;
            }

            $group = trim((string) ($setting['group_key'] ?? ''));
            $key = trim((string) ($setting['setting_key'] ?? ''));
            $type = trim((string) ($setting['type'] ?? 'string'));

            if ($group === '' || $key === '') {
                continue;
            }

            $type = in_array($type, ['string', 'text', 'boolean', 'integer', 'float', 'json', 'array'], true)
                ? $type
                : 'string';

            $value = $setting['value'] ?? null;

            if ($type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (in_array($type, ['json', 'array'], true) && is_string($value)) {
                $decodedValue = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decodedValue;
                }
            } elseif (is_array($value) && ! in_array($type, ['json', 'array'], true)) {
                $type = 'json';
            }

            SeoSetting::putValue(
                $siteId,
                'seo',
                $group,
                $key,
                $value,
                $type,
                (string) ($setting['locale'] ?? ''),
            );
            $imported++;
        }

        return back()->with('status', __('seo::ui.imported', ['count' => $imported]));
    }

    private function saveImage(Request $request, int $siteId, string $group, string $settingKey, string $fileKey, string $current): void
    {
        $value = $current;

        if ($request->hasFile($fileKey)) {
            $request->validate([
                $fileKey => ['nullable', 'image', 'max:4096'],
            ]);

            $stored = $request->file($fileKey)->store('seo', 'public');
            $value = '/storage/'.$stored;
        }

        SeoSetting::putValue($siteId, 'seo', $group, $settingKey, $value, 'string');
    }
}
