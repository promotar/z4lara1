<?php

namespace Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instructor extends Model
{
    use SoftDeletes;

    protected $table = 'lms_instructors';
    protected $guarded = [];

    protected $casts = [
        'can_publish_directly' => 'boolean',
        'avatar_meta' => 'array',
        'invited_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function canPublishDirectly(): bool
    {
        return $this->status === 'active' && $this->can_publish_directly === true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
