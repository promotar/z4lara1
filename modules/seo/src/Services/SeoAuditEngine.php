<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Http;
use Modules\Seo\Models\SeoAuditIssue;
use Modules\Seo\Models\SeoSetting;
use Throwable;

/**
 * Practical content-level SEO audit.
 *
 * It checks the records that editors actually manage, the generated sitemap,
 * and the HTTP status of the URLs in that sitemap. It intentionally does not
 * score content, crawl the whole site, or perform keyword analysis.
 */
final class SeoAuditEngine
{
    public function __construct(
        private readonly SeoSiteResolver $sites,
        private readonly AuditUrlProvider $urls,
        private readonly ContentSeoService $contentSeo,
        private readonly SeoDefaults $defaults,
        private ?SeoContentTypeRegistry $types = null,
    ) {
    }

    /**
     * @return array{urls: int, errors: int, warnings: int, records: int}
     */
    public function run(?int $siteId = null, ?int $limit = null): array
    {
        $siteId ??= $this->sites->id();
        $limit = max(1, min(1000, $limit ?? (int) config('seo.audit.max_urls', 100)));
        $registry = $this->types ?? app(SeoContentTypeRegistry::class);

        SeoAuditIssue::query()
            ->where('site_id', $siteId)
            ->where('type', 'like', 'audit.%')
            ->where('status', 'open')
            ->delete();

        $sitemapUrls = $this->urls->urls($siteId, 50000);
        $sitemapLookup = array_fill_keys(array_map(static fn (string $url): string => rtrim($url, '/'), $sitemapUrls), true);
        $sitemapEnabled = (bool) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'enabled', '', true);
        $enabledTypes = (array) SeoSetting::valueFor($siteId, 'seo', 'sitemap', 'types', '', []);
        $settings = $this->defaults->forSite($siteId);
        $defaultDescription = trim((string) ($settings['default_description'] ?? ''));
        $errors = 0;
        $warnings = 0;
        $records = 0;

        foreach ($registry->all() as $key => $definition) {
            try {
                $query = $registry->records($key);
                $recordsForType = $query?->limit($limit)->get() ?? collect();
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            foreach ($recordsForType as $record) {
                $records++;
                $url = $registry->publicUrl($key, $record);
                $adminUrl = $registry->adminUrl($key, $record);
                $module = (string) ($definition['module'] ?? '');

                if ($url === null) {
                    $errors++;
                    $this->issue($siteId, $module, $key, (int) $record->id, $adminUrl, null, 'audit.url_missing', 'error', 'Content record has no public URL or slug.');

                    continue;
                }

                $override = $this->contentSeo->custom($key, (int) $record->id);
                $effectiveTitle = trim((string) ($override['title'] ?? '')) ?: trim((string) ($record->title ?? ''));
                $effectiveDescription = trim((string) ($override['description'] ?? ''))
                    ?: trim((string) ($record->short_description ?? $record->description ?? $record->excerpt ?? ''))
                    ?: $defaultDescription;
                $noindex = ($override['robots'] ?? '') === 'noindex' || ! $registry->robotsIndex($key, $record);

                if ($effectiveTitle === '') {
                    $warnings++;
                    $this->issue($siteId, $module, $key, (int) $record->id, $adminUrl, $url, 'audit.title_missing', 'warning', 'No effective SEO title is available for this content record.');
                }

                if ($effectiveDescription === '') {
                    $warnings++;
                    $this->issue($siteId, $module, $key, (int) $record->id, $adminUrl, $url, 'audit.description_missing', 'warning', 'No effective meta description is available for this content record.');
                }

                if ($noindex) {
                    $warnings++;
                    $this->issue($siteId, $module, $key, (int) $record->id, $adminUrl, $url, 'audit.noindex', 'warning', 'This content record is configured as noindex.');
                }

                $typeEnabled = ! array_key_exists($key, $enabledTypes) || (bool) $enabledTypes[$key];

                if (! $noindex && $sitemapEnabled && $typeEnabled && ! isset($sitemapLookup[rtrim($url, '/')])) {
                    $warnings++;
                    $this->issue($siteId, $module, $key, (int) $record->id, $adminUrl, $url, 'audit.not_in_sitemap', 'warning', 'Indexable content is not included in the generated sitemap.');
                }
            }
        }

        $checkedUrls = array_slice($sitemapUrls, 0, $limit);

        $checked = [];

        foreach ($checkedUrls as $url) {
            try {
                $response = Http::timeout((int) config('seo.audit.timeout', 10))
                    ->withoutRedirecting()
                    ->withHeaders(['User-Agent' => (string) config('seo.audit.user_agent', 'ArtINPA-SEO-Audit/1.0')])
                    ->get($this->auditUrl($url));
            } catch (Throwable $exception) {
                $errors++;
                $checked[$url] = 'error: '.$exception->getMessage();
                $this->issue($siteId, null, null, null, null, $url, 'audit.status_error', 'error', 'Request failed: '.$exception->getMessage());

                continue;
            }

            $status = $response->status();
            $checked[$url] = $status;

            if ($status === 404) {
                $errors++;
                $this->issue($siteId, null, null, null, null, $url, 'audit.status_404', 'error', 'Sitemap URL returns HTTP 404.');
            } elseif ($status >= 500 || $status === 0) {
                $errors++;
                $this->issue($siteId, null, null, null, null, $url, 'audit.status_error', 'error', 'Sitemap URL returns HTTP '.$status.'.');
            }
        }

        SeoSetting::putValue($siteId, 'seo', 'audit', 'last_run', now()->toIso8601String(), 'string');
        SeoSetting::putValue($siteId, 'seo', 'audit', 'last_urls', count($checkedUrls), 'integer');
        SeoSetting::putValue($siteId, 'seo', 'audit', 'last_errors', $errors, 'integer');
        SeoSetting::putValue($siteId, 'seo', 'audit', 'last_warnings', $warnings, 'integer');

        return ['urls' => count($checkedUrls), 'errors' => $errors, 'warnings' => $warnings, 'records' => $records, 'checked' => $checked];
    }

    private function auditUrl(string $url): string
    {
        $base = trim((string) config('seo.audit.base_url', ''));

        if ($base !== '') {
            return $this->replaceOrigin($url, $base);
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $port = parse_url($url, PHP_URL_PORT);

        // Docker/local installs often expose the app on :8089 while the app
        // container itself listens on :80. Keep local audits useful instead
        // of reporting every URL as a connection failure.
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) && is_int($port) && ! in_array($port, [80, 443], true)) {
            return $this->replaceOrigin($url, 'http://127.0.0.1');
        }

        return $url;
    }

    private function replaceOrigin(string $url, string $base): string
    {
        $parts = parse_url($url);
        $baseParts = parse_url($base);

        if (! is_array($parts) || ! is_array($baseParts) || empty($baseParts['scheme']) || empty($baseParts['host'])) {
            return $url;
        }

        $origin = $baseParts['scheme'].'://'.$baseParts['host'];
        $origin .= isset($baseParts['port']) ? ':'.$baseParts['port'] : '';

        return $origin.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function issue(
        int $siteId,
        ?string $module,
        ?string $contentType,
        ?int $contentId,
        ?string $adminUrl,
        ?string $url,
        string $type,
        string $severity,
        string $message,
    ): void {
        $url = $url ?? '';

        SeoAuditIssue::query()->create([
            'site_id' => $siteId,
            'module_slug' => $module,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'admin_url' => $adminUrl,
            'url_hash' => $url !== '' ? hash('sha256', $url) : null,
            'url' => $url,
            'type' => $type,
            'severity' => $severity,
            'status' => 'open',
            'message' => $message,
            'detected_at' => now(),
        ]);
    }
}
