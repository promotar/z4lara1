<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSitemapRun extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'seo_sitemap_runs';

    protected $fillable = [
        'site_id',
        'status',
        'urls_count',
        'chunks_count',
        'bytes',
        'started_at',
        'completed_at',
        'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
