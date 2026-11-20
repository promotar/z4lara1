<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAuditIssue extends Model
{
    protected $table = 'seo_audit_issues';

    protected $fillable = [
        'site_id',
        'module_slug',
        'content_type',
        'content_id',
        'admin_url',
        'url_hash',
        'url',
        'type',
        'severity',
        'status',
        'message',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
