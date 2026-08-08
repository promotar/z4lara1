<?php

namespace App\Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class BackupCheckpoint extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'operation_type',
        'target_type',
        'target_slug',
        'checkpoint_type',
        'status',
        'path',
        'notes',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
