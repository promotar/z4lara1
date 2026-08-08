<?php

namespace Modules\Blog\Services;

use Illuminate\Support\Str;

class SeoScoreCalculator
{
    public function calculate(array $attributes, string $tags = ''): int
    {
        $keyword = Str::lower(trim((string) ($attributes['focus_keyword'] ?? '')));
        $title = Str::lower((string) (($attributes['seo_title'] ?? '') ?: ($attributes['title'] ?? '')));
        $slug = Str::lower((string) ($attributes['slug'] ?? ''));
        $seoDescription = (string) ($attributes['seo_description'] ?? '');
        $contentText = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($attributes['content'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u', $contentText, $words);
        $keywordSlug = Str::slug($keyword);
        $score = 0;

        $score += $keyword !== '' && Str::contains($title, $keyword) ? 15 : 0;
        $score += $keyword !== '' && Str::contains(Str::lower($seoDescription), $keyword) ? 12 : 0;
        $score += $keywordSlug !== '' && Str::contains($slug, $keywordSlug) ? 10 : 0;
        $score += $keyword !== '' && Str::contains(Str::lower($contentText), $keyword) ? 13 : 0;
        $score += count($words[0] ?? []) >= 600 ? 15 : 0;
        $score += mb_strlen($seoDescription) >= 120 && mb_strlen($seoDescription) <= 160 ? 10 : 0;
        $score += mb_strlen($title) >= 35 && mb_strlen($title) <= 65 ? 10 : 0;
        $score += ! empty($attributes['featured_image']) || ! empty($attributes['featured_image_id']) ? 7 : 0;
        $score += ! empty($attributes['category_id']) ? 5 : 0;
        $score += count(array_filter(array_map('trim', explode(',', $tags)))) > 0 ? 3 : 0;

        return min(100, $score);
    }
}
