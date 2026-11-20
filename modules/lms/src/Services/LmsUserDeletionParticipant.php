<?php

namespace Modules\Lms\Services;

use App\Models\User;
use App\Platform\Core\Contracts\UserDeletionParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LmsUserDeletionParticipant implements UserDeletionParticipant
{
    public function pluginSlug(): string
    {
        return 'lms';
    }

    public function blockers(User $user): array
    {
        $checks = [
            ['lms_course_enrollments', 'user_id', 'course enrollment'],
            ['lms_lesson_progress', 'user_id', 'lesson progress record'],
            ['lms_students', 'user_id', 'student profile'],
            ['lms_instructors', 'user_id', 'instructor profile'],
            ['lms_instructor_requests', 'user_id', 'instructor request'],
            ['lms_notifications', 'recipient_id', 'LMS notification'],
        ];

        $blockers = [];
        foreach ($checks as [$table, $column, $label]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = DB::table($table)->where($column, $user->getKey())->count();
            if ($count > 0) {
                $blockers[] = "has {$count} {$label}".($count === 1 ? '' : 's');
            }
        }

        return $blockers;
    }

    public function cleanup(User $user): void
    {
        // Learner, instructor, enrollment, and progress history is never erased
        // implicitly with a platform account. Administrators must resolve it first.
    }
}
