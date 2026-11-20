<?php

namespace Modules\Store\Infrastructure\ReadModel;

use Illuminate\Support\Facades\DB;
use Modules\Store\Contracts\Categories\CategoryReadService;
use Modules\Store\Contracts\Categories\CategoryView;

final class DatabaseCategoryReadService implements CategoryReadService
{
    public function tree(string $locale, string $fallbackLocale, bool $includeInactive = false): array
    {
        $categories = DB::table('store_categories')
            ->when(! $includeInactive, static fn ($query) => $query->where('status', 'active'))
            ->orderBy('depth')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'public_id', 'parent_id', 'status', 'depth', 'position']);
        if ($categories->isEmpty()) {
            return [];
        }

        $ids = $categories->pluck('id')->all();
        $translations = DB::table('store_category_translations')
            ->whereIn('category_id', $ids)
            ->whereIn('locale', array_values(array_unique([$locale, $fallbackLocale])))
            ->get(['category_id', 'locale', 'name', 'slug'])
            ->groupBy('category_id');
        $publicIds = $categories->pluck('public_id', 'id');
        $views = [];

        foreach ($categories as $category) {
            $available = $translations->get($category->id, collect());
            $translation = $available->firstWhere('locale', $locale)
                ?? $available->firstWhere('locale', $fallbackLocale);
            if ($translation === null) {
                continue;
            }
            $views[] = new CategoryView(
                publicId: (string) $category->public_id,
                parentPublicId: $category->parent_id === null ? null : (string) $publicIds->get($category->parent_id),
                status: (string) $category->status,
                depth: (int) $category->depth,
                position: (int) $category->position,
                locale: (string) $translation->locale,
                usedFallback: (string) $translation->locale !== $locale,
                name: (string) $translation->name,
                slug: (string) $translation->slug,
            );
        }

        return $views;
    }
}
