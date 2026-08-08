<?php

namespace App\Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginUpdate extends Model
{
    protected $fillable = [
        'plugin_slug',
        'plugin_id',
        'version',
        'current_version',
        'available_version',
        'changelog',
        'package_url',
        'checked_at',
        'installed_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'changelog' => 'array',
            'checked_at' => 'datetime',
            'installed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }
}
