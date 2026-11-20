<?php

namespace Modules\Lms\Observers;

use Modules\Lms\Models\Student;
use Modules\Lms\Services\LmsPublicationService;

final class StudentObserver
{
    public function __construct(private readonly LmsPublicationService $publication) {}

    public function created(Student $student): void
    {
        $this->publication->synchronizeActiveStudent($student);
    }

    public function updated(Student $student): void
    {
        if ($student->wasChanged(['status', 'user_id']) && $student->status === 'active') {
            $this->publication->synchronizeActiveStudent($student);
        }
    }
}
