<?php

namespace Modules\Lms\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountSectionProvider
{
    /** @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    public function append(array $sections, User $user): array
    {
        if (! Schema::hasTable('lms_course_enrollments') || ! Schema::hasTable('lms_courses')) {
            return $sections;
        }

        $enrollments = DB::table('lms_course_enrollments as enrollment')
            ->leftJoin('lms_courses as course', 'course.id', '=', 'enrollment.course_id')
            ->where('enrollment.user_id', $user->getKey())
            ->orderByDesc('enrollment.enrolled_at')
            ->orderByDesc('enrollment.id')
            ->get([
                'enrollment.id',
                'enrollment.enrolled_at',
                'course.title',
                'course.slug',
                'course.status',
            ]);

        $instructor = Schema::hasTable('lms_instructors')
            ? DB::table('lms_instructors')->where('user_id', $user->getKey())->whereNull('deleted_at')->first()
            : null;
        $requestStatus = Schema::hasTable('lms_instructor_requests')
            ? DB::table('lms_instructor_requests')->where('user_id', $user->getKey())->value('status')
            : null;
        $student = Schema::hasTable('lms_students')
            ? DB::table('lms_students')->where('user_id', $user->getKey())->first()
            : null;
        $studentActive = $student === null || (string) $student->status === 'active';

        $sections[] = [
            'key' => 'learning',
            'label' => __('lms::ui.account_my_learning'),
            'icon' => 'L',
            'order' => 20,
            'badge' => (string) $enrollments->count(),
            'view' => 'lms::account.learning',
            'data' => [
                'enrollments' => $enrollments,
                'portalUrl' => $studentActive ? route('lms.front.student.courses') : route('lms.front.courses'),
                'portalLabel' => $studentActive ? __('lms::ui.account_open_student') : __('lms::ui.browse-courses'),
                'studentActive' => $studentActive,
                'teachingUrl' => $instructor && $instructor->status === 'active'
                    ? route('lms.front.instructor.courses')
                    : route('lms.front.instructor-request.create'),
                'teachingLabel' => $instructor && $instructor->status === 'active'
                    ? __('lms::ui.account_open_teaching')
                    : ($requestStatus === 'pending' ? __('lms::ui.account_request_pending') : __('lms::ui.account_apply_teach')),
                'teachingDisabled' => $requestStatus === 'pending' && ! ($instructor && $instructor->status === 'active'),
            ],
        ];

        if ($instructor && $instructor->status === 'active' && $user->can('lms.instructor.portal')) {
            $courseCount = DB::table('lms_courses')->where('instructor_id', $instructor->id)->whereNull('deleted_at')->count();
            $lessonCount = Schema::hasTable('lms_lessons')
                ? DB::table('lms_lessons')
                    ->whereIn('course_id', DB::table('lms_courses')->select('id')->where('instructor_id', $instructor->id)->whereNull('deleted_at'))
                    ->whereNull('deleted_at')
                    ->count()
                : 0;

            $sections[] = [
                'key' => 'teaching',
                'label' => __('lms::ui.account_teaching'),
                'icon' => 'T',
                'order' => 30,
                'badge' => (string) $courseCount,
                'view' => 'lms::account.teaching',
                'data' => [
                    'courseCount' => $courseCount,
                    'lessonCount' => $lessonCount,
                    'dashboardUrl' => route('lms.front.instructor.courses'),
                    'createCourseUrl' => $user->can('lms.instructor.courses.manage') ? route('lms.front.instructor.courses.create') : null,
                    'createLessonUrl' => $user->can('lms.instructor.lessons.manage') ? route('lms.front.instructor.lessons.create') : null,
                ],
            ];
        }

        if (Schema::hasTable('lms_course_codes') && $user->can('lms.codes.generate')) {
            $courses = DB::table('lms_courses')
                ->whereNull('deleted_at')
                ->orderBy('title')
                ->get(['id', 'title']);

            $codes = DB::table('lms_course_codes as code')
                ->leftJoin('lms_courses as course', 'course.id', '=', 'code.course_id')
                ->where('code.created_by', $user->getKey())
                ->orderByDesc('code.id')
                ->limit(50)
                ->get(['code.code', 'code.status', 'code.created_at', 'course.title as course_title']);

            $codeCount = DB::table('lms_course_codes')->where('created_by', $user->getKey())->count();

            $sections[] = [
                'key' => 'codes',
                'label' => __('lms::ui.account_course_codes'),
                'icon' => 'C',
                'order' => 31,
                'badge' => (string) $codeCount,
                'view' => 'lms::account.codes',
                'data' => [
                    'courses' => $courses,
                    'codes' => $codes,
                    'codeCount' => $codeCount,
                    'generateUrl' => route('lms.front.codes.generate'),
                    'exportUrl' => route('lms.front.codes.export'),
                ],
            ];
        }

        return $sections;
    }
}
