<?php

namespace Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourseCode extends Model
{
    protected $table = 'lms_course_codes';

    protected $guarded = [];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(CourseEnrollment::class, 'course_code_id');
    }
}
