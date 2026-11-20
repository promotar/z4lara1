<?php

namespace Modules\Certificates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $table = 'certificate_templates';

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

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
