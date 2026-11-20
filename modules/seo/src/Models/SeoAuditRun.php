<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

final class SeoAuditRun extends Model
{
    protected $table = 'seo_audit_runs';

    protected $guarded = [];

    protected $casts = [
        'site_id' => 'integer',
        'urls' => 'integer',
        'records' => 'integer',
        'errors' => 'integer',
        'warnings' => 'integer',
        'duration_ms' => 'integer',
    ];
}
