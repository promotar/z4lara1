<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Seo\DefaultRobotsProvider;
use App\Platform\Core\Seo\RobotsProvider;
use Modules\Seo\Models\SeoSetting;
use Throwable;

final class SeoRobotsProvider implements RobotsProvider
{
    public function __construct(
        private readonly DefaultRobotsProvider $core,
        private readonly SeoSiteResolver $sites,
    ) {
    }

    public function lines(): array
    {
        $lines = $this->core->lines();

        try {
            $siteId = $this->sites->id();
            $custom = SeoSetting::valueFor($siteId, 'seo', 'robots', 'disallow', '', []);

            if (is_array($custom)) {
                $custom = array_values(array_filter(array_map(
                    static fn (mixed $path): string => '/'.ltrim(trim((string) $path), '/'),
                    $custom,
                )));

                $sitemapLine = null;
                $filtered = [];

                foreach ($lines as $line) {
                    if (str_starts_with(strtolower(trim($line)), 'sitemap:')) {
                        $sitemapLine = $line;
                        continue;
                    }

                    $filtered[] = $line;
                }

                foreach ($custom as $path) {
                    if ($path !== '/') {
                        $filtered[] = 'Disallow: '.$path;
                    }
                }

                if ($sitemapLine !== null) {
                    $filtered[] = $sitemapLine;
                }

                $lines = $filtered;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $lines;
    }
}
