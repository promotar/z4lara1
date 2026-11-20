<?php

namespace Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsEmailNotification extends Model
{
    protected $table = 'lms_email_notifications';

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
