<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSitemapChunk extends Model
{
    protected $table = 'seo_sitemap_chunks';

    protected $fillable = [
        'site_id',
        'run_id',
        'chunk_index',
        'content_type',
        'page_index',
        'urls_count',
        'bytes',
        'content',
    ];
}
