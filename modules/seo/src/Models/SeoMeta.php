<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'site_id',
        'module_slug',
        'seoable_type',
        'seoable_id',
        'locale',
        'title',
        'description',
        'focus_keyword',
        'canonical',
        'robots',
        'og_title',
        'og_description',
        'og_image',
        'twitter_card',
        'og_type',
        'schema_json',
        'orphaned_at',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'orphaned_at' => 'datetime',
    ];
}
