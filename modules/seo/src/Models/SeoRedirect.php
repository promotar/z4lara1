<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    protected $table = 'seo_redirects';

    protected $fillable = [
        'site_id',
        'from_path',
        'to_path',
        'status_code',
        'is_active',
        'module_slug',
        'priority',
        'is_external',
        'query_behavior',
        'hits',
        'last_hit_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_external' => 'boolean',
        'last_hit_at' => 'datetime',
    ];
}
