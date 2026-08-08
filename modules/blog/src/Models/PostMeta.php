<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMeta extends Model
{
    protected $table = 'blog_post_meta';

    protected $fillable = ['post_id', 'meta_key', 'meta_value'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
