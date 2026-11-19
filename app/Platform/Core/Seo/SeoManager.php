<?php

namespace App\Platform\Core\Seo;

use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Http\Request;
use Throwable;

/**
 * Builds the final SeoData for a request.
 *
 * Order: Core defaults -> registered module contributors (ascending priority)
 * -> SEO Plugin contributor (priority 100, when active).
 */
final class SeoManager
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SeoRegistry $registry,
        private readonly ?LocaleUrlStrategy $localeUrls = null,
        private readonly ?SeoIndexability $indexability = null,
    ) {
    }

    public function forRequest(Request $request): SeoData
    {
        try {
            $settings = $this->settings->values();
        } catch (Throwable) {
            $settings = [];
        }

        $siteTitle = trim((string) ($settings['general.site_title'] ?? config('app.name', 'Art INPA')));
        $seoTitle = trim((string) ($settings['seo.seo_title'] ?? ''));
        $tagline = trim((string) ($settings['general.tagline'] ?? ''));
        $description = trim((string) ($settings['seo.seo_description'] ?? $tagline));

        $seo = new SeoData(new Robots(
            index: (bool) ($settings['seo.robots_index'] ?? true),
            follow: (bool) ($settings['seo.robots_follow'] ?? true),
        ));

        $seo->title = $seoTitle !== '' ? $seoTitle : ($siteTitle !== '' ? $siteTitle : null);
        $seo->description = $description !== '' ? $description : null;
        $seo->siteName = $siteTitle !== '' ? $siteTitle : null;
        $seo->ogTitle = trim((string) ($settings['seo.open_graph_title'] ?? ($seo->title ?? ''))) ?: null;
        $seo->ogDescription = trim((string) ($settings['seo.open_graph_description'] ?? ($seo->description ?? ''))) ?: null;
        $seo->image = $this->absoluteUrl($settings['seo.open_graph_image'] ?? null);

        // Fall back to the site logo/icon so social previews always have an image.
        if (! $seo->image) {
            $seo->image = $this->absoluteUrl($settings['general.site_logo'] ?? null)
                ?? $this->absoluteUrl($settings['general.site_icon'] ?? null);
        }

        $seo->twitterImage = $seo->image;
        $seo->twitterCard = $seo->image ? 'summary_large_image' : 'summary';
        $seo->twitterTitle = $seo->ogTitle;
        $seo->twitterDescription = $seo->ogDescription;
        $seo->twitterImage = $seo->image;

        $localeUrls = $this->localeUrls;

        if ($localeUrls === null) {
            try {
                $localeUrls = app(LocaleUrlStrategy::class);
            } catch (Throwable) {
                $localeUrls = null;
            }
        }

        $seo->canonical = $localeUrls?->canonical($request) ?? $request->url();
        $seo->alternates = $localeUrls?->alternates($request) ?? [];

        $keywords = trim((string) ($settings['seo.seo_keywords'] ?? ''));

        if ($keywords !== '') {
            $seo->meta['keywords'] = $keywords;
        }

        foreach ($this->registry->contributorsFor($request) as $contributor) {
            try {
                $contributor->contribute($seo, $request);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        // Indexability is a hard policy: apply it after contributors so a
        // module cannot accidentally make a private page indexable.
        if (($this->indexability ?? new SeoIndexability())->shouldNoindex($request)) {
            $seo->robots = $seo->robots->merge(new Robots(index: false, follow: true));
        }

        $seo->ogTitle = $seo->ogTitle ?: $seo->title;
        $seo->ogDescription = $seo->ogDescription ?: $seo->description;
        $seo->twitterTitle = $seo->twitterTitle ?: $seo->ogTitle;
        $seo->twitterDescription = $seo->twitterDescription ?: $seo->ogDescription;
        $seo->twitterImage = $seo->twitterImage ?: $seo->image;
        $seo->twitterCard = $seo->twitterCard ?: ($seo->image ? 'summary_large_image' : 'summary');

        return $seo;
    }

    private function absoluteUrl(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            ? $value
            : url($value);
    }
}
