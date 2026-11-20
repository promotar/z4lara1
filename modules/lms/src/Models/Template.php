<?php

namespace Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    protected $table = 'lms_templates';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'html_code',
        'css_code',
        'js_code',
        'is_system',
        'system_key',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCatalog(Builder $query): Builder
    {
        return $query->where('type', 'catalog');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
