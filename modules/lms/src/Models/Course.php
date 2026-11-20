<?php

namespace Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Platform\Core\Localization\PlatformLocale;

class Course extends Model
{
    use SoftDeletes;

    protected $table = 'lms_courses';

    protected $guarded = [];

    public function translations(): HasMany
    {
        return $this->hasMany(CourseTranslation::class, 'course_id');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $locale = app()->getLocale();

        $translation = CourseTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $value)
            ->first()
            ?? CourseTranslation::query()->where('slug', $value)->first();

        if ($translation) {
            return $query->whereKey($translation->course_id);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    protected $casts = [
        'partners' => 'array',
        'outcomes' => 'array',
        'requirements' => 'array',
        'target_audience' => 'array',
        'faqs' => 'array',
        'curriculum_units' => 'array',
        'featured_image_meta' => 'array',
        'published_at' => 'datetime',
        'publish_date' => 'datetime',
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'online_price' => 'decimal:2',
        'in_person_price' => 'decimal:2',
        'training_mode' => 'string',
        'enrollment_limit' => 'integer',
    ];

    /**
     * Normalized learning outcomes. Supports both storage shapes:
     * ["text", ...] (legacy) and [{"title": "...", "text": "..."}] (current editor).
     *
     * @return array<int, string>
     */
    public function outcomeLabels(): array
    {
        return collect((array) ($this->outcomes ?? []))
            ->map(function ($item): string {
                if (is_array($item)) {
                    $title = trim((string) ($item['title'] ?? ''));
                    $text = trim((string) ($item['text'] ?? ''));

                    return $title !== '' && $text !== '' ? $title.' — '.$text : ($title !== '' ? $title : $text);
                }

                return trim((string) $item);
            })
            ->filter()
            ->values()
            ->all();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'course_id')->orderBy('module_order')->orderBy('lesson_order')->orderBy('id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(CourseCode::class, 'course_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'course_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }
}
