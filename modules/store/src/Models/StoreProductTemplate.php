<?php

namespace Modules\Store\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class StoreProductTemplate extends Model
{
    protected $table = 'store_product_templates';

    protected $fillable = [
        'name',
        'slug',
        'category',
        'status',
        'html_code',
        'css_code',
        'js_code',
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

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
