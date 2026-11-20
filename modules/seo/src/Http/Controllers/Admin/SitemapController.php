<?php

namespace Modules\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Models\SeoSitemapChunk;
use Modules\Seo\Models\SeoSitemapRun;
use Modules\Seo\Services\SeoContentTypeRegistry;
use Modules\Seo\Services\SeoSiteResolver;
use Modules\Seo\Services\SitemapGenerator;
use Modules\Seo\Services\SitemapValidator;
use Throwable;

final class SitemapController extends Controller
{
    public function index(SeoSiteResolver $sites, SeoContentTypeRegistry $types): View
    {
        $run = null;
        $chunks = collect();
        $settings = [
            'enabled' => true,
            'max_urls' => (int) config('seo.sitemap.max_urls', 50000),
            'include_images' => false,
            'include_featured_images' => true,
            'exclude_attachments' => true,
            'types' => $types->defaultSettings(),
        ];

        try {
            $siteId = $sites->id();
            $settings = [
                'enabled' => (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'enabled', '', true),
                'max_urls' => (int) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'max_urls', '', config('seo.sitemap.max_urls', 50000)),
                'include_images' => (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'include_images', '', false),
                'include_featured_images' => (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'include_featured_images', '', true),
                'exclude_attachments' => (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'exclude_attachments', '', true),
                'types' => array_merge(
                    $types->defaultSettings(),
                    (array) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'types', '', []),
                ),
            ];

            $run = SeoSitemapRun::query()
                ->where('site_id', $siteId)
                ->latest('id')
                ->first();

            if ($run !== null) {
                $chunks = SeoSitemapChunk::query()
                    ->where('site_id', $siteId)
                    ->where('run_id', $run->id)
                    ->orderBy('chunk_index')
                    ->get();
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('seo::admin.sitemap', [
            'run' => $run,
            'chunks' => $chunks,
            'settings' => $settings,
            'contentTypes' => $types->labels(),
        ]);
    }

    public function update(Request $request, SeoSiteResolver $sites, SeoContentTypeRegistry $types): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'max_urls' => ['required', 'integer', 'min:1', 'max:50000'],
            'include_images' => ['nullable', 'boolean'],
            'include_featured_images' => ['nullable', 'boolean'],
            'exclude_attachments' => ['nullable', 'boolean'],
            'types' => ['nullable', 'array'],
            'types.*' => ['boolean'],
        ]);

        try {
            $siteId = $sites->id();
            $availableTypes = array_keys($types->defaultSettings());
            $selectedTypes = [];

            foreach ($availableTypes as $key) {
                $selectedTypes[$key] = (bool) (($data['types'] ?? [])[$key] ?? false);
            }

            SeoSetting::putValue($siteId, 'seo', 'sitemap', 'enabled', (bool) ($data['enabled'] ?? false), 'boolean');
            SeoSetting::putValue($siteId, 'seo', 'sitemap', 'max_urls', (int) $data['max_urls'], 'integer');
            SeoSetting::putValue($siteId, 'seo', 'sitemap', 'include_images', (bool) ($data['include_images'] ?? false), 'boolean');
            SeoSetting::putValue($siteId, 'seo', 'sitemap', 'include_featured_images', (bool) ($data['include_featured_images'] ?? false), 'boolean');
            SeoSetting::putValue($siteId, 'seo', 'sitemap', 'exclude_attachments', (bool) ($data['exclude_attachments'] ?? false), 'boolean');
            SeoSetting::putValue($siteId, 'seo', 'sitemap', 'types', $selectedTypes, 'json');

            // Generated chunks are derived data. Invalidate them so the next
            // public sitemap request is generated with the new settings.
            SeoSitemapChunk::query()->where('site_id', $siteId)->delete();
            SeoSitemapRun::query()->where('site_id', $siteId)->delete();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['sitemap' => $exception->getMessage()]);
        }

        return back()->with('status', __('seo::ui.sitemap_settings_saved'));
    }

    public function generate(SitemapGenerator $generator, SeoSiteResolver $sites): RedirectResponse
    {
        try {
            $run = $generator->generate($sites->id());

            if ($run->status === SeoSitemapRun::STATUS_COMPLETED) {
                return back()->with('status', __('seo::ui.sitemap_generated', [
                    'urls' => $run->urls_count,
                    'chunks' => $run->chunks_count,
                ]));
            }

            return back()->withErrors(['sitemap' => $run->error ?: __('seo::ui.sitemap_failed')]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['sitemap' => $exception->getMessage()]);
        }
    }

    public function validate(SitemapValidator $validator, SeoSiteResolver $sites): RedirectResponse
    {
        try {
            $result = $validator->validate($sites->id());

            return back()->with('status', __('seo::ui.sitemap_validated', [
                'errors' => $result['errors'],
                'warnings' => $result['warnings'],
                'urls' => $result['urls'],
                'chunks' => $result['chunks'],
            ]));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['sitemap' => $exception->getMessage()]);
        }
    }
}
