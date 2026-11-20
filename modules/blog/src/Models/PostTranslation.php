<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Blog\Support\BlogLocale;

class PostTranslation extends Model
{
    protected $table = 'blog_post_translations';

    protected $fillable = [
        'post_id',
        'locale',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'featured_image_alt',
        'seo_title',
        'seo_description',
        'focus_keyword',
        'canonical_url',
        'created_by',
        'updated_by',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public static function resolve(Post $post, string $locale): ?self
    {
        if ($locale === '' || BlogLocale::isDefault($locale)) {
            return null;
        }

        return static::query()
            ->where('post_id', $post->id)
            ->where('locale', $locale)
            ->first();
    }

    /**
     * Copy translated attributes onto a post model so the existing front-end
     * views render the requested language without duplicating templates.
     */
    public function applyTo(Post $post): Post
    {
        $values = [
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'seo_title' => $this->seo_title ?: $this->title,
            'meta_title' => $this->seo_title ?: $this->title,
            'seo_description' => $this->seo_description,
            'meta_description' => $this->seo_description,
            'focus_keyword' => $this->focus_keyword,
            'seo_focus_keyword' => $this->focus_keyword,
            'canonical_url' => $this->canonical_url,
        ];

        if (filled($this->featured_image)) {
            $values['featured_image'] = $this->featured_image;
        }

        if (filled($this->featured_image_alt)) {
            $values['featured_image_alt'] = $this->featured_image_alt;
        }

        $post->setRawAttributes(array_merge($post->getAttributes(), array_filter(
            $values,
            static fn ($value): bool => $value !== null && $value !== ''
        )));

        $post->syncOriginal();

        return $post;
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
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @return array<string, self>
     */
    public static function mapForPosts(iterable $posts, string $locale): array
    {
        $ids = collect($posts)->pluck('id')->filter()->all();

        if ($ids === []) {
            return [];
        }

        return static::query()
            ->where('locale', $locale)
            ->whereIn('post_id', $ids)
            ->get()
            ->keyBy('post_id')
            ->all();
    }
}
