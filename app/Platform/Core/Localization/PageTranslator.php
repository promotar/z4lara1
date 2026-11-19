<?php

namespace App\Platform\Core\Localization;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the published translation of a platform page to the object that the
 * public views render. One implementation for every entry point (page route,
 * front page, layout sections) so a language switch never depends on the URL
 * that happened to render the page.
 */
final class PageTranslator
{
    public function __construct(private readonly PlatformLocale $platform) {}

    public function localize(object $page): object
    {
        try {
            if (! Schema::hasTable('platform_page_translations')) {
                return $page;
            }

            $locale = app()->getLocale();

            if (! $this->platform->isAvailable($locale) || $locale === $this->platform->defaultCode()) {
                return $page;
            }

            $translation = DB::table('platform_page_translations')
                ->where('page_id', $page->id ?? 0)
                ->where('locale', $locale)
                ->where('status', 'published')
                ->first();

            if ($translation === null) {
                return $page;
            }

            $hasContent = filled($translation->vvvebjs_html)
                || filled($translation->html)
                || filled($translation->css);

            return (object) array_merge((array) $page, [
                'title' => $translation->title ?: ($page->title ?? null),
                'seo_title' => $translation->seo_title ?: ($page->seo_title ?? null),
                'meta_description' => $translation->meta_description ?: ($page->meta_description ?? null),
                'vvvebjs_html' => $translation->vvvebjs_html ?: ($page->vvvebjs_html ?? null),
                'html' => $translation->html ?: ($page->html ?? null),
                'content' => filled($translation->html) ? $translation->html : ($page->content ?? null),
                'css' => $translation->css ?: ($page->css ?? null),
                'translation_locale' => $hasContent ? $locale : null,
            ]);
        } catch (\Throwable) {
            return $page;
        }
    }
}

