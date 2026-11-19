<?php

namespace App\Platform\Core\Seo;

use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Safe Core fallback sitemap. The SEO Plugin may bind a richer provider that
 * adds caching, splitting and persisted settings.
 */
final class DefaultSitemapProvider implements SitemapProvider
{
    public function __construct(private readonly SeoRegistry $registry)
    {
    }

    public function entries(): iterable
    {
        $entries = [];
        $seen = [];

        $add = static function (array $entry) use (&$entries, &$seen): void {
            $loc = trim((string) ($entry['loc'] ?? ''));

            if ($loc === '' || isset($seen[$loc])) {
                return;
            }

            $seen[$loc] = true;
            $entry['loc'] = $loc;
            $entries[] = $entry;
        };

        $sectionIndexes = [];

        $add([
            'loc' => url('/'),
            'changefreq' => 'daily',
            'priority' => 1.0,
        ]);
        $sectionIndexes[] = count($entries) - 1;

        foreach ([
            'lms.front.courses',
            'store.index',
            'blog.index',
            'exhibitions.artworks.index',
        ] as $routeName) {
            try {
                if (Route::has($routeName)) {
                    $add([
                        'loc' => route($routeName),
                        'changefreq' => 'weekly',
                        'priority' => 0.8,
                    ]);
                    $sectionIndexes[] = count($entries) - 1;
                }
            } catch (Throwable) {
                // The module may be inactive or the route may require params.
            }
        }

        foreach ($this->registry->sitemapContributors() as $contributor) {
            try {
                foreach ($contributor->sitemapEntries() as $entry) {
                    if (is_array($entry)) {
                        $add($entry);
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        // Section entries (home and module archives) carry no content date of
        // their own, so derive one from the newest item they list. This keeps
        // <lastmod> tied to real content edits instead of the generation time.
        foreach ($sectionIndexes as $index) {
            if (! isset($entries[$index]) || filled($entries[$index]['lastmod'] ?? null)) {
                continue;
            }

            $prefix = (string) ($entries[$index]['loc'] ?? '');
            $latest = null;

            foreach ($entries as $entry) {
                $loc = (string) ($entry['loc'] ?? '');
                $lastmod = trim((string) ($entry['lastmod'] ?? ''));

                if ($lastmod === '' || $loc === $prefix) {
                    continue;
                }

                if ($prefix !== url('/') && ! str_starts_with($loc, rtrim($prefix, '/').'/')) {
                    continue;
                }

                $timestamp = strtotime($lastmod);

                if ($timestamp === false) {
                    continue;
                }

                if ($latest === null || $timestamp > $latest) {
                    $latest = $timestamp;
                }
            }

            if ($latest !== null) {
                $entries[$index]['lastmod'] = gmdate('c', $latest);
            }
        }

        return $entries;
    }
}
