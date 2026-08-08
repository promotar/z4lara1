<?php

namespace App\Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'parent_id',
        'plugin_id',
        'title',
        'label',
        'type',
        'url',
        'route_name',
        'route_params',
        'icon',
        'target',
        'permission',
        'metadata',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'route_params' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param Builder<MenuItem> $query
     * @return Builder<MenuItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Menu, MenuItem>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, MenuItem>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return BelongsTo<Plugin, MenuItem>
     */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }
}
