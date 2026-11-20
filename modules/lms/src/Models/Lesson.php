<?php

namespace Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use SoftDeletes;

    protected $table = 'lms_lessons';

    protected $guarded = [];

    protected $casts = [
        'publish_date' => 'datetime',
        'published_at' => 'datetime',
        'featured_image_meta' => 'array',
        'attachments' => 'array',
        'resources' => 'array',
        'free_preview' => 'boolean',
        'prerequisite_lesson_id' => 'integer',
        'lesson_order' => 'integer',
        'duration_hours' => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LessonTranslation::class, 'lesson_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prerequisite_lesson_id');
    }

    public function dependentLessons(): HasMany
    {
        return $this->hasMany(self::class, 'prerequisite_lesson_id');
    }
}
