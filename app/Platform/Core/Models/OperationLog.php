<?php

namespace App\Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class OperationLog extends Model
{
    public const STATUS_STARTED = 'started';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'operation_type',
        'target_type',
        'target_slug',
        'status',
        'message',
        'context',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
