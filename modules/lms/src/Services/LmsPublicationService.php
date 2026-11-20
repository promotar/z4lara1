<?php

namespace Modules\Lms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\Lesson;
use Modules\Lms\Models\Student;

final class LmsPublicationService
{
    private ?bool $notificationsSupportDeduplication = null;

    private ?bool $emailOutboxAvailable = null;

    private ?bool $studentTableAvailable = null;

    public function synchronizePublishedCourse(Course $course, bool $announce): void
    {
        if ($course->status !== 'published' || ! Schema::hasTable('lms_course_enrollments')) {
            return;
        }

        $this->synchronizeInstructorOwner($course);

        $this->studentTableAvailable ??= Schema::hasTable('lms_students');
        if ($course->pricing_type !== 'free' || ! $this->studentTableAvailable) {
            return;
        }

        $this->activeStudents()->chunk(200, function ($students) use ($course, $announce): void {
            foreach ($students as $student) {
                $this->enroll((int) $course->id, (int) $student->user_id, 'free_course');
                $this->refreshStudentCache((int) $student->user_id);

                if ($announce) {
                    $kind = $course->program_type === 'diploma' ? 'diploma' : 'course';
                    $title = 'New free '.ucfirst($kind);
                    $body = $course->title.' is now available in your learning account.';
                    $path = '/courses/'.rawurlencode((string) $course->slug);
                    $this->notifyAndQueueEmail(
                        (int) $student->user_id,
                        (string) $student->email,
                        "free.{$kind}.published",
                        "free-course:{$course->id}:user:{$student->user_id}",
                        $title,
                        $body,
                        $path,
                        ['course_id' => (int) $course->id],
                    );
                }
            }
        });
    }

    public function synchronizeActiveStudent(Student $student): void
    {
        if ($student->status !== 'active' || ! $student->user_id || ! Schema::hasTable('lms_course_enrollments')) {
            return;
        }

        DB::table('lms_courses')
            ->where('status', 'published')
            ->where('pricing_type', 'free')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn ($courseId) => $this->enroll((int) $courseId, (int) $student->user_id, 'free_course'));

        $this->refreshStudentCache((int) $student->user_id);
    }

    public function announcePublishedLesson(Lesson $lesson): void
    {
        $this->studentTableAvailable ??= Schema::hasTable('lms_students');
        if ($lesson->status !== 'published' || ! Schema::hasTable('lms_course_enrollments') || ! $this->studentTableAvailable) {
            return;
        }

        $course = $lesson->course;
        if (! $course || $course->status !== 'published') {
            return;
        }

        $this->enrolledStudents((int) $course->id)->chunk(200, function ($students) use ($course, $lesson): void {
            foreach ($students as $student) {
                $path = '/courses/'.rawurlencode((string) $course->slug).'/player/'.rawurlencode((string) $lesson->slug);
                $availabilityMessage = $lesson->availability === 'scheduled' && $lesson->publish_date?->isFuture()
                    ? $lesson->title.' will be available on '.$lesson->publish_date->format('Y-m-d H:i').'.'
                    : $lesson->title.' is now available.';
                $this->notifyAndQueueEmail(
                    (int) $student->user_id,
                    (string) $student->email,
                    'course.lesson.published',
                    "lesson:{$lesson->id}:user:{$student->user_id}",
                    'New lesson in '.$course->title,
                    $availabilityMessage,
                    $path,
                    ['course_id' => (int) $course->id, 'lesson_id' => (int) $lesson->id],
                );
            }
        });
    }

    public function synchronizeInstructorOwner(Course $course, ?int $previousInstructorId = null): void
    {
        if (! Schema::hasTable('lms_course_enrollments') || ! Schema::hasTable('lms_instructors')) {
            return;
        }

        $currentInstructorId = $course->instructor_id ? (int) $course->instructor_id : null;
        if ($previousInstructorId && $previousInstructorId !== $currentInstructorId) {
            $previousUserId = DB::table('lms_instructors')->where('id', $previousInstructorId)->value('user_id');
            if ($previousUserId) {
                $this->removeObsoleteInstructorEnrollment($course, (int) $previousUserId);
            }
        }

        if (! $currentInstructorId) {
            return;
        }

        $userId = DB::table('lms_instructors')
            ->where('id', $currentInstructorId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('user_id');
        if (! $userId) {
            return;
        }

        if ($course->status === 'published') {
            $this->enroll((int) $course->id, (int) $userId, 'instructor_owner');
            $this->refreshStudentCache((int) $userId);

            return;
        }

        $this->removeObsoleteInstructorEnrollment($course, (int) $userId);
    }

    public function reconcileInstructorOwners(Course $course): void
    {
        if (! Schema::hasTable('lms_course_enrollments') || ! Schema::hasTable('lms_instructors')) {
            return;
        }

        $currentUserId = $course->instructor_id
            ? DB::table('lms_instructors')
                ->where('id', $course->instructor_id)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->value('user_id')
            : null;

        DB::table('lms_course_enrollments')
            ->where('course_id', $course->id)
            ->where('source', 'instructor_owner')
            ->pluck('user_id')
            ->map(fn ($userId): int => (int) $userId)
            ->filter(fn (int $userId): bool => ! $currentUserId || $userId !== (int) $currentUserId)
            ->each(fn (int $userId) => $this->removeObsoleteInstructorEnrollment($course, $userId));

        $this->synchronizeInstructorOwner($course);
    }

    private function removeObsoleteInstructorEnrollment(Course $course, int $userId): void
    {
        $enrollment = DB::table('lms_course_enrollments')
            ->where('course_id', $course->id)
            ->where('user_id', $userId)
            ->where('source', 'instructor_owner');

        $retainFreeAccess = $course->status === 'published'
            && $course->pricing_type === 'free'
            && Schema::hasTable('lms_students')
            && DB::table('lms_students')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->exists();

        if ($retainFreeAccess) {
            $enrollment->update(['source' => 'free_course', 'updated_at' => now()]);
        } else {
            $enrollment->delete();
        }

        $this->refreshStudentCache($userId);
    }

    private function enroll(int $courseId, int $userId, string $source): void
    {
        $now = now();
        DB::table('lms_course_enrollments')->insertOrIgnore([[
            'course_id' => $courseId,
            'user_id' => $userId,
            'source' => $source,
            'enrolled_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }

    private function refreshStudentCache(int $userId): void
    {
        $this->studentTableAvailable ??= Schema::hasTable('lms_students');
        if (! $this->studentTableAvailable) {
            return;
        }

        $courseIds = DB::table('lms_course_enrollments')
            ->where('user_id', $userId)
            ->orderBy('course_id')
            ->pluck('course_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        DB::table('lms_students')->where('user_id', $userId)->update([
            'enrolled_course_ids' => json_encode($courseIds, JSON_THROW_ON_ERROR),
            'enrolled_courses_count' => count($courseIds),
            'updated_at' => now(),
        ]);
    }

    private function notifyAndQueueEmail(
        int $userId,
        string $email,
        string $type,
        string $dedupKey,
        string $title,
        string $body,
        string $path,
        array $data,
    ): void {
        $now = now();

        $this->notificationsSupportDeduplication ??= Schema::hasTable('lms_notifications')
            && Schema::hasColumn('lms_notifications', 'dedup_key');
        if ($this->notificationsSupportDeduplication) {
            DB::table('lms_notifications')->insertOrIgnore([[
                'recipient_id' => $userId,
                'actor_id' => null,
                'type' => $type,
                'dedup_key' => $dedupKey,
                'title' => $title,
                'body' => $body,
                'data' => json_encode([...$data, 'action_url' => $path], JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);
        }

        $this->emailOutboxAvailable ??= Schema::hasTable('lms_email_notifications');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $this->emailOutboxAvailable) {
            DB::table('lms_email_notifications')->insertOrIgnore([[
                'recipient_id' => $userId,
                'email' => $email,
                'subject' => $title,
                'body' => $body."\n\n".url($path),
                'dedup_key' => $dedupKey,
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => $now,
                'processing_at' => null,
                'sent_at' => null,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);
        }
    }

    private function activeStudents()
    {
        return DB::table('lms_students as student')
            ->join('users as user', 'user.id', '=', 'student.user_id')
            ->where('student.status', 'active')
            ->whereNull('student.deleted_at')
            ->whereNotNull('student.user_id')
            ->orderBy('student.id')
            ->select(['student.id', 'student.user_id', 'user.email']);
    }

    private function enrolledStudents(int $courseId)
    {
        return DB::table('lms_course_enrollments as enrollment')
            ->join('lms_students as student', 'student.user_id', '=', 'enrollment.user_id')
            ->join('users as user', 'user.id', '=', 'student.user_id')
            ->where('enrollment.course_id', $courseId)
            ->where('student.status', 'active')
            ->whereNull('student.deleted_at')
            ->orderBy('student.id')
            ->select(['student.id', 'student.user_id', 'user.email']);
    }
}
