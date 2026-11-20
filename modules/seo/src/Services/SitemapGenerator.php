<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Seo\DefaultSitemapProvider;
use App\Platform\Core\Seo\LocaleUrlStrategy;
use App\Platform\Core\Seo\SeoRegistry;
use App\Platform\Core\Seo\SitemapXmlRenderer;
use Modules\Seo\Jobs\GenerateSitemapJob;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Models\SeoSitemapChunk;
use Modules\Seo\Models\SeoSitemapRun;
use Throwable;

final class SitemapGenerator
{
    private ?SeoContentTypeRegistry $types;

    public function __construct(
        private readonly SitemapXmlRenderer $renderer,
        private readonly SeoRegistry $registry,
        private readonly SeoSiteResolver $sites,
        private readonly SitemapUrlPolicy $policy,
        private readonly PlatformLocale $locale,
        private readonly LocaleUrlStrategy $localeUrls,
        ?SeoContentTypeRegistry $types = null,
    ) {
        $this->types = $types;
    }

    public function queue(?int $siteId = null): void
    {
        GenerateSitemapJob::dispatch($siteId ?? $this->sites->id());
    }

    public function generate(?int $siteId = null): SeoSitemapRun
    {
        $siteId ??= $this->sites->id();

        $run = SeoSitemapRun::query()->create([
            'site_id' => $siteId,
            'status' => SeoSitemapRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            SeoSetting::flushCache();
            $maxUrls = max(1, (int) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'max_urls', '', config('seo.sitemap.max_urls', 50000)));
            $maxBytes = max(1024, (int) config('seo.sitemap.max_bytes', 52428800));
            $types = $this->types ?? app(SeoContentTypeRegistry::class);
            $enabledTypes = (array) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'types', '', []);
            $excludeAttachments = (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'exclude_attachments', '', false);
            $includeImages = (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'include_images', '', false);
            $includeFeaturedImages = (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'include_featured_images', '', true);
            $headerBytes = strlen($this->renderer->urlset([]));
            $current = [];
            $currentBytes = [];
            $nextIndex = [];
            $chunkIndex = 1;
            $chunksTotal = 0;
            $totalUrls = 0;
            $totalBytes = 0;
            $seen = [];

            $flush = function (string $type) use (
                &$current,
                &$currentBytes,
                &$nextIndex,
                &$chunkIndex,
                &$chunksTotal,
                &$totalUrls,
                &$totalBytes,
                $run,
                $siteId,
                $headerBytes,
            ): void {
                if (empty($current[$type])) {
                    return;
                }

                $xml = $this->renderer->urlset($current[$type]);
                $bytes = strlen($xml);
                $index = $nextIndex[$type] ?? 1;

                SeoSitemapChunk::query()->create([
                    'site_id' => $siteId,
                    'run_id' => $run->id,
                    'chunk_index' => $chunkIndex,
                    'content_type' => $type,
                    'page_index' => $index,
                    'urls_count' => count($current[$type]),
                    'bytes' => $bytes,
                    'content' => $xml,
                ]);

                $totalUrls += count($current[$type]);
                $totalBytes += $bytes;
                $nextIndex[$type] = $index + 1;
                $chunkIndex++;
                $chunksTotal++;
                $current[$type] = [];
                $currentBytes[$type] = $headerBytes;
            };

            $entries = (new DefaultSitemapProvider($this->registry))->entries();

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                foreach ($this->localizedEntries($entry) as $localized) {
                    $loc = $this->canonicalize(trim((string) ($localized['loc'] ?? '')));
                    $contentType = $loc !== '' ? $types->typeForUrl($loc) : null;

                    if ($loc === '' || isset($seen[$loc]) || ! $this->policy->allows($loc, $siteId)) {
                        continue;
                    }

                    if ($contentType !== null && array_key_exists($contentType, $enabledTypes) && ! (bool) $enabledTypes[$contentType]) {
                        continue;
                    }

                    if ($excludeAttachments && $this->looksLikeAttachment($loc)) {
                        continue;
                    }

                    if ($includeImages && $contentType !== null) {
                        $images = $types->imagesForUrl($loc, $includeFeaturedImages);

                        if ($images !== []) {
                            $localized['images'] = $images;
                        }
                    } else {
                        unset($localized['images']);
                    }

                    $seen[$loc] = true;
                    $localized['loc'] = $loc;
                    $entryXml = $this->renderer->urlEntry($localized);

                    $section = $contentType ?? 'other';
                    $current[$section] ??= [];
                    $currentBytes[$section] ??= $headerBytes;

                    if ($current[$section] !== [] && (count($current[$section]) >= $maxUrls || $currentBytes[$section] + strlen($entryXml) > $maxBytes)) {
                        $flush($section);
                    }

                    $current[$section][] = $localized;
                    $currentBytes[$section] += strlen($entryXml);
                }
            }

            foreach (array_keys($current) as $section) {
                $flush($section);
            }

            SeoSitemapChunk::query()
                ->where('site_id', $siteId)
                ->where('run_id', '!=', $run->id)
                ->delete();

            $run->update([
                'status' => SeoSitemapRun::STATUS_COMPLETED,
                'urls_count' => $totalUrls,
                'chunks_count' => $chunksTotal,
                'bytes' => $totalBytes,
                'completed_at' => now(),
                'error' => null,
            ]);

            $this->pruneRuns($siteId);
        } catch (Throwable $exception) {
            SeoSitemapChunk::query()->where('run_id', $run->id)->delete();
            $run->update([
                'status' => SeoSitemapRun::STATUS_FAILED,
                'completed_at' => now(),
                'error' => \Illuminate\Support\Str::limit($exception->getMessage(), 250),
            ]);
            report($exception);
        }

        return $run->refresh();
    }

    /**
     * Keep only the newest runs (default 20) so the history table cannot grow
     * forever. Chunks of removed runs are deleted as well.
     */
    private function pruneRuns(int $siteId): void
    {
        $keep = max(1, (int) config('seo.sitemap.keep_runs', 20));

        $ids = SeoSitemapRun::query()
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        SeoSitemapChunk::query()->whereIn('run_id', $ids)->delete();
        SeoSitemapRun::query()->whereIn('id', $ids)->delete();
    }

    private function looksLikeAttachment(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return preg_match('#\.(?:pdf|docx?|xlsx?|pptx?|zip|rar|7z|csv|txt|mp3|wav|mp4|webm|mov|avi|mkv)$#i', $path) === 1;
    }

    /**
     * Expand one canonical entry into the locale variants that should be
     * indexed. In prefix mode this makes /en/... URLs first-class sitemap
     * entries and guarantees absolute, reciprocal hreflang links.
     *
     * @param  array<string, mixed>  $entry
     * @return iterable<int, array<string, mixed>>
     */
    private function localizedEntries(array $entry): iterable
    {
        $loc = $this->canonicalize(trim((string) ($entry['loc'] ?? '')));

        if ($loc === '') {
            return;
        }

        $default = $this->locale->defaultCode() ?: $this->localeUrls->defaultLocale();
        $codes = array_keys($this->locale->languages());

        if ($codes === []) {
            $codes = [$default];
        }

        $codes = array_values(array_unique(array_map('strval', $codes)));
        $rawAlternates = is_array($entry['alternates'] ?? null) ? $entry['alternates'] : [];
        $prefixMode = strtolower((string) config('seo.locale.mode', 'query')) === 'prefix';

        $variants = [$default => $loc];

        if ($prefixMode) {
            foreach ($codes as $code) {
                if ($code === $default) {
                    continue;
                }

                $source = $this->alternateSource($rawAlternates, $code, $loc);
                $url = $this->canonicalize($this->localeUrls->withLocale($source, $code));

                if ($url !== '') {
                    $variants[$code] = $url;
                }
            }
        }

        $alternates = [];

        foreach (array_values(array_unique(array_merge($codes, ['x-default']))) as $code) {
            if ($code === 'x-default') {
                $url = $variants[$default] ?? $loc;
            } else {
                $source = $this->alternateSource($rawAlternates, $code, $loc);
                $url = $this->canonicalize($this->localeUrls->withLocale($source, $code));
            }

            if ($url !== '') {
                $alternates[$code] = $url;
            }
        }

        foreach ($variants as $url) {
            $localized = $entry;
            $localized['loc'] = $url;
            $localized['alternates'] = $alternates;

            yield $localized;
        }
    }

    /**
     * @param  array<string, mixed>  $alternates
     */
    private function alternateSource(array $alternates, string $locale, string $fallback): string
    {
        $value = $alternates[$locale] ?? null;

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : $fallback;
    }

    private function canonicalize(string $url): string
    {
        $canonical = trim((string) config('seo.canonical.host'));

        if ($canonical === '') {
            try {
                $canonical = request()->getSchemeAndHttpHost();
            } catch (\Throwable) {
                $canonical = (string) url('/');
            }
        }

        if ($canonical === '') {
            return $url;
        }

        $base = parse_url($canonical);
        $parts = parse_url($url);

        if (! is_array($base) || empty($base['host']) || ! is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $scheme = $base['scheme'] ?? 'https';
        $port = isset($base['port']) ? ':'.$base['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$base['host'].$port.$path.$query;
    }
}
