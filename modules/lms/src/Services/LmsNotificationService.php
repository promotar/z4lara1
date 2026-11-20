<?php

namespace Modules\Lms\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\Lesson;
use Modules\Lms\Models\LmsNotification;

final class LmsNotificationService
{
    public function notifyAdministratorsOfSubmission(User $actor, Course $course, ?Lesson $lesson = null): void
    {
        if (! Schema::hasTable('lms_notifications')) {
            return;
        }

        $kind = $lesson ? 'lesson' : ($course->program_type === 'diploma' ? 'diploma' : 'course');
        $subject = $lesson?->title ?: $course->title;
        $status = $lesson?->status ?: $course->status;
        $reviewStatus = $lesson?->review_status ?: $course->review_status;
        $requiresReview = $status !== 'published' || $reviewStatus === 'pending';
        $actionUrl = $lesson
            ? route('admin.plugins.lms.lessons.edit', $lesson, false)
            : route('admin.plugins.lms.courses.edit', $course, false);

        foreach ($this->administratorIds() as $recipientId) {
            LmsNotification::query()->create([
                'recipient_id' => $recipientId,
                'actor_id' => $actor->getKey(),
                'type' => "instructor.{$kind}.created",
                'title' => ucfirst($kind).' created by '.$actor->name,
                'body' => $subject.' · '.($requiresReview ? 'Draft — approval required' : 'Published'),
                'data' => [
                    'course_id' => $course->getKey(),
                    'course_title' => $course->title,
                    'lesson_id' => $lesson?->getKey(),
                    'lesson_title' => $lesson?->title,
                    'status' => $status,
                    'review_status' => $reviewStatus,
                    'action_url' => $actionUrl,
                ],
            ]);
        }
    }

    public function notifyAdministratorsOfInstructorRequest(User $actor): void
    {
        if (! Schema::hasTable('lms_notifications')) {
            return;
        }

        foreach ($this->administratorIds() as $recipientId) {
            LmsNotification::query()->create([
                'recipient_id' => $recipientId,
                'actor_id' => $actor->getKey(),
                'type' => 'instructor.request.created',
                'title' => 'New instructor request',
                'body' => $actor->name.' ('.$actor->email.') requested instructor access.',
                'data' => ['action_url' => route('admin.plugins.lms.instructor-requests.index', [], false)],
            ]);
        }
    }

    public function notifyInstructorDecision(User $recipient, string $decision, ?string $note = null): void
    {
        if (! Schema::hasTable('lms_notifications')) {
            return;
        }

        LmsNotification::query()->create([
            'recipient_id' => $recipient->getKey(),
            'actor_id' => auth()->id(),
            'type' => 'instructor.request.'.$decision,
            'title' => $decision === 'approved' ? 'Instructor request approved' : 'Instructor request updated',
            'body' => $decision === 'approved'
                ? 'You can now create courses, diplomas, and lessons from your teaching dashboard.'
                : ($note ?: 'Your instructor request was not approved.'),
            'data' => [
                'decision' => $decision,
                'action_url' => $decision === 'approved'
                    ? route('lms.front.instructor.courses', [], false)
                    : route('lms.front.instructor-request.create', [], false),
            ],
        ]);
    }

    public function notifySubmissionDecision(Course $course, string $status, ?Lesson $lesson = null): void
    {
        $recipient = $course->instructor?->user;
        if (! $recipient || ! Schema::hasTable('lms_notifications')) {
            return;
        }

        $kind = $lesson ? 'lesson' : ($course->program_type === 'diploma' ? 'diploma' : 'course');
        $subject = $lesson?->title ?: $course->title;
        LmsNotification::query()->create([
            'recipient_id' => $recipient->getKey(),
            'actor_id' => auth()->id(),
            'type' => "instructor.{$kind}.reviewed",
            'title' => ucfirst($kind).' review updated',
            'body' => $subject.' · '.ucfirst($status),
            'data' => [
                'status' => $status,
                'course_id' => $course->getKey(),
                'lesson_id' => $lesson?->getKey(),
                'action_url' => $lesson
                    ? route('lms.front.instructor.lessons.edit', $lesson, false)
                    : route('lms.front.instructor.courses.edit', $course, false),
            ],
        ]);
    }

    /** @return list<int> */
    private function administratorIds(): array
    {
        return User::query()
            ->where(function ($query): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'super-admin'))
                    ->orWhereHas('permissions', fn ($permissions) => $permissions->whereIn('name', ['lms.instructors.manage', 'lms.settings.manage']))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->whereIn('name', ['lms.instructors.manage', 'lms.settings.manage']));
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
