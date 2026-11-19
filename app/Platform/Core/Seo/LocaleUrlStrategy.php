<?php

namespace App\Platform\Core\Seo;

use Illuminate\Http\Request;

/**
 * Locale URL policy. Query strategy is the Phase 1 implementation; a prefix
 * strategy can be bound later without changing canonical/hreflang/sitemap
 * callers.
 */
interface LocaleUrlStrategy
{
    public function current(Request $request): string;

    public function defaultLocale(): string;

    public function canonical(Request $request): string;

    /**
     * URL used by the language switcher (preserves current query state).
     */
    public function urlFor(Request $request, string $locale): string;

    /**
     * Clean alternate URLs for canonical/hreflang/sitemap.
     *
     * @return array<string, string>
     */
    public function alternates(Request $request): array;

    public function withLocale(string $url, string $locale): string;
}
