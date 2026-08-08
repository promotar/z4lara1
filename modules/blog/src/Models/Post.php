<?php

namespace Modules\Blog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $table = 'blog_posts';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'status',
        'visibility',
        'password',
        'featured_image',
        'featured_image_id',
        'featured_image_alt',
        'layout_template',
        'template',
        'layout',
        'meta_title',
        'meta_description',
        'seo_title',
        'seo_description',
        'seo_focus_keyword',
        'focus_keyword',
        'seo_score',
        'seo_schema_type',
        'schema_type',
        'seo_social_title',
        'seo_social_description',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'published_at',
        'scheduled_at',
        'author_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'deleted_at' => 'datetime',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'seo_score' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            });
    }

    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->published();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'blog_category_post', 'post_id', 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'blog_post_tag', 'post_id', 'tag_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class, 'post_id')->latest();
    }

    public function metas(): HasMany
    {
        return $this->hasMany(PostMeta::class, 'post_id');
    }

    public function latestAutosave(): HasOne
    {
        return $this->hasOne(Revision::class, 'post_id')->where('revision_type', 'autosave')->latestOfMany();
    }

    public function getSeoTitleAttribute(?string $value): ?string
    {
        return $value ?: $this->attributes['meta_title'] ?? null;
    }

    public function getSeoDescriptionAttribute(?string $value): ?string
    {
        return $value ?: $this->attributes['meta_description'] ?? null;
    }

    public function getFocusKeywordAttribute(?string $value): ?string
    {
        return $value ?: $this->attributes['seo_focus_keyword'] ?? null;
    }

    public function getSchemaTypeAttribute(?string $value): ?string
    {
        return $value ?: $this->attributes['seo_schema_type'] ?? null;
    }

    public function featuredImageUrl(): ?string
    {
        return $this->featuredImage?->url ?: $this->featured_image;
    }
}
