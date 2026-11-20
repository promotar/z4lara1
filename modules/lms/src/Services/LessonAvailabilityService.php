<?php

namespace Modules\Lms\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\Lesson;
use Modules\Lms\Models\LessonProgress;

final class LessonAvailabilityService
{
    /**
     * @param  Collection<int, Lesson>  $lessons
     * @return Collection<int, int>
     */
    public function lockedLessonIds(Course $course, User $user, Collection $lessons): Collection
    {
        $lessonIds = $lessons->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $completedIds = LessonProgress::query()
            ->where('course_id', $course->getKey())
            ->where('user_id', $user->getKey())
            ->where('completed', true)
            ->whereIn('lesson_id', $lessonIds)
            ->pluck('lesson_id')
            ->map(fn ($id): int => (int) $id);
        $courseLessonIds = array_fill_keys($lessonIds, true);

        return $lessons
            ->filter(function (Lesson $lesson) use ($completedIds, $courseLessonIds): bool {
                return match ($lesson->availability ?: 'immediate') {
                    'scheduled' => ! $lesson->publish_date || $lesson->publish_date->isFuture(),
                    'after_previous' => ! $lesson->prerequisite_lesson_id
                        || ! isset($courseLessonIds[(int) $lesson->prerequisite_lesson_id])
                        || ! $completedIds->contains((int) $lesson->prerequisite_lesson_id),
                    default => false,
                };
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    public function lockMessage(Lesson $lesson): string
    {
        if (($lesson->availability ?: 'immediate') === 'scheduled') {
            return $lesson->publish_date
                ? __('lms::ui.lesson_available_on', ['date' => $lesson->publish_date->format('Y-m-d H:i')])
                : __('lms::ui.lesson_scheduled_not_available');
        }

        return __('lms::ui.lesson_complete_previous');
    }
}
