<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSchemaTemplate extends Model
{
    protected $table = 'seo_schema_templates';

    protected $fillable = [
        'site_id',
        'name',
        'key',
        'type',
        'module_slug',
        'route_pattern',
        'schema_json',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
