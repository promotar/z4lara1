<?php

namespace Modules\Lms\Models;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LessonTranslation extends Model
{
    protected $table = 'lms_lesson_translations';

    protected $fillable = [
        'lesson_id', 'locale', 'title', 'slug', 'description', 'content',
        'created_by', 'updated_by',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public static function resolve(Lesson $lesson, string $locale): ?self
    {
        if ($locale === '' || $locale === PlatformLocale::DEFAULT) {
            return null;
        }

        return static::query()->where('lesson_id', $lesson->id)->where('locale', $locale)->first();
    }

    public function applyTo(Lesson $lesson): Lesson
    {
        foreach (['title', 'slug', 'description', 'content'] as $field) {
            if (filled($this->{$field})) {
                $lesson->{$field} = $this->{$field};
            }
        }

        return $lesson;
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
}
