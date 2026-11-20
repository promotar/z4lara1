<?php

namespace Modules\Lms\Observers;

use Modules\Lms\Models\Lesson;
use Modules\Lms\Services\LmsPublicationService;

final class LessonObserver
{
    public function __construct(private readonly LmsPublicationService $publication) {}

    public function created(Lesson $lesson): void
    {
        if ($lesson->status === 'published') {
            $this->publication->announcePublishedLesson($lesson);
        }
    }

    public function updated(Lesson $lesson): void
    {
        if ($lesson->status === 'published' && $lesson->wasChanged('status')) {
            $this->publication->announcePublishedLesson($lesson);
        }
    }

    public function deleted(Lesson $lesson): void
    {
        Lesson::query()
            ->where('course_id', $lesson->course_id)
            ->where('prerequisite_lesson_id', $lesson->getKey())
            ->update([
                'availability' => 'immediate',
                'prerequisite_lesson_id' => null,
            ]);
    }
}
