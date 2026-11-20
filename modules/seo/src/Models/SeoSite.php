<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSite extends Model
{
    protected $table = 'seo_sites';

    protected $fillable = [
        'domain',
        'default_locale',
        'is_primary',
        'settings',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'settings' => 'array',
    ];
}
