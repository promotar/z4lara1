<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoModuleState extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_ORPHANED = 'orphaned';
    public const STATUS_PURGED = 'purged';

    protected $table = 'seo_module_states';

    protected $fillable = [
        'site_id',
        'module_slug',
        'version',
        'status',
        'settings_hash',
        'activated_at',
        'deactivated_at',
        'archived_at',
        'purge_after',
        'last_synced_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'archived_at' => 'datetime',
        'purge_after' => 'datetime',
        'last_synced_at' => 'datetime',
    ];
}
