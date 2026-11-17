<?php

namespace App\Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'license_key',
        'product_type',
        'product_slug',
        'domain',
        'status',
        'expires_at',
        'activated_at',
        'last_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
