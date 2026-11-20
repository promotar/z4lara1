<?php

namespace Modules\Blog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    protected $table = 'blog_templates';

    protected $fillable = [
        'name',
        'slug',
        'category',
        'status',
        'html_code',
        'css_code',
        'js_code',
        'preview_image_id',
        'preview_image',
        'created_by',
        'updated_by',
        'is_system',
        'system_key',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function previewImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'preview_image_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function previewImageUrl(): ?string
    {
        return $this->previewImage?->url ?: $this->preview_image;
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
