<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Localised copy of a blog category, stored the same way as post translations
 * so the frontend and the admin read one source of truth.
 */
class CategoryTranslation extends Model
{
    protected $table = 'blog_category_translations';

    protected $fillable = [
        'category_id',
        'locale',
        'name',
        'slug',
        'description',
        'image_alt',
        'seo_title',
        'seo_description',
        'created_by',
        'updated_by',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public static function resolve(Category $category, string $locale): ?self
    {
        return static::query()
            ->where('category_id', $category->getKey())
            ->where('locale', $locale)
            ->first();
    }

    /**
     * Apply the translation values onto the category model.
     */
    public function applyTo(Category $category): Category
    {
        $values = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_alt' => $this->image_alt,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
        ];

        $category->setRawAttributes(array_merge($category->getAttributes(), array_filter(
            $values,
            static fn ($value): bool => $value !== null && $value !== '',
        )));

        $category->syncOriginal();

        return $category;
    }

    public static function uniqueSlug(string $value, string $locale, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $base = Str::limit($base, 200, '');
        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @return array<int, self>
     */
    public static function mapForCategories(iterable $categories, string $locale): array
    {
        $ids = collect($categories)->pluck('id')->filter()->all();

        if ($ids === []) {
            return [];
        }

        return static::query()
            ->where('locale', $locale)
            ->whereIn('category_id', $ids)
            ->get()
            ->keyBy('category_id')
            ->all();
    }
}
