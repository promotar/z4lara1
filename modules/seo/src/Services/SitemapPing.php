<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Http;
use Modules\Seo\Models\SeoSetting;
use Throwable;

final class SitemapPing
{
    /**
     * @return array{sitemap: string, google: int|null, bing: int|null}
     */
    public function ping(int $siteId): array
    {
        $base = rtrim((string) (config('seo.canonical.host') ?: url('/')), '/');
        $sitemap = $base.'/sitemap.xml';

        $targets = [
            'google' => 'https://www.google.com/ping?sitemap='.rawurlencode($sitemap),
            'bing' => 'https://www.bing.com/ping?sitemap='.rawurlencode($sitemap),
        ];

        $results = [];

        foreach ($targets as $engine => $endpoint) {
            try {
                $results[$engine] = Http::timeout(10)->get($endpoint)->status();
            } catch (Throwable $exception) {
                report($exception);
                $results[$engine] = null;
            }
        }

        SeoSetting::putValue(
            $siteId,
            'seo',
            'sitemap',
            'last_ping',
            [
                'at' => now()->toIso8601String(),
                'sitemap' => $sitemap,
                'results' => $results,
            ],
            'json',
        );

        return ['sitemap' => $sitemap, ...$results];
    }
}
