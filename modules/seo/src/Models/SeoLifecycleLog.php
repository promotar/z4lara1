<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoLifecycleLog extends Model
{
    protected $table = 'seo_lifecycle_log';

    protected $fillable = [
        'site_id',
        'module_slug',
        'action',
        'actor_id',
        'before',
        'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];
}
