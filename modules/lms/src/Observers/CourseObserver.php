<?php

namespace Modules\Lms\Observers;

use Modules\Lms\Models\Course;
use Modules\Lms\Services\LmsPublicationService;

final class CourseObserver
{
    public function __construct(private readonly LmsPublicationService $publication) {}

    public function created(Course $course): void
    {
        if ($course->status === 'published') {
            $this->publication->synchronizePublishedCourse($course, true);
        }
    }

    public function updated(Course $course): void
    {
        $ownerChanged = $course->wasChanged('instructor_id');
        if ($ownerChanged || $course->wasChanged('status')) {
            $previousInstructorId = $ownerChanged && $course->getRawOriginal('instructor_id')
                ? (int) $course->getRawOriginal('instructor_id')
                : null;
            $this->publication->synchronizeInstructorOwner($course, $previousInstructorId);
        }

        if ($course->status !== 'published') {
            return;
        }

        $wasPublishedNow = $course->wasChanged('status');
        $becameFree = $course->pricing_type === 'free' && $course->wasChanged('pricing_type');

        if ($wasPublishedNow || $becameFree) {
            $this->publication->synchronizePublishedCourse($course, $wasPublishedNow || $becameFree);
        }
    }
}
