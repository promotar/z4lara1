<?php

namespace Modules\Lms\Models;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CourseTranslation extends Model
{
    protected $table = 'lms_course_translations';

    protected $fillable = [
        'course_id',
        'locale',
        'title',
        'slug',
        'subtitle',
        'short_description',
        'description',
        'overview',
        'outcomes',
        'requirements',
        'target_audience',
        'faqs',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'outcomes' => 'array',
        'requirements' => 'array',
        'target_audience' => 'array',
        'faqs' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public static function resolve(Course $course, string $locale): ?self
    {
        if ($locale === '' || $locale === app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode()) {
            return null;
        }

        return static::query()
            ->where('course_id', $course->id)
            ->where('locale', $locale)
            ->first();
    }

    public function applyTo(Course $course): Course
    {
        $values = [
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'overview' => $this->overview,
            'outcomes' => $this->outcomes,
            'requirements' => $this->requirements,
            'target_audience' => $this->target_audience,
            'faqs' => $this->faqs,
        ];

        $course->forceFill(array_filter(
            $values,
            static fn ($value): bool => $value !== null && $value !== '' && $value !== []
        ));

        return $course;
    }

    public static function uniqueSlug(string $value, string $locale, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $base = Str::limit($base, 200, '');
        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @return array<int, self>
     */
    public static function mapForCourses(iterable $courses, string $locale): array
    {
        $ids = collect($courses)->pluck('id')->filter()->all();

        if ($ids === []) {
            return [];
        }

        return static::query()
            ->where('locale', $locale)
            ->whereIn('course_id', $ids)
            ->get()
            ->keyBy('course_id')
            ->all();
    }
}
