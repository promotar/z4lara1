<?php

namespace App\Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMediaMetadata extends Model
{
    protected $table = 'platform_media_metadata';

    protected $fillable = [
        'url',
        'alt_text',
        'title',
        'caption',
        'description',
    ];
}
