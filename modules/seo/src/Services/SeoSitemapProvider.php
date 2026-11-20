<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Seo\DefaultSitemapProvider;
use App\Platform\Core\Seo\SitemapChunkProvider;
use App\Platform\Core\Seo\SitemapIndexProvider;
use App\Platform\Core\Seo\SitemapProvider;
use Modules\Seo\Models\SeoSitemapChunk;
use Modules\Seo\Models\SeoSitemapRun;
use Throwable;

final class SeoSitemapProvider implements SitemapProvider, SitemapIndexProvider, SitemapChunkProvider
{
    /** Maps internal content-type keys to public sitemap file names. */
    private const FILE_NAMES = [
        'blog_post' => 'post',
        'blog_category' => 'category',
        'blog_tag' => 'post_tag',
        'page' => 'page',
        'store_product' => 'product',
        'store_category' => 'product_category',
        'store_brand' => 'product_brand',
        'lms_course' => 'course',
        'exhibition_artwork' => 'artwork',
        'other' => 'other',
    ];

    public function __construct(
        private readonly DefaultSitemapProvider $core,
        private readonly SeoSiteResolver $sites,
        private readonly SitemapGenerator $generator,
    ) {
    }

    public function entries(): iterable
    {
        if (! $this->enabled()) {
            return [];
        }

        return $this->core->entries();
    }

    public function sitemapIndex(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $siteId = $this->sites->id();
        $run = $this->latestRun($siteId);

        if ($run === null && (bool) config('seo.sitemap.lazy_generate', true)) {
            $run = $this->generator->generate($siteId);
        }

        if ($run === null || $run->status !== SeoSitemapRun::STATUS_COMPLETED) {
            return [];
        }

        $lastmod = $run->completed_at?->toAtomString();

        return SeoSitemapChunk::query()
            ->where('site_id', $siteId)
            ->where('run_id', $run->id)
            ->orderBy('chunk_index')
            ->get(['chunk_index'])
            ->map(static fn (SeoSitemapChunk $chunk): array => [
                'loc' => route('seo.sitemap.chunk', ['chunk' => $chunk->chunk_index]),
                'lastmod' => $lastmod,
            ])
            ->values()
            ->all();
    }

    public function sitemapChunk(int $chunk): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $siteId = $this->sites->id();
        $run = $this->latestRun($siteId);

        if ($run === null && (bool) config('seo.sitemap.lazy_generate', true)) {
            $run = $this->generator->generate($siteId);
        }

        if ($run === null || $run->status !== SeoSitemapRun::STATUS_COMPLETED) {
            return null;
        }

        return SeoSitemapChunk::query()
            ->where('site_id', $siteId)
            ->where('run_id', $run->id)
            ->where('chunk_index', $chunk)
            ->value('content');
    }

    public function sitemapSections(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $siteId = $this->sites->id();
        $run = $this->latestRun($siteId);

        if ($run === null && (bool) config('seo.sitemap.lazy_generate', true)) {
            $run = $this->generator->generate($siteId);
        }

        if ($run === null || $run->status !== SeoSitemapRun::STATUS_COMPLETED) {
            return [];
        }

        $lastmod = $run->completed_at?->toAtomString();

        return SeoSitemapChunk::query()
            ->where('site_id', $siteId)
            ->where('run_id', $run->id)
            ->whereNotNull('content_type')
            ->selectRaw('content_type, max(page_index) as pages, min(chunk_index) as first_index')
            ->groupBy('content_type')
            ->orderBy('first_index')
            ->get()
            ->map(static fn ($row): array => [
                'type' => (string) $row->content_type,
                'file' => self::FILE_NAMES[(string) $row->content_type] ?? (string) $row->content_type,
                'pages' => max(1, (int) $row->pages),
                'lastmod' => $lastmod,
            ])
            ->values()
            ->all();
    }

    public function sitemapSection(string $type, int $page = 1): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $siteId = $this->sites->id();
        $run = $this->latestRun($siteId);

        if ($run === null && (bool) config('seo.sitemap.lazy_generate', true)) {
            $run = $this->generator->generate($siteId);
        }

        if ($run === null || $run->status !== SeoSitemapRun::STATUS_COMPLETED) {
            return null;
        }

        $resolved = array_search($type, self::FILE_NAMES, true);
        $contentType = $resolved !== false ? (string) $resolved : $type;

        return SeoSitemapChunk::query()
            ->where('site_id', $siteId)
            ->where('run_id', $run->id)
            ->where('content_type', $contentType)
            ->where('page_index', max(1, $page))
            ->value('content');
    }

    private function latestRun(int $siteId): ?SeoSitemapRun
    {
        return SeoSitemapRun::query()
            ->where('site_id', $siteId)
            ->where('status', SeoSitemapRun::STATUS_COMPLETED)
            ->latest('id')
            ->first();
    }

    private function enabled(): bool
    {
        try {
            $siteId = $this->sites->id();

            return (bool) \Modules\Seo\Models\SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'enabled', '', true);
        } catch (Throwable $exception) {
            report($exception);

            return true;
        }
    }
}
