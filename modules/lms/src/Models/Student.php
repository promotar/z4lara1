<?php

namespace Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $table = 'lms_students';

    protected $guarded = [];

    protected $casts = [
        'registered_at' => 'datetime',
        'last_active_at' => 'datetime',
        'avatar_meta' => 'array',
        'enrolled_course_ids' => 'array',
        'enrolled_courses_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'user_id', 'user_id');
    }
}
