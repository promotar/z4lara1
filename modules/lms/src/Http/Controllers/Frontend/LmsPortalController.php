<?php

namespace Modules\Lms\Http\Controllers\Frontend;

use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\CourseEnrollment;
use Modules\Lms\Models\CourseTranslation;
use Modules\Lms\Models\Instructor;
use Modules\Lms\Models\InstructorRequest;
use Modules\Lms\Models\Invitation;
use Modules\Lms\Models\Lesson;
use Modules\Lms\Models\LessonProgress;
use Modules\Lms\Models\LessonTranslation;
use Modules\Lms\Models\LmsNotification;
use Modules\Lms\Models\Student;
use Modules\Lms\Services\LmsNotificationService;
use Modules\Lms\Support\ResolvesPlatformMedia;
use Spatie\Permission\Models\Role;

class LmsPortalController extends Controller
{
    use ResolvesPlatformMedia;

    /**
     * Plugin-owned bridge to the platform Media Studio. This keeps the LMS UI on
     * the canonical gallery implementation without changing or duplicating core.
     */
    public function mediaIndex(Request $request)
    {
        $this->authorizeMediaStudio($request);

        $response = app(MediaController::class)->index($request);
        $payload = $response instanceof JsonResponse ? $response->getData(true) : null;
        $items = is_array($payload) ? ($payload['items'] ?? $payload['data'] ?? []) : [];

        if (is_array($items) && $items !== []) {
            return $response;
        }

        $fallback = $this->scanMediaDirectory((string) $request->query('type', 'image'));

        if ($fallback === []) {
            return $response;
        }

        return response()->json(array_merge(is_array($payload) ? $payload : [], ['items' => $fallback]));
    }

    /** @return array<int, array<string, mixed>> */
    private function scanMediaDirectory(string $type = 'image'): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists('media')) {
            return [];
        }

        $extensions = match ($type) {
            'video' => ['mp4', 'webm', 'mov', 'm4v'],
            'image' => ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'bmp'],
            default => ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'bmp', 'pdf', 'mp4', 'webm', 'mov', 'm4v', 'mp3', 'wav', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],
        };

        $items = [];

        foreach ($disk->allFiles('media') as $path) {
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

            if (! in_array($extension, $extensions, true)) {
                continue;
            }

            $items[] = [
                'name' => basename($path),
                'title' => basename($path),
                'path' => $path,
                'media_url' => route('lms.front.media.file', ['path' => $path], false),
                'image_url' => route('lms.front.media.file', ['path' => $path], false),
                'thumbnail_url' => null,
                'size' => number_format($disk->size($path) / 1024, 1).' KB',
                'modified_at' => date('Y-m-d H:i', $disk->lastModified($path)),
                'mime_type' => (string) ($disk->mimeType($path) ?: ''),
                'is_image' => in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'bmp'], true),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp((string) $b['modified_at'], (string) $a['modified_at']));

        return array_slice($items, 0, 300);
    }

    public function mediaStore(Request $request)
    {
        $this->authorizeMediaStudio($request);

        return app(MediaController::class)->store($request);
    }


    public function mediaDelete(Request $request)
    {
        $this->authorizeMediaStudio($request);

        return app(MediaController::class)->destroy($request);
    }



    public function uploadCourseImage(Request $request): JsonResponse
    {
        if (! $request->user()?->can('lms.settings.manage')) {
            $this->authorizeMediaStudio($request);
        }

        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $file = $request->file('image');
        $extension = strtolower((string) ($file?->getClientOriginalExtension() ?: 'jpg'));
        $path = $file->storeAs('media/courses', (string) Str::uuid().'.'.$extension, 'public');

        return response()->json([
            'ok' => true,
            'url' => route('lms.front.media.file', ['path' => $path], false),
            'name' => (string) $file->getClientOriginalName(),
        ]);
    }

    public function mediaFile(string $path)
    {
        $path = ltrim($path, '/');

        abort_if($path === '' || str_contains($path, '..'), 404);

        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if ($disk->exists($path)) {
            return $disk->response($path);
        }

        $publicFile = public_path($path);

        if (is_file($publicFile)) {
            return response()->file($publicFile);
        }

        $appFile = storage_path('app/'.$path);

        abort_unless(is_file($appFile), 404);

        return response()->file($appFile);
    }

    public function invitation(string $token): View
    {
        $invitation = $this->invitationFromToken($token);
        $existingAccount = $invitation->role === 'student'
            && User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->exists();

        return view('lms::frontend.invitation', compact('invitation', 'token', 'existingAccount'));
    }

    public function acceptInvitation(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitationFromToken($token);
        $payload = (array) $invitation->payload;
        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->first();

        if ($existingUser && $invitation->role === 'student') {
            DB::transaction(function () use ($existingUser, $invitation): void {
                Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
                if (! $existingUser->hasRole('student')) {
                    $existingUser->assignRole('student');
                }

                if ($invitation->course_id) {
                    CourseEnrollment::query()->firstOrCreate([
                        'course_id' => $invitation->course_id,
                        'user_id' => $existingUser->id,
                    ], ['source' => 'admin_invitation', 'enrolled_at' => now()]);
                }

                $courseIds = CourseEnrollment::query()
                    ->where('user_id', $existingUser->id)
                    ->pluck('course_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $student = Student::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($existingUser->email)])->first() ?: new Student;
                if ($student->trashed()) {
                    $student->restore();
                }
                $student->fill([
                    'user_id' => $existingUser->id,
                    'name' => $existingUser->name ?: $existingUser->email,
                    'email' => mb_strtolower($existingUser->email),
                    'status' => 'active',
                    'registered_at' => $student->registered_at ?: now(),
                    'last_active_at' => now(),
                    'enrolled_course_ids' => $courseIds,
                    'enrolled_courses_count' => count($courseIds),
                    'created_by' => $student->created_by ?: $invitation->invited_by,
                    'updated_by' => $invitation->invited_by,
                ])->save();

                $invitation->forceFill(['status' => 'accepted', 'accepted_at' => now(), 'last_error' => null])->save();
            });

            if (auth()->id() === $existingUser->id) {
                return redirect()->route('lms.front.student.overview')->with('status', __('lms::ui.invitation_accepted_available'));
            }

            return redirect()->route('login')->with('status', __('lms::ui.invitation_accepted_signin'));
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($existingUser) {
            return back()->withErrors(['email' => 'An account already exists for this email. Sign in or ask the administrator to resend the enrollment.']);
        }

        $user = DB::transaction(function () use ($invitation, $validated, $payload): User {
            $name = trim($validated['first_name'].' '.$validated['last_name']);
            $user = User::query()->create([
                'name' => $name,
                'email' => mb_strtolower($invitation->email),
                'password' => Hash::make($validated['password']),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            Role::firstOrCreate(['name' => $invitation->role, 'guard_name' => 'web']);
            $user->assignRole($invitation->role);

            if ($invitation->role === 'student') {
                Student::query()->updateOrCreate(['email' => $user->email], [
                    'user_id' => $user->id,
                    'name' => $name,
                    'status' => 'active',
                    'registered_at' => now(),
                    'last_active_at' => now(),
                    'enrolled_course_ids' => [$invitation->course_id],
                    'enrolled_courses_count' => $invitation->course_id ? 1 : 0,
                    'created_by' => $invitation->invited_by,
                    'updated_by' => $invitation->invited_by,
                ]);
                if ($invitation->course_id) {
                    CourseEnrollment::query()->firstOrCreate([
                        'course_id' => $invitation->course_id,
                        'user_id' => $user->id,
                    ], ['source' => 'admin_invitation', 'enrolled_at' => now()]);
                }
            } else {
                $instructor = Instructor::query()->findOrFail((int) ($payload['instructor_id'] ?? 0));
                $instructor->forceFill([
                    'user_id' => $user->id,
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $user->email,
                    'status' => 'active',
                    'invited_at' => $instructor->invited_at ?: now(),
                    'last_active_at' => now(),
                ])->save();
            }

            $invitation->forceFill(['status' => 'accepted', 'accepted_at' => now(), 'last_error' => null])->save();

            return $user;
        });

        auth()->login($user);

        return redirect()->route($invitation->role === 'student' ? 'lms.front.student.overview' : 'lms.front.instructor.courses')
            ->with('status', __('lms::ui.account_ready', ['brand' => \Modules\Lms\Support\PlatformBrand::name()]));
    }

    public function studentOverview(Request $request): View
    {
        $student = $this->studentFor($request);
        $summaries = $this->studentCourseSummaries($request->user()->id);
        $continue = $summaries->sortByDesc('last_activity')->first();

        return view('lms::frontend.student.overview', [
            'student' => $student,
            'courses' => $summaries->take(3),
            'totalCourses' => $summaries->count(),
            'continueCourse' => $continue,
            'completedCourses' => $summaries->where('status_key', 'completed')->count(),
            'inProgressCourses' => $summaries->where('status_key', 'in_progress')->count(),
        ]);
    }

    public function studentCourses(Request $request): View
    {
        $student = $this->studentFor($request);
        $allCourses = $this->studentCourseSummaries($request->user()->id);
        $status = (string) $request->query('status', 'all');
        $status = in_array($status, ['all', 'in-progress', 'completed'], true) ? $status : 'all';
        $search = trim((string) $request->query('search', ''));

        $courses = $allCourses;
        if ($status === 'in-progress') {
            $courses = $courses->where('status_key', 'in_progress');
        } elseif ($status === 'completed') {
            $courses = $courses->where('status_key', 'completed');
        }
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $courses = $courses->filter(fn ($summary) => str_contains(mb_strtolower($summary->course->title), $needle)
                || str_contains(mb_strtolower((string) ($summary->course->instructor?->name ?: $summary->course->instructor_name)), $needle));
        }

        return view('lms::frontend.student.courses', ['student' => $student, 'courses' => $courses->values(), 'status' => $status, 'search' => $search, 'hasEnrollments' => $allCourses->isNotEmpty()]);
    }

    public function studentSettings(Request $request): View
    {
        return view('lms::frontend.student.settings', ['student' => $this->studentFor($request)]);
    }

    public function updateStudentSettings(Request $request): RedirectResponse
    {
        $student = $this->studentFor($request);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($validated['first_name'].' '.$validated['last_name']);
        $student->forceFill(['name' => $name, 'last_active_at' => now()])->save();

        return back()->with('status', __('lms::ui.learning_profile_updated'));
    }

    public function instructorRequest(Request $request): View|RedirectResponse
    {
        $instructor = Instructor::query()->where('user_id', $request->user()->id)->first();
        if ($instructor && $instructor->status === 'active') {
            return redirect()->route('lms.front.instructor.courses');
        }

        return view('lms::frontend.instructor.request', [
            'application' => InstructorRequest::query()->where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function storeInstructorRequest(Request $request): RedirectResponse
    {
        abort_if(
            Instructor::query()->where('user_id', $request->user()->id)->where('status', 'active')->exists(),
            409,
            'Your instructor account is already active.',
        );

        $validated = $request->validate([
            'expertise' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:20', 'max:3000'],
        ]);

        $application = DB::transaction(function () use ($request, $validated): InstructorRequest {
            return InstructorRequest::query()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'status' => 'pending',
                    'expertise' => trim($validated['expertise']),
                    'message' => trim($validated['message']),
                    'requested_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'decision_note' => null,
                ],
            );
        });

        if ($application->wasRecentlyCreated || $application->wasChanged()) {
            app(LmsNotificationService::class)->notifyAdministratorsOfInstructorRequest($request->user());
        }

        return back()->with('status', __('lms::ui.instructor_request_sent'));
    }

    public function instructorCourses(Request $request): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.portal');
        $courses = $instructor->courses()
            ->withCount(['lessons', 'enrollments'])
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('lms::frontend.instructor.courses', compact('instructor', 'courses'));
    }

    public function createInstructorCourse(Request $request): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $locales = app(PlatformLocale::class)->languages();
        $locale = $this->editorLocale($locales, (string) $request->query('locale', ''));

        return view('lms::admin.courses.create', [
            'editorMode' => 'instructor',
            'instructor' => $instructor,
            'course' => null,
            'locale' => $locale,
            'locales' => $locales,
            'translation' => null,
            'baseCourse' => null,
            'formOptions' => $this->instructorCourseFormOptions($instructor),
            ...$this->instructorCourseEditorData($instructor, null, $request->user()),
        ]);
    }

    public function storeInstructorCourse(Request $request): RedirectResponse|JsonResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $this->normalizeCategoryInput($request);

        if ((string) $request->input('action') === 'autosave') {
            try {
                $course = $this->saveInstructorCourseDraft($request, $instructor);
            } catch (ValidationException $exception) {
                return response()->json([
                    'message' => $exception->errors()['title'][0] ?? __('lms::ui.course_title_required_draft'),
                ], 422);
            }

            return response()->json([
                'course_id' => $course->id,
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        $course = $this->instructorDraftCourseFromRequest($request, $instructor) ?? new Course;
        $this->saveInstructorCourse($request, $instructor, $course, (string) $request->input('action') === 'publish');
        app(LmsNotificationService::class)->notifyAdministratorsOfSubmission($request->user(), $course);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'course_id' => $course->id,
                'status' => $course->status,
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        $programTypeLabel = $course->program_type === 'diploma'
            ? __('lms::ui.admin_course_type_diploma')
            : __('lms::ui.admin_course_type_course');

        $redirectParams = ['course' => $course];
        $switchLocale = (string) $request->input('switch_locale', '');

        if ($switchLocale !== '' && array_key_exists($switchLocale, app(PlatformLocale::class)->languages())) {
            $redirectParams['locale'] = $switchLocale;
        }

        return redirect()->route('lms.front.instructor.courses.edit', $redirectParams)->with(
            'status',
            (string) $request->input('action') === 'save'
                ? __('lms::ui.course_draft_saved_continue')
                : ($course->status === 'published'
                    ? __('lms::ui.course_published_success', ['type' => $programTypeLabel])
                    : __('lms::ui.course_saved_approval', ['type' => $programTypeLabel])),
        );
    }

    public function editInstructorCourse(Request $request, Course $course): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $this->authorizeInstructorCourse($instructor, $course);
        $course->loadMissing('translations');
        $locales = app(PlatformLocale::class)->languages();
        $locale = $this->editorLocale($locales, (string) $request->query('locale', ''));
        $translation = null;
        $display = $course;

        if ($locale !== app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode()) {
            $translation = CourseTranslation::resolve($course, $locale);
            $display = clone $course;
            $display->title = $translation?->title ?? $course->title;
            $display->slug = $translation?->slug ?? $course->slug;
            $display->subtitle = $translation?->subtitle ?? $course->subtitle;
            $display->short_description = $translation?->short_description ?? $course->short_description;
            $display->description = $translation?->description ?? $course->description;
            $display->overview = $translation?->overview ?? $course->overview;
            $display->outcomes = $translation?->outcomes ?? $course->outcomes;
            $display->requirements = $translation?->requirements ?? $course->requirements;
            $display->target_audience = $translation?->target_audience ?? $course->target_audience;
            $display->faqs = $translation?->faqs ?? $course->faqs;
            $display->setRelation('translations', $course->translations);
        }

        return view('lms::admin.courses.create', [
            'editorMode' => 'instructor',
            'instructor' => $instructor,
            'course' => $display,
            'locale' => $locale,
            'locales' => $locales,
            'translation' => $translation,
            'baseCourse' => $course,
            'formOptions' => $this->instructorCourseFormOptions($instructor),
            ...$this->instructorCourseEditorData($instructor, $display, $request->user()),
        ]);
    }

    public function updateInstructorCourse(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $this->authorizeInstructorCourse($instructor, $course);
        $this->normalizeCategoryInput($request);

        $locale = (string) $request->input('locale', app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode());

        if ($locale !== app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode()
            && array_key_exists($locale, app(PlatformLocale::class)->languages())
        ) {
            $this->saveInstructorCourseTranslation($request, $course, $locale);

            return redirect()
                ->route('lms.front.instructor.courses.edit', ['course' => $course, 'locale' => $locale])
                ->with('status', __('core.translate.saved'));
        }

        $this->saveInstructorCourse($request, $instructor, $course, (string) $request->input('action') === 'publish' || $course->status === 'published');

        if ($course->review_status === 'pending') {
            app(LmsNotificationService::class)->notifyAdministratorsOfSubmission($request->user(), $course);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'course_id' => $course->id,
                'status' => $course->status,
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        return redirect()->route('lms.front.instructor.courses.edit', $course)->with(
            'status',
            $course->review_status === 'pending'
                ? __('lms::ui.course_changes_approval')
                : __('lms::ui.course_changes_saved'),
        );
    }

    private function saveInstructorCourseTranslation(Request $request, Course $course, string $locale): void
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string', 'max:600'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'outcomes' => ['nullable', 'array'],
            'outcomes.*.title' => ['nullable', 'string', 'max:255'],
            'outcomes.*.text' => ['nullable', 'string', 'max:1000'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['nullable', 'string', 'max:255'],
            'target_audience' => ['nullable', 'array'],
            'target_audience.*' => ['nullable', 'string', 'max:255'],
            'faqs' => ['nullable', 'array'],
            'faqs.*.question' => ['nullable', 'string', 'max:255'],
            'faqs.*.answer' => ['nullable', 'string', 'max:1000'],
        ]);

        $translation = CourseTranslation::query()->firstOrNew([
            'course_id' => $course->id,
            'locale' => $locale,
        ]);
        $slugSource = trim((string) ($validated['slug'] ?? '')) ?: $validated['title'];

        $translation->fill([
            'title' => $validated['title'],
            'slug' => CourseTranslation::uniqueSlug($slugSource, $locale, $translation->id),
            'subtitle' => $validated['subtitle'] ?? null,
            'overview' => $validated['overview'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'outcomes' => $this->cleanLearnItems($validated['outcomes'] ?? []),
            'requirements' => $this->cleanStringList($validated['requirements'] ?? []),
            'target_audience' => $this->cleanStringList($validated['target_audience'] ?? []),
            'faqs' => $this->cleanFaqs($validated['faqs'] ?? []),
            'updated_by' => $request->user()->id,
        ]);

        if (! $translation->exists) {
            $translation->created_by = $request->user()->id;
        }

        $translation->save();
    }

    private function saveInstructorLessonTranslation(Request $request, Lesson $lesson, string $locale): void
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
        ]);

        $translation = LessonTranslation::query()->firstOrNew([
            'lesson_id' => $lesson->id,
            'locale' => $locale,
        ]);
        $slugSource = trim((string) ($validated['slug'] ?? '')) ?: $validated['title'];

        $translation->fill([
            'title' => $validated['title'],
            'slug' => LessonTranslation::uniqueSlug($slugSource, $locale, $translation->id),
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        if (! $translation->exists) {
            $translation->created_by = $request->user()->id;
        }

        $translation->save();
    }

    public function updateInstructorCourseCurriculum(Request $request, Course $course): JsonResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $this->authorizeInstructorCourse($instructor, $course);

        $validated = $request->validate([
            'units' => ['present', 'array', 'max:250'],
            'units.*.key' => ['required', 'string', 'max:80'],
            'units.*.title' => ['required', 'string', 'max:160'],
            'units.*.previous_title' => ['nullable', 'string', 'max:160'],
            'units.*.order' => ['required', 'integer', 'min:1', 'max:10000'],
            'lessons' => ['sometimes', 'array', 'max:2000'],
            'lessons.*.id' => ['required', 'integer', 'distinct'],
            'lessons.*.module_title' => ['required', 'string', 'max:160'],
            'lessons.*.module_order' => ['required', 'integer', 'min:1', 'max:10000'],
            'lessons.*.lesson_order' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $units = collect($validated['units'])
            ->map(fn (array $unit): array => [
                'key' => trim($unit['key']),
                'title' => trim($unit['title']),
                'order' => (int) $unit['order'],
            ])
            ->sortBy('order')
            ->values()
            ->map(fn (array $unit, int $index): array => array_merge($unit, ['order' => $index + 1]));

        $lessonPayload = collect($validated['lessons'] ?? []);
        $lessons = $units
            ->flatMap(function (array $unit) use ($lessonPayload) {
                return $lessonPayload
                    ->where('module_title', $unit['title'])
                    ->sortBy('lesson_order')
                    ->values()
                    ->map(fn (array $lesson, int $index): array => [
                        'id' => (int) $lesson['id'],
                        'module_title' => $unit['title'],
                        'module_order' => (int) $unit['order'],
                        'lesson_order' => $index + 1,
                    ]);
            })
            ->values();

        if (array_key_exists('lessons', $validated)) {
            $submittedIds = $lessonPayload->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values();
            $orderedIds = $lessons->pluck('id')->sort()->values();
            $courseLessonIds = $course->lessons()->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values();

            if ($submittedIds->all() !== $orderedIds->all()
                || $submittedIds->all() !== $courseLessonIds->all()) {
                throw ValidationException::withMessages([
                    'lessons' => 'The lesson order must include every lesson in this course exactly once.',
                ]);
            }
        }

        DB::transaction(function () use ($course, $units, $lessons, $validated, $request): void {
            foreach ($validated['units'] as $unit) {
                $previousTitle = trim((string) ($unit['previous_title'] ?? ''));
                $title = trim((string) $unit['title']);

                if ($previousTitle !== '' && $previousTitle !== $title) {
                    Lesson::query()
                        ->where('course_id', $course->id)
                        ->where('module_title', $previousTitle)
                        ->update(['module_title' => $title, 'updated_by' => $request->user()->id]);
                }

                Lesson::query()
                    ->where('course_id', $course->id)
                    ->where('module_title', $title)
                    ->update(['module_order' => (int) $unit['order'], 'updated_by' => $request->user()->id]);
            }

            foreach ($lessons as $lessonOrder) {
                Lesson::query()
                    ->where('course_id', $course->id)
                    ->whereKey($lessonOrder['id'])
                    ->update([
                        'module_title' => $lessonOrder['module_title'],
                        'module_order' => $lessonOrder['module_order'],
                        'lesson_order' => $lessonOrder['lesson_order'],
                        'updated_by' => $request->user()->id,
                    ]);
            }

            $course->forceFill([
                'curriculum_units' => $units->all(),
                'updated_by' => $request->user()->id,
            ])->save();
        });

        return response()->json([
            'ok' => true,
            'units' => $units->all(),
            'lessons' => $lessons->all(),
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    public function instructorLessons(Request $request): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $courseIds = $instructor->courses()->pluck('id');
        $query = Lesson::query()->with('course')->whereIn('course_id', $courseIds);
        if ($search = trim((string) $request->query('search'))) {
            $query->where('title', 'like', "%{$search}%");
        }
        if ($courseId = $request->integer('course_id')) {
            abort_unless($courseIds->contains($courseId), 403);
            $query->where('course_id', $courseId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return view('lms::frontend.instructor.lessons', [
            'instructor' => $instructor,
            'lessons' => $query->latest()->paginate(12)->withQueryString(),
            'courses' => $instructor->courses()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function createInstructorLesson(Request $request): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $courses = $instructor->courses()->orderBy('title')->get(['id', 'title']);
        $defaultCourseId = $request->integer('course_id');
        if ($defaultCourseId > 0) {
            abort_unless($courses->contains('id', $defaultCourseId), 403);
        }
        $locales = app(PlatformLocale::class)->languages();
        $locale = $this->editorLocale($locales, (string) $request->query('locale', ''));

        return view('lms::admin.lessons.create', [
            'editorMode' => 'instructor',
            'instructor' => $instructor,
            'lesson' => null,
            'courses' => $courses,
            'authors' => User::query()->whereKey($request->user()->id)->get(['id', 'name']),
            'formOptions' => $this->instructorLessonFormOptions($courses->pluck('id')),
            'defaultCourseId' => $defaultCourseId ?: null,
            'locale' => $locale,
            'locales' => $locales,
            'translation' => null,
            'baseLesson' => null,
        ]);
    }

    public function storeInstructorLesson(Request $request): RedirectResponse|JsonResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $this->prepareInstructorLessonContentType($request);

        if ((string) $request->input('action') === 'save_draft') {
            $lesson = $this->saveInstructorLessonDraft($request, $instructor);
            $redirectParams = ['lesson' => $lesson];
            $switchLocale = (string) $request->input('switch_locale', '');

            if ($switchLocale !== '' && array_key_exists($switchLocale, app(PlatformLocale::class)->languages())) {
                $redirectParams['locale'] = $switchLocale;
            }

            return redirect()->route('lms.front.instructor.lessons.edit', $redirectParams)
                ->with('status', __('lms::ui.lesson_draft_saved'));
        }

        $lesson = new Lesson;
        $this->saveInstructorLesson($request, $instructor, $lesson);
        app(LmsNotificationService::class)->notifyAdministratorsOfSubmission($request->user(), $lesson->course()->firstOrFail(), $lesson);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'lesson' => $this->instructorLessonEditorPayload($lesson->fresh(['author'])),
            ]);
        }

        return redirect()->route('lms.front.instructor.lessons')->with(
            'status',
            $lesson->status === 'published' ? __('lms::ui.lesson_published') : __('lms::ui.lesson_saved_approval'),
        );
    }

    public function editInstructorLesson(Request $request, Lesson $lesson): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $this->authorizeInstructorLesson($instructor, $lesson);
        $lesson->loadMissing('translations');
        $courses = $instructor->courses()->orderBy('title')->get(['id', 'title']);
        $locales = app(PlatformLocale::class)->languages();
        $locale = $this->editorLocale($locales, (string) $request->query('locale', ''));
        $translation = null;
        $display = $lesson;

        if ($locale !== app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode()) {
            $translation = LessonTranslation::resolve($lesson, $locale);
            $display = clone $lesson;
            $display->title = $translation?->title ?? $lesson->title;
            $display->slug = $translation?->slug ?? $lesson->slug;
            $display->description = $translation?->description ?? $lesson->description;
            $display->content = $translation?->content ?? $lesson->content;
            $display->setRelation('translations', $lesson->translations);
        }

        return view('lms::admin.lessons.create', [
            'editorMode' => 'instructor',
            'instructor' => $instructor,
            'lesson' => $display,
            'courses' => $courses,
            'authors' => User::query()->whereKey($request->user()->id)->get(['id', 'name']),
            'formOptions' => $this->instructorLessonFormOptions($courses->pluck('id')),
            'locale' => $locale,
            'locales' => $locales,
            'translation' => $translation,
            'baseLesson' => $lesson,
        ]);
    }

    public function updateInstructorLesson(Request $request, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $this->authorizeInstructorLesson($instructor, $lesson);
        $this->prepareInstructorLessonContentType($request);

        $locale = (string) $request->input('locale', app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode());

        if ($locale !== app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode()
            && array_key_exists($locale, app(PlatformLocale::class)->languages())
        ) {
            $this->saveInstructorLessonTranslation($request, $lesson, $locale);

            return redirect()
                ->route('lms.front.instructor.lessons.edit', ['lesson' => $lesson, 'locale' => $locale])
                ->with('status', __('lms::ui.admin_lesson_translation_saved'));
        }

        if ((string) $request->input('action') === 'save_draft') {
            $this->saveInstructorLessonDraft($request, $instructor, $lesson);

            return redirect()->route('lms.front.instructor.lessons.edit', $lesson)
                ->with('status', __('lms::ui.lesson_draft_saved'));
        }

        $this->saveInstructorLesson($request, $instructor, $lesson);

        if ($lesson->review_status === 'pending') {
            app(LmsNotificationService::class)->notifyAdministratorsOfSubmission($request->user(), $lesson->course()->firstOrFail(), $lesson);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'lesson' => $this->instructorLessonEditorPayload($lesson->fresh(['author'])),
            ]);
        }

        return redirect()->route('lms.front.instructor.lessons.edit', $lesson)->with('status', __('lms::ui.lesson_updated'));
    }

    public function deleteInstructorLesson(Request $request, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $this->authorizeInstructorLesson($instructor, $lesson);
        $lesson->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('lms.front.instructor.lessons')->with('status', __('lms::ui.lesson_deleted'));
    }

    public function instructorCourseTrash(Request $request): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $courses = Course::onlyTrashed()
            ->where('instructor_id', $instructor->id)
            ->withCount(['lessons', 'enrollments'])
            ->latest('deleted_at')
            ->paginate(12)
            ->withQueryString();

        return view('lms::frontend.instructor.courses-trash', compact('instructor', 'courses'));
    }

    public function deleteInstructorCourse(Request $request, string $course): RedirectResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $model = Course::where('instructor_id', $instructor->id)->findOrFail($course);
        $model->delete();

        return redirect()->route('lms.front.instructor.courses')->with('status', __('lms::ui.course_moved_trash'));
    }

    public function restoreInstructorCourse(Request $request, string $course): RedirectResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $model = Course::onlyTrashed()->where('instructor_id', $instructor->id)->findOrFail($course);
        $model->restore();

        return redirect()->route('lms.front.instructor.courses.trash')->with('status', __('lms::ui.course_restored'));
    }

    public function forceInstructorCourse(Request $request, string $course): RedirectResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.courses.manage');
        $model = Course::onlyTrashed()->where('instructor_id', $instructor->id)->findOrFail($course);
        $model->forceDelete();

        return redirect()->route('lms.front.instructor.courses.trash')->with('status', __('lms::ui.course_perm_deleted'));
    }

    public function instructorLessonTrash(Request $request): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $courseIds = $instructor->courses()->pluck('id');
        $lessons = Lesson::onlyTrashed()
            ->with('course')
            ->whereIn('course_id', $courseIds)
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('lms::frontend.instructor.lessons-trash', compact('instructor', 'lessons'));
    }

    public function restoreInstructorLesson(Request $request, string $lesson): RedirectResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $model = Lesson::onlyTrashed()->findOrFail($lesson);
        $this->authorizeInstructorLesson($instructor, $model);
        $model->restore();

        return redirect()->route('lms.front.instructor.lessons.trash')->with('status', __('lms::ui.lesson_restored'));
    }

    public function forceInstructorLesson(Request $request, string $lesson): RedirectResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.lessons.manage');
        $model = Lesson::onlyTrashed()->findOrFail($lesson);
        $this->authorizeInstructorLesson($instructor, $model);
        $model->forceDelete();

        return redirect()->route('lms.front.instructor.lessons.trash')->with('status', __('lms::ui.lesson_perm_deleted'));
    }

    public function instructorStudents(Request $request): View
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.portal');
        $courses = $instructor->courses()->orderBy('title')->get(['id', 'title']);
        $courseIds = $courses->pluck('id');
        $query = CourseEnrollment::query()->with(['course', 'student', 'user'])->whereIn('course_id', $courseIds);
        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('user', fn ($builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($courseId = $request->integer('course_id')) {
            abort_unless($courseIds->contains($courseId), 403);
            $query->where('course_id', $courseId);
        }
        $enrollments = $query->latest('enrolled_at')->paginate(15)->withQueryString();
        foreach ($enrollments as $enrollment) {
            $enrollment->setAttribute('progress_percent', $this->courseProgress($enrollment->user_id, $enrollment->course));
            $enrollment->setAttribute('last_activity', LessonProgress::query()->where('user_id', $enrollment->user_id)->where('course_id', $enrollment->course_id)->max('last_seen_at'));
        }

        return view('lms::frontend.instructor.students', compact('instructor', 'courses', 'enrollments'));
    }

    public function instructorSettings(Request $request): View
    {
        return view('lms::frontend.instructor.settings', ['instructor' => $this->instructorFor($request, 'lms.instructor.portal')]);
    }

    public function updateInstructorSettings(Request $request): RedirectResponse
    {
        $instructor = $this->instructorFor($request, 'lms.instructor.portal');
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'avatar_media_url' => ['nullable', 'string', 'max:2048'],
            'avatar_media_name' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $validated, $instructor): void {
            $instructor->forceFill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
                'last_active_at' => now(),
            ]);
            $this->storeAvatar($request, $instructor, 'lms/instructors');
            $instructor->save();
            Course::query()->where('instructor_id', $instructor->id)->update(['instructor_name' => $instructor->name]);
        });

        return back()->with('status', __('lms::ui.instructor_profile_updated'));
    }

    private function invitationFromToken(string $token): Invitation
    {
        $invitation = Invitation::query()->with('course')->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($invitation->isUsable(), 410, __('lms::ui.invitation_expired'));

        return $invitation;
    }

    private function studentFor(Request $request): Student
    {
        $student = Student::query()->where('user_id', $request->user()->id)->first();
        if (! $student) {
            $ids = CourseEnrollment::query()->where('user_id', $request->user()->id)->pluck('course_id')->map(fn ($id) => (int) $id)->all();
            $student = Student::query()->updateOrCreate(['email' => mb_strtolower($request->user()->email)], [
                'user_id' => $request->user()->id,
                'name' => $request->user()->name,
                'status' => 'active',
                'registered_at' => now(),
                'last_active_at' => now(),
                'enrolled_course_ids' => $ids,
                'enrolled_courses_count' => count($ids),
            ]);
        }
        abort_unless($student && $student->status === 'active', 403, 'Your student account is not active.');
        $student->forceFill(['last_active_at' => now()])->save();

        return $student;
    }

    public function generateCourseCodes(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('lms.codes.generate'), 403);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('lms_courses', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);
        $quantity = (int) $validated['quantity'];
        $batchId = 'batch-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $created = 0;

        DB::transaction(function () use ($course, $quantity, $batchId, $request, &$created): void {
            while ($created < $quantity) {
                $code = 'C'.$course->id.'-'.Str::upper(Str::random(8));

                if (\Modules\Lms\Models\CourseCode::query()->where('code', $code)->exists()) {
                    continue;
                }

                \Modules\Lms\Models\CourseCode::query()->create([
                    'course_id' => $course->id,
                    'code' => $code,
                    'batch_id' => $batchId,
                    'status' => 'available',
                    'created_by' => $request->user()->id,
                ]);

                $created++;
            }
        });

        return redirect(url('/account').'?section=codes')
            ->with('status', 'تم توليد '.$created.' كوداً للدورة '.$course->title.'.');
    }

    public function exportCourseCodes(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($request->user()?->can('lms.codes.generate'), 403);

        $userId = (int) $request->user()->id;
        $courseId = (int) $request->integer('course_id');

        $query = \Modules\Lms\Models\CourseCode::query()
            ->with('course')
            ->where('created_by', $userId)
            ->orderByDesc('id');

        if ($courseId > 0) {
            $query->where('course_id', $courseId);
        }

        $filename = 'my-course-codes-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['code', 'course', 'batch', 'status', 'used_at', 'created_at']);

            $query->chunk(500, function ($codes) use ($out): void {
                foreach ($codes as $code) {
                    fputcsv($out, [
                        (string) $code->code,
                        (string) ($code->course?->title ?? ''),
                        (string) ($code->batch_id ?? ''),
                        (string) $code->status,
                        $code->used_at?->format('Y-m-d H:i') ?? '',
                        $code->created_at?->format('Y-m-d H:i') ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = LmsNotification::query()
            ->forUser($request->user())
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (LmsNotification $notification): array => [
                'id' => $notification->getKey(),
                'title' => $notification->title,
                'body' => $notification->body,
                'action_url' => (string) data_get($notification->data, 'action_url', ''),
                'is_read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->diffForHumans(),
                'read_url' => route('lms.front.notifications.read', $notification, false),
            ]);

        return response()->json([
            'data' => $notifications,
            'unread_count' => LmsNotification::query()->forUser($request->user())->whereNull('read_at')->count(),
            'read_all_url' => route('lms.front.notifications.read-all', [], false),
        ]);
    }

    public function readNotification(Request $request, LmsNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->recipient_id === (int) $request->user()->id, 404);
        $notification->forceFill(['read_at' => $notification->read_at ?: now()])->save();

        return response()->json(['ok' => true]);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        LmsNotification::query()->forUser($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function instructorFor(Request $request, ?string $permission = null): Instructor
    {
        $instructor = Instructor::query()->where('user_id', $request->user()->id)->first();
        abort_unless($instructor && $instructor->status === 'active', 403, 'Your instructor account is not active.');
        abort_unless($request->user()->can('lms.instructor.portal'), 403, 'Your instructor portal permission is not active.');
        if ($permission && $permission !== 'lms.instructor.portal') {
            abort_unless($request->user()->can($permission), 403, 'You do not have permission to perform this instructor action.');
        }
        $instructor->forceFill(['last_active_at' => now()])->save();

        return $instructor;
    }

    private function saveInstructorCourse(Request $request, Instructor $instructor, Course $course, bool $requireComplete = true): void
    {
        // Newlines arrive as CRLF from the browser and would be counted twice by max: rules.
        $newlineOutcomes = (array) $request->input('outcomes', []);

        foreach ($newlineOutcomes as $newlineIndex => $newlineItem) {
            if (is_array($newlineItem) && array_key_exists('text', $newlineItem)) {
                $newlineOutcomes[$newlineIndex]['text'] = str_replace(["\r\n", "\r"], "\n", (string) $newlineItem['text']);
            }
        }

        $request->merge(['outcomes' => $newlineOutcomes]);
        // Newlines arrive as CRLF from the browser and would be counted twice by max: rules.
        foreach (['short_description', 'title', 'subtitle'] as $newlineField) {
            if ($request->has($newlineField)) {
                $request->merge([$newlineField => str_replace(["\r\n", "\r"], "\n", (string) $request->input($newlineField))]);
            }
        }

        $validated = $request->validate([
            'program_type' => ['required', Rule::in(['course', 'diploma'])],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('lms_courses', 'slug')->ignore($course->id)],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string', 'max:600'],
            'partners_text' => ['nullable', 'string', 'max:4000'],
            'short_description' => [$requireComplete ? 'required' : 'nullable', 'string', 'max:500'],
            'description' => [$requireComplete ? 'required' : 'nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'published'])],
            'publish_date' => ['nullable', 'date'],
            'featured_image' => ['nullable', 'image', 'max:8192'],
            'featured_image_media_url' => ['nullable', 'string', 'max:2048'],
            'featured_image_media_name' => ['nullable', 'string', 'max:255'],
            'promo_video_url' => ['nullable', 'url', 'max:2048'],
            'promo_video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:51200'],
            'category' => [$requireComplete ? 'required' : 'nullable', 'string', 'max:120'],
            'category_new' => ['nullable', 'string', 'max:120'],
            'training_mode' => ['nullable', Rule::in(['online', 'in_person', 'both'])],
            'online_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'in_person_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'level' => [$requireComplete ? 'required' : 'nullable', 'string', 'max:80'],
            'language' => [$requireComplete ? 'required' : 'nullable', 'string', 'max:80'],
            'visibility' => [$requireComplete ? 'required' : 'nullable', Rule::in(['public', 'private', 'unlisted'])],
            'instructor_id' => [$requireComplete ? 'required' : 'nullable', 'integer', Rule::in([(int) $instructor->id])],
            'pricing_type' => [$requireComplete ? 'required' : 'nullable', Rule::in(['free', 'paid'])],
            'regular_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'enrollment_limit_type' => [$requireComplete ? 'required' : 'nullable', Rule::in(['unlimited', 'limited'])],
            'enrollment_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'enrollment_type' => [$requireComplete ? 'required' : 'nullable', 'string', 'max:80'],
            'access_duration' => [$requireComplete ? 'required' : 'nullable', 'string', 'max:80'],
            'course_progress' => [$requireComplete ? 'required' : 'nullable', 'string', 'max:80'],
            'outcomes' => ['nullable', 'array', 'max:40'],
            'outcomes.*.title' => ['nullable', 'string', 'max:255'],
            'outcomes.*.text' => ['nullable', 'string', 'max:1000'],
            'requirements' => ['nullable', 'array', 'max:40'],
            'requirements.*' => ['nullable', 'string', 'max:255'],
            'target_audience' => ['nullable', 'array', 'max:40'],
            'target_audience.*' => ['nullable', 'string', 'max:255'],
            'faqs' => ['nullable', 'array', 'max:30'],
            'faqs.*.question' => ['nullable', 'string', 'max:255'],
            'faqs.*.answer' => ['nullable', 'string', 'max:1500'],
        ]);

        $requestedStatus = (string) $request->input('action') === 'publish'
            ? 'published'
            : (string) ($validated['status'] ?? $course->status ?? 'draft');
        $canPublish = $instructor->canPublishDirectly();
        $status = $canPublish && $requestedStatus === 'published' ? 'published' : 'draft';
        $submittedForReview = ! $canPublish && $requestedStatus === 'published';
        $reviewStatus = $canPublish
            ? ($status === 'published' ? 'approved' : 'none')
            : ($submittedForReview || $course->review_status === 'pending' ? 'pending' : 'none');

        $trainingMode = in_array($validated['training_mode'] ?? null, ['online', 'in_person', 'both'], true)
            ? (string) $validated['training_mode']
            : 'online';
        $onlinePriceValue = isset($validated['online_price']) && $validated['online_price'] !== null ? (float) $validated['online_price'] : null;
        $inPersonPriceValue = isset($validated['in_person_price']) && $validated['in_person_price'] !== null ? (float) $validated['in_person_price'] : null;
        $basePriceValue = isset($validated['regular_price']) && $validated['regular_price'] !== null ? (float) $validated['regular_price'] : null;
        $priceCandidates = match ($trainingMode) {
            'in_person' => [$inPersonPriceValue],
            'both' => [$onlinePriceValue, $inPersonPriceValue],
            default => [$onlinePriceValue],
        };
        $priceCandidates = array_values(array_filter($priceCandidates, fn ($value) => $value !== null && $value > 0));
        $effectivePrice = $priceCandidates === [] ? null : min($priceCandidates);
        $effectivePricingType = $effectivePrice !== null ? 'paid' : 'free';

        $course->fill([
            'program_type' => $validated['program_type'],
            'title' => $validated['title'],
            'slug' => $this->uniqueCourseSlug((string) ($validated['slug'] ?? ''), $validated['title'], $course),
            'subtitle' => $validated['subtitle'] ?? null,
            'overview' => $validated['overview'] ?? null,
            'partners' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($validated['partners_text'] ?? '')) ?: []))),
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'outcomes' => $this->cleanLearnItems($validated['outcomes'] ?? []),
            'requirements' => $this->cleanStringList($validated['requirements'] ?? []),
            'target_audience' => $this->cleanStringList($validated['target_audience'] ?? []),
            'faqs' => $this->cleanFaqs($validated['faqs'] ?? []),
            'status' => $status,
            'review_status' => $reviewStatus,
            'publish_date' => $validated['publish_date'] ?? null,
            'published_at' => $status === 'published' ? ($course->published_at ?: now()) : null,
            'visibility' => $validated['visibility'] ?? ($course->visibility ?: 'public'),
            'promo_video_url' => $validated['promo_video_url'] ?? null,
            'category' => $validated['category'] ?? ($course->category ?: null),
            'training_mode' => $trainingMode,
            'online_price' => $trainingMode === 'both' ? $onlinePriceValue : null,
            'in_person_price' => $trainingMode !== 'online' ? $inPersonPriceValue : null,
            'level' => $validated['level'] ?? ($course->level ?: 'Beginner'),
            'language' => $validated['language'] ?? ($course->language ?: 'English'),
            'instructor_id' => $instructor->getKey(),
            'instructor_name' => $instructor->name,
            'instructor_title' => null,
            'pricing_type' => $effectivePricingType,
            'regular_price' => $effectivePricingType === 'paid' ? $effectivePrice : null,
            'sale_price' => $effectivePricingType === 'paid' ? ($validated['sale_price'] ?? null) : null,
            'currency' => strtoupper($validated['currency'] ?? 'USD'),
            'enrollment_limit_type' => $validated['enrollment_limit_type'] ?? ($course->enrollment_limit_type ?: 'unlimited'),
            'enrollment_limit' => ($validated['enrollment_limit_type'] ?? null) === 'limited' ? ($validated['enrollment_limit'] ?? null) : null,
            'enrollment_type' => $validated['enrollment_type'] ?? ($course->enrollment_type ?: 'open'),
            'access_duration' => $validated['access_duration'] ?? ($course->access_duration ?: 'lifetime'),
            'course_progress' => $validated['course_progress'] ?? ($course->course_progress ?: 'complete_previous'),
            'created_by' => $course->exists ? $course->created_by : $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if ($request->hasFile('featured_image')) {
            $file = $request->file('featured_image');
            $course->featured_image_path = $file->store('lms/course-images', 'public');
            $course->featured_image_name = $file->getClientOriginalName();
            $course->featured_image_meta = ['size' => $file->getSize(), 'mime' => $file->getMimeType(), 'source' => 'upload'];
        } elseif (! empty($validated['featured_image_media_url']) && ($path = $this->platformMediaPathFromUrl($validated['featured_image_media_url']))) {
            $course->featured_image_path = $path;
            $course->featured_image_name = trim((string) ($validated['featured_image_media_name'] ?? '')) ?: basename($path);
            $course->featured_image_meta = ['source' => 'media_library', 'url' => Storage::disk('public')->url($path)];
        }

        if ($request->hasFile('promo_video')) {
            $course->promo_video_path = $request->file('promo_video')->store('lms/course-videos', 'public');
        }

        $course->save();
    }

    private function normalizeCategoryInput(Request $request): void
    {
        $newCategory = trim((string) $request->input('category_new', ''));

        if ($newCategory !== '') {
            $request->merge(['category' => $newCategory]);
        }
    }

    private function saveInstructorCourseDraft(Request $request, Instructor $instructor): Course
    {
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => __('lms::ui.course_title_required_draft')]);
        }

        $course = $this->instructorDraftCourseFromRequest($request, $instructor) ?? new Course;
        $pricingType = in_array($request->input('pricing_type'), ['free', 'paid'], true)
            ? (string) $request->input('pricing_type')
            : ($course->pricing_type ?: 'free');
        $limitType = in_array($request->input('enrollment_limit_type'), ['unlimited', 'limited'], true)
            ? (string) $request->input('enrollment_limit_type')
            : ($course->enrollment_limit_type ?: 'unlimited');
        $programType = in_array($request->input('program_type'), ['course', 'diploma'], true)
            ? (string) $request->input('program_type')
            : ($course->program_type ?: 'course');

        $course->fill([
            'program_type' => $programType,
            'title' => $title,
            'slug' => $this->uniqueCourseSlug((string) $request->input('slug', ''), $title, $course),
            'subtitle' => $this->nullableInstructorValue($request->input('subtitle')),
            'overview' => $this->nullableInstructorValue($request->input('overview')),
            'partners' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $request->input('partners_text', '')) ?: []))),
            'short_description' => $this->nullableInstructorValue($request->input('short_description')),
            'description' => $this->nullableInstructorValue($request->input('description')),
            'outcomes' => $this->cleanLearnItems((array) $request->input('outcomes', [])),
            'requirements' => $this->cleanStringList((array) $request->input('requirements', [])),
            'target_audience' => $this->cleanStringList((array) $request->input('target_audience', [])),
            'faqs' => $this->cleanFaqs((array) $request->input('faqs', [])),
            'status' => 'draft',
            'review_status' => 'none',
            'publish_date' => $request->input('publish_date') ?: null,
            'published_at' => null,
            'visibility' => $this->nullableInstructorValue($request->input('visibility')) ?: ($course->visibility ?: 'public'),
            'promo_video_url' => $this->nullableInstructorValue($request->input('promo_video_url')),
            'category' => $this->nullableInstructorValue($request->input('category')),
            'training_mode' => in_array($request->input('training_mode'), ['online', 'in_person', 'both'], true) ? (string) $request->input('training_mode') : ($course->training_mode ?: 'online'),
            'online_price' => $request->filled('online_price') ? $request->input('online_price') : null,
            'in_person_price' => $request->filled('in_person_price') ? $request->input('in_person_price') : null,
            'level' => $this->nullableInstructorValue($request->input('level')),
            'language' => $this->nullableInstructorValue($request->input('language')),
            'instructor_id' => $instructor->id,
            'instructor_name' => $instructor->name,
            'instructor_title' => null,
            'pricing_type' => $pricingType,
            'regular_price' => $pricingType === 'paid' && $request->filled('regular_price') ? $request->input('regular_price') : null,
            'sale_price' => $pricingType === 'paid' && $request->filled('sale_price') ? $request->input('sale_price') : null,
            'currency' => strtoupper($this->nullableInstructorValue($request->input('currency')) ?: ($course->currency ?: 'USD')),
            'enrollment_limit_type' => $limitType,
            'enrollment_limit' => $limitType === 'limited' && $request->filled('enrollment_limit') ? $request->integer('enrollment_limit') : null,
            'enrollment_type' => $this->nullableInstructorValue($request->input('enrollment_type')) ?: ($course->enrollment_type ?: 'open'),
            'access_duration' => $this->nullableInstructorValue($request->input('access_duration')) ?: ($course->access_duration ?: 'lifetime'),
            'course_progress' => $this->nullableInstructorValue($request->input('course_progress')) ?: ($course->course_progress ?: 'complete_previous'),
            'created_by' => $course->exists ? $course->created_by : $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $course->save();

        return $course;
    }

    private function instructorDraftCourseFromRequest(Request $request, Instructor $instructor): ?Course
    {
        $draftId = $request->integer('draft_course_id');
        if ($draftId < 1) {
            return null;
        }

        return Course::query()
            ->whereKey($draftId)
            ->where('instructor_id', $instructor->id)
            ->where('status', 'draft')
            ->first();
    }

    private function nullableInstructorValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function editorLocale(array $locales, string $requested = ''): string
    {
        $current = (string) app()->getLocale();
        $default = array_key_exists($current, $locales) ? $current : app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode();
        $locale = $requested !== '' ? $requested : $default;

        return array_key_exists($locale, $locales) ? $locale : $default;
    }

    private function authorizeInstructorCourse(Instructor $instructor, Course $course): void
    {
        abort_unless((int) $course->instructor_id === (int) $instructor->id, 403);
    }

    private function studentCourseSummaries(int $userId)
    {
        return CourseEnrollment::query()->with(['course' => fn ($builder) => $builder->with(['instructor'])->withCount('lessons')])
            ->where('user_id', $userId)->latest('enrolled_at')->get()->map(function (CourseEnrollment $enrollment) use ($userId): object {
                $course = $enrollment->course;
                $lessonIds = $course->lessons()->where('status', 'published')->pluck('id');
                $completed = $lessonIds->isEmpty() ? 0 : LessonProgress::query()->where('user_id', $userId)->where('course_id', $course->id)->where('completed', true)->whereIn('lesson_id', $lessonIds)->count();
                $progress = $lessonIds->isEmpty() ? 0 : min(100, (int) round(($completed / $lessonIds->count()) * 100));
                $lastProgress = LessonProgress::query()->with('lesson')->where('user_id', $userId)->where('course_id', $course->id)->latest('last_seen_at')->first();
                $firstLesson = $course->lessons()->where('status', 'published')->orderBy('lesson_order')->first();
                $statusKey = $progress >= 100 ? 'completed' : ($lastProgress ? 'in_progress' : 'not_started');

                return (object) [
                    'course' => $course,
                    'progress' => $progress,
                    'completed_lessons' => $completed,
                    'total_lessons' => $lessonIds->count(),
                    'last_lesson' => $lastProgress?->lesson,
                    'next_lesson' => $lastProgress?->lesson ?: $firstLesson,
                    'last_activity' => $lastProgress?->last_seen_at ?: $enrollment->enrolled_at,
                    'training_mode' => $enrollment->training_mode,
                    'status_key' => $statusKey,
                    'status_label' => __('lms::ui.student_portal_'.$statusKey),
                    'action_label' => $statusKey === 'completed' ? __('lms::ui.student_portal_review_course') : ($statusKey === 'in_progress' ? __('lms::ui.student_portal_continue_learning') : __('lms::ui.student_portal_start_course')),
                ];
            });
    }

    private function saveInstructorLesson(Request $request, Instructor $instructor, Lesson $lesson): void
    {
        $courseIds = $instructor->courses()->pluck('id');
        $validated = $request->validate([
            'course_id' => ['required', 'integer', Rule::in($courseIds->all())],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('lms_lessons', 'slug')->ignore($lesson->id)],
            'module_title' => ['required', 'string', 'max:160'],
            'module_order' => ['required', 'integer', 'min:1', 'max:10000'],
            'lesson_order' => ['required', 'integer', 'min:1', 'max:10000'],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'image', 'max:8192'],
            'featured_image_media_url' => ['nullable', 'string', 'max:2048'],
            'featured_image_media_name' => ['nullable', 'string', 'max:255'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'published'])],
            'publish_date' => [Rule::requiredIf($request->input('availability') === 'scheduled'), 'nullable', 'date'],
            'visibility' => ['required', Rule::in(['enrolled', 'public', 'private'])],
            'duration_hours' => ['required', 'integer', 'min:0', 'max:999'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'availability' => ['required', Rule::in(['immediate', 'scheduled', 'after_previous'])],
            'prerequisite_lesson_id' => [Rule::requiredIf($request->input('availability') === 'after_previous'), 'nullable', 'integer', Rule::exists('lms_lessons', 'id')],
            'access' => ['required', 'string', 'max:80'],
            'completion_requirement' => ['required', Rule::in(['manual', 'video', 'content'])],
            'lesson_type' => ['required', Rule::in(['video', 'text', 'pdf'])],
            'author_id' => ['nullable', 'integer', Rule::in([(int) $request->user()->id])],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:102400'],
            'pdf_file' => ['nullable', 'file', 'mimes:pdf', 'max:102400'],
            'pdf_media_url' => ['nullable', 'string', 'max:2048'],
            'pdf_media_name' => ['nullable', 'string', 'max:255'],
            'free_preview' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:102400'],
            'remove_attachment_paths' => ['nullable', 'array'],
            'remove_attachment_paths.*' => ['nullable', 'string', 'max:2048'],
            'media_attachments' => ['nullable', 'array'],
            'media_attachments.*.url' => ['nullable', 'string', 'max:2048'],
            'media_attachments.*.name' => ['nullable', 'string', 'max:255'],
            'media_attachments.*.size' => ['nullable', 'string', 'max:80'],
            'media_attachments.*.mime' => ['nullable', 'string', 'max:120'],
            'resources' => ['nullable', 'array'],
            'resources.*.file_name' => ['nullable', 'string', 'max:255'],
            'resources.*.type' => ['nullable', 'string', 'max:80'],
            'resources.*.size' => ['nullable', 'string', 'max:80'],
            'resources.*.download' => ['nullable', 'boolean'],
        ]);

        $prerequisiteLessonId = $this->validatedInstructorPrerequisiteLessonId(
            $validated,
            $lesson,
            $courseIds->map(fn ($id): int => (int) $id)->all(),
        );

        $requestedStatus = (string) $request->input('action') === 'publish'
            ? 'published'
            : (string) ($validated['status'] ?? $lesson->status ?? 'draft');
        $canPublish = $instructor->canPublishDirectly();
        $status = $canPublish && $requestedStatus === 'published' ? 'published' : 'draft';
        $submittedForReview = ! $canPublish && $requestedStatus === 'published';
        $reviewStatus = $canPublish
            ? ($status === 'published' ? 'approved' : 'none')
            : ($submittedForReview || $lesson->review_status === 'pending' ? 'pending' : 'none');
        $isNew = ! $lesson->exists;
        $lessonOrder = $isNew
            ? $this->nextInstructorLessonOrder((int) $validated['course_id'], (string) $validated['module_title'])
            : (int) $validated['lesson_order'];

        $lesson->fill([
            'course_id' => $validated['course_id'],
            'title' => $validated['title'],
            'slug' => $this->uniqueLessonSlug((string) ($validated['slug'] ?? ''), $validated['title'], $lesson),
            'module_title' => $validated['module_title'],
            'module_order' => $validated['module_order'],
            'lesson_order' => $lessonOrder,
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'] ?? null,
            'status' => $status,
            'review_status' => $reviewStatus,
            'publish_date' => $validated['publish_date'] ?? null,
            'published_at' => $status === 'published' ? ($lesson->published_at ?: now()) : null,
            'visibility' => $validated['visibility'],
            'duration_hours' => $validated['duration_hours'],
            'duration_minutes' => $validated['duration_minutes'],
            'availability' => $validated['availability'],
            'prerequisite_lesson_id' => $prerequisiteLessonId,
            'access' => $validated['access'],
            'completion_requirement' => $validated['completion_requirement'],
            'lesson_type' => $validated['lesson_type'],
            'video_url' => $validated['video_url'] ?? null,
            'free_preview' => $request->boolean('free_preview'),
            'resources' => $this->cleanInstructorResources($validated['resources'] ?? []),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if ($validated['lesson_type'] === 'video' && ! empty($validated['video_url'])) {
            $lesson->video_path = $this->protectPlatformVideo($validated['video_url']);
        } elseif ($validated['lesson_type'] !== 'video') {
            $lesson->video_path = null;
        }

        if ($request->boolean('remove_featured_image')) {
            $lesson->featured_image_path = null;
            $lesson->featured_image_name = null;
            $lesson->featured_image_meta = null;
        } elseif ($request->hasFile('featured_image')) {
            $file = $request->file('featured_image');
            $lesson->featured_image_path = $file->store('lms/lesson-images', 'public');
            $lesson->featured_image_name = $file->getClientOriginalName();
            $lesson->featured_image_meta = ['size' => $file->getSize(), 'mime' => $file->getMimeType(), 'source' => 'upload'];
        } elseif (! empty($validated['featured_image_media_url']) && ($path = $this->platformMediaPathFromUrl($validated['featured_image_media_url']))) {
            $lesson->featured_image_path = $path;
            $lesson->featured_image_name = trim((string) ($validated['featured_image_media_name'] ?? '')) ?: basename($path);
            $lesson->featured_image_meta = ['source' => 'media_library', 'url' => Storage::disk('public')->url($path)];
        }

        if ($request->hasFile('video_file')) {
            $lesson->video_path = 'private:'.$request->file('video_file')->store('lms/protected-videos', 'local');
        }

        $newAttachments = collect($this->storeInstructorLessonAttachments($request))
            ->concat($this->storeInstructorLessonPdf($request))
            ->unique('path')
            ->values()
            ->all();
        $retainedAttachments = $isNew ? [] : $this->retainedInstructorLessonAttachments($lesson, $request);
        if ($isNew || $newAttachments !== [] || $request->filled('remove_attachment_paths')) {
            $lesson->attachments = collect(array_merge($retainedAttachments, $newAttachments))
                ->unique('path')
                ->values()
                ->all();
        }

        $lesson->save();
        $this->syncInstructorModuleOrder($lesson);
    }

    private function saveInstructorLessonDraft(Request $request, Instructor $instructor, ?Lesson $existingLesson = null): Lesson
    {
        $lesson = $existingLesson ?? new Lesson;
        if ($lesson->exists) {
            $this->authorizeInstructorLesson($instructor, $lesson);
        }

        $courseIds = $instructor->courses()->pluck('id')->map(fn ($id): int => (int) $id);
        $courseId = $request->integer('course_id');
        if ($courseId < 1 || ! $courseIds->contains($courseId)) {
            throw ValidationException::withMessages(['course_id' => 'Select one of your courses before saving the draft.']);
        }

        $title = trim((string) $request->input('title', '')) ?: ($lesson->title ?: 'Untitled lesson');
        $lessonType = in_array($request->input('lesson_type'), ['video', 'text', 'pdf'], true)
            ? (string) $request->input('lesson_type')
            : ($lesson->lesson_type ?: 'text');
        $availability = in_array($request->input('availability'), ['immediate', 'scheduled', 'after_previous'], true)
            ? (string) $request->input('availability')
            : ($lesson->availability ?: 'immediate');
        $prerequisiteId = null;
        if ($availability === 'after_previous' && $request->integer('prerequisite_lesson_id') > 0) {
            $prerequisiteId = $this->validatedInstructorPrerequisiteLessonId([
                'availability' => $availability,
                'course_id' => $courseId,
                'prerequisite_lesson_id' => $request->integer('prerequisite_lesson_id'),
            ], $lesson, $courseIds->all());
        }

        $lesson->fill([
            'course_id' => $courseId,
            'module_title' => $this->nullableInstructorValue($request->input('module_title')) ?: 'Module 1: Introduction',
            'module_order' => max(1, $request->integer('module_order', $lesson->module_order ?: 1)),
            'lesson_order' => max(1, $request->integer('lesson_order', $lesson->lesson_order ?: 1)),
            'title' => $title,
            'slug' => $this->uniqueLessonSlug((string) $request->input('slug', ''), $title, $lesson),
            'description' => $this->nullableInstructorValue($request->input('description')),
            'content' => $this->nullableInstructorValue($request->input('content')),
            'status' => 'draft',
            'review_status' => 'none',
            'publish_date' => $request->input('publish_date') ?: null,
            'published_at' => null,
            'visibility' => $this->nullableInstructorValue($request->input('visibility')) ?: ($lesson->visibility ?: 'enrolled'),
            'duration_hours' => max(0, $request->integer('duration_hours', $lesson->duration_hours ?: 0)),
            'duration_minutes' => min(59, max(0, $request->integer('duration_minutes', $lesson->duration_minutes ?: 30))),
            'availability' => $availability,
            'prerequisite_lesson_id' => $prerequisiteId,
            'access' => $this->nullableInstructorValue($request->input('access')) ?: ($lesson->access ?: 'enrolled'),
            'completion_requirement' => in_array($request->input('completion_requirement'), ['manual', 'video', 'content'], true)
                ? $request->input('completion_requirement')
                : ($lesson->completion_requirement ?: 'manual'),
            'lesson_type' => $lessonType,
            'video_url' => $this->nullableInstructorValue($request->input('video_url')),
            'free_preview' => $request->boolean('free_preview'),
            'resources' => $this->cleanInstructorResources((array) $request->input('resources', [])),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $lesson->save();

        $newAttachments = collect($this->storeInstructorLessonAttachments($request))
            ->concat($this->storeInstructorLessonPdf($request))
            ->unique('path')
            ->values()
            ->all();
        $retainedAttachments = $this->retainedInstructorLessonAttachments($lesson, $request);
        if ($newAttachments !== [] || $request->filled('remove_attachment_paths')) {
            $lesson->attachments = collect(array_merge($retainedAttachments, $newAttachments))
                ->unique('path')
                ->values()
                ->all();
            $lesson->save();
        }

        $this->syncInstructorModuleOrder($lesson);

        return $lesson;
    }

    /** @return array<string, mixed> */
    private function instructorCourseEditorData(Instructor $instructor, ?Course $course, User $user): array
    {
        if ($course) {
            $this->authorizeInstructorCourse($instructor, $course);
        }

        $lessons = $course
            ? Lesson::query()
                ->where('course_id', $course->id)
                ->with('author:id,name')
                ->orderBy('module_order')
                ->orderBy('lesson_order')
                ->orderBy('id')
                ->get()
            : collect();

        $storedUnits = collect($course?->curriculum_units ?: [])
            ->filter(fn (mixed $unit): bool => is_array($unit) && trim((string) ($unit['title'] ?? '')) !== '')
            ->map(fn (array $unit, int $index): array => [
                'key' => (string) ($unit['key'] ?? 'unit-'.($index + 1)),
                'title' => trim((string) $unit['title']),
                'order' => max(1, (int) ($unit['order'] ?? ($index + 1))),
            ]);
        $lessonUnits = $lessons
            ->groupBy(fn (Lesson $lesson): string => trim((string) $lesson->module_title) ?: 'Module 1')
            ->map(function ($moduleLessons, string $title): array {
                $first = $moduleLessons->sortBy('module_order')->first();

                return [
                    'key' => 'module-'.Str::slug($title).'-'.((int) ($first?->module_order ?: 1)),
                    'title' => $title,
                    'order' => max(1, (int) ($first?->module_order ?: 1)),
                ];
            });
        $units = $storedUnits
            ->concat($lessonUnits)
            ->unique(fn (array $unit): string => mb_strtolower($unit['title']))
            ->sortBy('order')
            ->values();

        if ($units->isEmpty() && $course) {
            $units = collect([['key' => 'unit-1', 'title' => 'Module 1: Introduction', 'order' => 1]]);
        }

        $lessonScope = $course ? collect([(int) $course->id]) : collect();

        return [
            'curriculumUnits' => $units->all(),
            'courseLessons' => $lessons->map(fn (Lesson $lesson): array => $this->instructorLessonEditorPayload($lesson))->all(),
            'lessonAuthors' => User::query()->whereKey($user->id)->get(['id', 'name']),
            'lessonFormOptions' => $this->instructorLessonFormOptions($lessonScope),
            'enrolledStudentCount' => $course && Schema::hasTable('lms_course_enrollments')
                ? CourseEnrollment::query()->where('course_id', $course->id)->count()
                : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function instructorCourseFormOptions(Instructor $instructor): array
    {
        $categories = collect(['Interior Design', 'Fine Art', 'Digital Art', 'Photography'])
            ->merge(Course::query()->whereNotNull('category')->pluck('category'))
            ->map(fn ($category): string => trim((string) $category))
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $category): array => ['value' => $category, 'label' => match ($category) {
                'Interior Design' => __('lms::ui.category_interior_design'),
                'Fine Art' => __('lms::ui.category_fine_art'),
                'Digital Art' => __('lms::ui.category_digital_art'),
                'Photography' => __('lms::ui.category_photography'),
                default => $category,
            }])
            ->all();

        return [
            'statuses' => [
                ['value' => 'draft', 'label' => __('lms::ui.instructor_option_status_draft')],
                ['value' => 'published', 'label' => $instructor->canPublishDirectly() ? __('lms::ui.instructor_option_status_published') : __('lms::ui.instructor_option_status_submit')],
            ],
            'visibility' => [
                ['value' => 'public', 'label' => __('lms::ui.instructor_option_public')],
                ['value' => 'private', 'label' => __('lms::ui.instructor_option_private')],
                ['value' => 'unlisted', 'label' => __('lms::ui.instructor_option_unlisted')],
            ],
            'categories' => $categories,
            'instructors' => [[
                'value' => (string) $instructor->id,
                'label' => $instructor->name,
                'email' => (string) ($instructor->user?->email ?: $instructor->email),
            ]],
            'levels' => [
                ['value' => 'Beginner', 'label' => __('lms::ui.instructor_option_level_beginner')],
                ['value' => 'Intermediate', 'label' => __('lms::ui.instructor_option_level_intermediate')],
                ['value' => 'Advanced', 'label' => __('lms::ui.instructor_option_level_advanced')],
            ],
            'languages' => [
                ['value' => 'English', 'label' => __('lms::ui.instructor_option_language_english')],
                ['value' => 'Arabic', 'label' => __('lms::ui.instructor_option_language_arabic')],
            ],
            'enrollmentTypes' => [
                ['value' => 'open', 'label' => __('lms::ui.instructor_option_enrollment_open')],
                ['value' => 'manual', 'label' => __('lms::ui.instructor_option_enrollment_manual')],
                ['value' => 'invite_only', 'label' => __('lms::ui.instructor_option_enrollment_invite')],
            ],
            'accessDurations' => [
                ['value' => 'lifetime', 'label' => __('lms::ui.instructor_option_lifetime')],
                ['value' => 'one_year', 'label' => __('lms::ui.instructor_option_one_year')],
                ['value' => 'six_months', 'label' => __('lms::ui.instructor_option_six_months')],
            ],
            'progressRules' => [
                ['value' => 'complete_previous', 'label' => __('lms::ui.instructor_option_progress_complete_previous')],
                ['value' => 'free_navigation', 'label' => __('lms::ui.instructor_option_progress_free')],
            ],
        ];
    }

    private function instructorLessonFormOptions($courseIds): array
    {
        $ids = collect($courseIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $moduleLessons = Lesson::query()
            ->whereIn('course_id', $ids)
            ->whereNotNull('module_title')
            ->where('module_title', '!=', '')
            ->orderBy('course_id')
            ->orderBy('module_order')
            ->orderBy('module_title')
            ->get(['course_id', 'module_title', 'module_order']);
        $modulesByCourse = $moduleLessons
            ->groupBy('course_id')
            ->map(fn ($lessons): array => $lessons->pluck('module_title')->unique()->values()->all())
            ->all();
        $moduleOrdersByCourse = $moduleLessons
            ->groupBy('course_id')
            ->map(fn ($lessons): array => $lessons
                ->groupBy('module_title')
                ->map(fn ($items): int => max(1, (int) $items->min('module_order')))
                ->all())
            ->all();

        return [
            'modulesByCourse' => $modulesByCourse,
            'moduleOrdersByCourse' => $moduleOrdersByCourse,
            'prerequisiteLessonsByCourse' => $this->instructorPrerequisiteLessons($ids),
            'statuses' => [
                ['value' => 'draft', 'label' => __('lms::ui.instructor_option_status_draft')],
                ['value' => 'published', 'label' => __('lms::ui.instructor_option_status_published')],
            ],
            'visibility' => [
                ['value' => 'enrolled', 'label' => __('lms::ui.instructor_option_visibility_enrolled')],
                ['value' => 'public', 'label' => __('lms::ui.instructor_option_public')],
                ['value' => 'private', 'label' => __('lms::ui.instructor_option_private')],
            ],
            'availability' => [
                ['value' => 'immediate', 'label' => __('lms::ui.instructor_option_availability_immediate')],
                ['value' => 'scheduled', 'label' => __('lms::ui.instructor_option_availability_scheduled')],
                ['value' => 'after_previous', 'label' => __('lms::ui.instructor_option_availability_after_previous')],
            ],
            'access' => [
                ['value' => 'enrolled', 'label' => __('lms::ui.instructor_option_visibility_enrolled')],
                ['value' => 'public', 'label' => __('lms::ui.instructor_option_access_public_preview')],
                ['value' => 'instructor', 'label' => __('lms::ui.instructor_option_access_instructor')],
            ],
            'completion' => [
                ['value' => 'manual', 'label' => __('lms::ui.instructor_option_completion_manual'), 'help' => __('lms::ui.instructor_option_completion_manual_help')],
            ],
            'types' => [
                ['value' => 'video', 'label' => __('lms::ui.instructor_option_type_video'), 'icon' => '▣'],
                ['value' => 'text', 'label' => __('lms::ui.instructor_option_type_text'), 'icon' => '▤'],
                ['value' => 'pdf', 'label' => __('lms::ui.instructor_option_type_pdf'), 'icon' => '▧'],
            ],
        ];
    }

    private function prepareInstructorLessonContentType(Request $request): void
    {
        $hasVideo = $request->filled('video_url') || $request->hasFile('video_file');
        $hasPdf = $request->filled('pdf_media_url') || $request->hasFile('pdf_file');
        $hasText = trim(strip_tags((string) $request->input('content', ''))) !== '';
        $type = $hasVideo ? 'video' : ($hasPdf ? 'pdf' : ($hasText ? 'text' : 'text'));
        $completion = (string) $request->input('completion_requirement', 'manual');
        if ($completion === 'auto') {
            $completion = $type === 'video' ? 'video' : 'content';
        }

        $request->merge([
            'lesson_type' => $type,
            'completion_requirement' => in_array($completion, ['manual', 'video', 'content'], true) ? $completion : 'manual',
        ]);
    }

    /** @return array<string, mixed> */
    private function instructorLessonEditorPayload(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'module_title' => $lesson->module_title,
            'module_order' => (int) ($lesson->module_order ?: 1),
            'lesson_order' => (int) ($lesson->lesson_order ?: 1),
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'description' => $lesson->description,
            'content' => $lesson->content,
            'status' => $lesson->status,
            'visibility' => $lesson->visibility,
            'duration_hours' => (int) $lesson->duration_hours,
            'duration_minutes' => (int) $lesson->duration_minutes,
            'availability' => $lesson->availability,
            'prerequisite_lesson_id' => $lesson->prerequisite_lesson_id,
            'publish_date' => $lesson->publish_date?->format('Y-m-d\TH:i'),
            'access' => $lesson->access,
            'completion_requirement' => $lesson->completion_requirement,
            'lesson_type' => $lesson->lesson_type,
            'video_url' => $lesson->video_url,
            'video_path' => $lesson->video_path,
            'free_preview' => (bool) $lesson->free_preview,
            'author_id' => $lesson->created_by,
            'author_name' => $lesson->author?->name ?: 'Unassigned',
            'attachments' => collect($lesson->attachments ?: [])->map(function (array $attachment): array {
                $path = (string) ($attachment['path'] ?? '');

                return [...$attachment, 'url' => $path !== '' ? Storage::disk('public')->url($path) : null];
            })->values()->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function storeInstructorLessonAttachments(Request $request): array
    {
        $uploads = collect($request->file('attachments', []))
            ->filter()
            ->map(fn ($file): array => [
                'file_name' => $file->getClientOriginalName(),
                'path' => $file->store('lms/lesson-attachments', 'public'),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'download' => true,
            ]);
        $mediaSelections = collect((array) $request->input('media_attachments', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): ?array {
                $path = $this->platformMediaPathFromUrl((string) ($item['url'] ?? ''));
                if (! $path) {
                    return null;
                }

                return [
                    'file_name' => trim((string) ($item['name'] ?? '')) ?: basename($path),
                    'path' => $path,
                    'size' => Storage::disk('public')->size($path),
                    'mime' => trim((string) ($item['mime'] ?? '')) ?: (Storage::disk('public')->mimeType($path) ?: null),
                    'download' => true,
                ];
            })
            ->filter();

        return $uploads->concat($mediaSelections)->unique('path')->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function storeInstructorLessonPdf(Request $request): array
    {
        if ($request->hasFile('pdf_file')) {
            $file = $request->file('pdf_file');
            $path = $file->store('media/'.now()->format('Y/m'), 'public');

            return [[
                'file_name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'download' => true,
            ]];
        }

        $path = $this->platformMediaPathFromUrl((string) $request->input('pdf_media_url', ''));
        if (! $path || ! str_ends_with(strtolower($path), '.pdf')) {
            return [];
        }

        return [[
            'file_name' => trim((string) $request->input('pdf_media_name', '')) ?: basename($path),
            'path' => $path,
            'size' => Storage::disk('public')->size($path),
            'mime' => Storage::disk('public')->mimeType($path) ?: 'application/pdf',
            'download' => true,
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private function retainedInstructorLessonAttachments(Lesson $lesson, Request $request): array
    {
        $removedPaths = collect((array) $request->input('remove_attachment_paths', []))
            ->filter(fn ($path): bool => is_string($path) && trim($path) !== '')
            ->map(fn (string $path): string => trim($path))
            ->all();

        return collect($lesson->attachments ?: [])
            ->reject(fn (array $attachment): bool => in_array((string) ($attachment['path'] ?? ''), $removedPaths, true))
            ->values()
            ->all();
    }

    /** @return array<int, array{file_name:string,type:string,size:string,download:bool}> */
    private function cleanInstructorResources(array $items): array
    {
        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'file_name' => trim((string) ($item['file_name'] ?? '')),
                'type' => trim((string) ($item['type'] ?? '')),
                'size' => trim((string) ($item['size'] ?? '')),
                'download' => (bool) ($item['download'] ?? false),
            ])
            ->filter(fn (array $item): bool => $item['file_name'] !== '')
            ->values()
            ->all();
    }

    private function nextInstructorLessonOrder(int $courseId, string $moduleTitle): int
    {
        return ((int) Lesson::query()
            ->where('course_id', $courseId)
            ->where('module_title', trim($moduleTitle))
            ->max('lesson_order')) + 1;
    }

    private function syncInstructorModuleOrder(Lesson $lesson): void
    {
        if (! $lesson->course_id || ! $lesson->module_title) {
            return;
        }

        Lesson::query()
            ->where('course_id', $lesson->course_id)
            ->where('module_title', $lesson->module_title)
            ->update(['module_order' => max(1, (int) $lesson->module_order)]);
    }

    private function instructorPrerequisiteLessons($courseIds): array
    {
        return Lesson::query()
            ->whereIn('course_id', $courseIds)
            ->whereNull('deleted_at')
            ->orderBy('course_id')
            ->orderBy('module_order')
            ->orderBy('lesson_order')
            ->orderBy('id')
            ->get(['id', 'course_id', 'title', 'module_title', 'lesson_order'])
            ->groupBy('course_id')
            ->map(fn ($lessons): array => $lessons->map(fn (Lesson $item): array => [
                'id' => (int) $item->id,
                'title' => $item->title,
                'module' => $item->module_title,
                'order' => (int) $item->lesson_order,
            ])->values()->all())
            ->all();
    }

    /** @param array<string, mixed> $validated @param list<int> $courseIds */
    private function validatedInstructorPrerequisiteLessonId(array $validated, Lesson $lesson, array $courseIds): ?int
    {
        if (($validated['availability'] ?? 'immediate') !== 'after_previous') {
            return null;
        }

        $courseId = (int) $validated['course_id'];
        $prerequisiteId = (int) ($validated['prerequisite_lesson_id'] ?? 0);
        $isValid = in_array($courseId, $courseIds, true)
            && $prerequisiteId > 0
            && (! $lesson->exists || $prerequisiteId !== (int) $lesson->id)
            && Lesson::query()
                ->whereKey($prerequisiteId)
                ->where('course_id', $courseId)
                ->whereNull('deleted_at')
                ->exists();

        if (! $isValid) {
            throw ValidationException::withMessages([
                'prerequisite_lesson_id' => 'Select a previous lesson from the same course.',
            ]);
        }

        return $prerequisiteId;
    }

    private function authorizeInstructorLesson(Instructor $instructor, Lesson $lesson): void
    {
        abort_unless($instructor->courses()->whereKey($lesson->course_id)->exists(), 403);
    }

    private function courseProgress(int $userId, Course $course): int
    {
        $ids = $course->lessons()->where('status', 'published')->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }
        $completed = LessonProgress::query()->where('user_id', $userId)->where('course_id', $course->id)->where('completed', true)->whereIn('lesson_id', $ids)->count();

        return min(100, (int) round(($completed / $ids->count()) * 100));
    }

    private function uniqueLessonSlug(string $requestedSlug, string $title, Lesson $lesson): string
    {
        $base = Str::slug($requestedSlug !== '' ? $requestedSlug : $this->transliterateArabic($title)) ?: 'lesson-'.now()->format('YmdHis');
        $slug = $base;
        $counter = 2;
        while (Lesson::withTrashed()->where('slug', $slug)->when($lesson->exists, fn ($builder) => $builder->whereKeyNot($lesson->id))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function uniqueCourseSlug(string $requestedSlug, string $title, Course $course): string
    {
        $base = Str::slug($requestedSlug !== '' ? $requestedSlug : $this->transliterateArabic($title)) ?: 'course-'.now()->format('YmdHis');
        $slug = $base;
        $counter = 2;

        while (Course::withTrashed()->where('slug', $slug)->when($course->exists, fn ($builder) => $builder->whereKeyNot($course->id))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /** @return list<string> */
    private function cleanStringList(array $values): array
    {
        return collect($values)
            ->map(fn ($value): string => trim(strip_tags((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<array{question:string,answer:string}> */
    private function cleanLearnItems(array $values): array
    {
        return collect($values)
            ->map(function ($item): ?array {
                if (is_array($item)) {
                    $title = trim(strip_tags((string) ($item['title'] ?? '')));
                    $text = trim(strip_tags((string) ($item['text'] ?? '')));

                    return $title !== '' || $text !== '' ? ['title' => $title, 'text' => $text] : null;
                }

                $title = trim(strip_tags((string) $item));

                return $title !== '' ? ['title' => $title, 'text' => ''] : null;
            })
            ->filter()
            ->unique(fn (array $item): string => mb_strtolower($item['title'].'|'.$item['text']))
            ->values()
            ->all();
    }
    private function cleanFaqs(array $faqs): array
    {
        return collect($faqs)
            ->map(fn ($faq): array => [
                'question' => trim(strip_tags((string) ($faq['question'] ?? ''))),
                'answer' => trim(strip_tags((string) ($faq['answer'] ?? ''))),
            ])
            ->filter(fn (array $faq): bool => $faq['question'] !== '' && $faq['answer'] !== '')
            ->values()
            ->all();
    }

    private function transliterateArabic(string $value): string
    {
        return strtr($value, [
            'ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ب' => 'b', 'ت' => 't',
            'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh',
            'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd',
            'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
            'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'ة' => 'h',
            'و' => 'w', 'ؤ' => 'w', 'ي' => 'y', 'ى' => 'a', 'ئ' => 'y', 'ء' => '',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function storeAvatar(Request $request, $model, string $directory): void
    {
        if ($request->filled('avatar_media_url') && ($path = $this->platformMediaPathFromUrl($request->input('avatar_media_url')))) {
            $model->avatar_path = $path;
            $model->avatar_name = trim((string) $request->input('avatar_media_name')) ?: basename($path);
            $model->avatar_meta = ['source' => 'media_library', 'url' => Storage::disk('public')->url($path)];

            return;
        }

        if (! $request->hasFile('avatar')) {
            return;
        }
        if ($model->avatar_path) {
            Storage::disk('public')->delete($model->avatar_path);
        }
        $file = $request->file('avatar');
        $model->avatar_path = $file->store($directory, 'public');
        $model->avatar_name = $file->getClientOriginalName();
        $model->avatar_meta = ['mime' => $file->getMimeType(), 'size' => $file->getSize()];
    }

    private function authorizeMediaStudio(Request $request): void
    {
        $user = $request->user();
        $isActiveInstructor = $user
            && $user->can('lms.instructor.portal')
            && Instructor::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

        abort_unless($isActiveInstructor, 403);
    }
}
