<?php

namespace Modules\Seo\Services;

use Modules\Seo\Models\SeoSite;

final class SeoSiteResolver
{
    private ?int $resolvedSiteId = null;

    public function id(): int
    {
        if ($this->resolvedSiteId !== null) {
            return $this->resolvedSiteId;
        }

        $site = SeoSite::query()->where('is_primary', true)->first()
            ?? SeoSite::query()->orderBy('id')->first();

        if ($site !== null) {
            return $this->resolvedSiteId = (int) $site->id;
        }

        $domain = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

        return $this->resolvedSiteId = (int) SeoSite::query()->create([
            'domain' => $domain,
            'default_locale' => (string) config('app.locale', 'en'),
            'is_primary' => true,
            'settings' => null,
        ])->id;
    }
}
