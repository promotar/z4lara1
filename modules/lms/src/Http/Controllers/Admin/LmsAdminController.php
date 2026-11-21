<?php

namespace Modules\Lms\Http\Controllers\Admin;

use App\Http\Controllers\Admin\MediaController;
use App\Models\User;
use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\CourseCode;
use Modules\Lms\Models\CourseEnrollment;
use Modules\Lms\Models\CourseTranslation;
use Modules\Lms\Models\Instructor;
use Modules\Lms\Models\Invitation;
use Modules\Lms\Models\Lesson;
use Modules\Lms\Models\LessonTranslation;
use Modules\Lms\Models\Student;
use Modules\Lms\Services\LmsNotificationService;
use Modules\Lms\Services\LmsPublicationService;
use Modules\Lms\Support\CurriculumSections;
use Modules\Lms\Support\ResolvesPlatformMedia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LmsAdminController extends Controller
{
    use ResolvesPlatformMedia;

    /** Reuse the platform Media Studio behind LMS permissions without changing core. */
    public function mediaIndex(Request $request)
    {
        return app(MediaController::class)->index($request);
    }

    public function mediaStore(Request $request)
    {
        return app(MediaController::class)->store($request);
    }


    public function mediaDelete(Request $request)
    {
        return app(MediaController::class)->destroy($request);
    }

    public function overview(Request $request): View
    {
        return view('lms::admin.overview', $this->overviewData($request));
    }

    public function overviewStyles(): Response
    {
        return response()->file(
            dirname(__DIR__, 4).'/resources/assets/admin/overview.css',
            [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }

    public function exportOverview(Request $request): StreamedResponse
    {
        $data = $this->overviewData($request);
        $fileName = 'lms-overview-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['LMS Overview Report']);
            fputcsv($handle, ['Range', $data['range']['label']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Metric', 'Value', 'Note']);

            foreach ($data['stats'] as $stat) {
                fputcsv($handle, [$stat['label'], $stat['value'], $stat['note']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Course Status', 'Count', 'Percent']);
            foreach ($data['courseStatus']['segments'] as $segment) {
                fputcsv($handle, [$segment['label'], $segment['count'], $segment['percent'].'%']);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Top Course', 'Instructor', 'Students', 'Completion', 'Status']);
            foreach ($data['topCourses'] as $course) {
                fputcsv($handle, [
                    $course['title'],
                    $course['instructor'],
                    $course['students'],
                    $course['completion'].'%',
                    $course['status'],
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function courses(Request $request): View
    {
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $query = Course::query()->with('translations');

        if (Schema::hasTable('lms_course_enrollments')) {
            $query->withCount(['enrollments' => static fn ($builder) => $builder->where('source', '!=', 'instructor_owner')]);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%');
            });
        }

        if ($category = trim((string) $request->query('category', ''))) {
            $query->where('category', $category);
        }

        if ($instructor = trim((string) $request->query('instructor', ''))) {
            $query->where('instructor_name', $instructor);
        }

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        if ($type = trim((string) $request->query('type', ''))) {
            $query->where('pricing_type', $type);
        }

        match ((string) $request->query('date_range', '')) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            default => null,
        };

        $courses = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'total' => Course::query()->count(),
            'published' => Course::query()->where('status', 'published')->count(),
            'draft' => Course::query()->where('status', 'draft')->count(),
            'students' => Schema::hasTable('lms_course_enrollments')
                ? CourseEnrollment::query()->where('source', '!=', 'instructor_owner')->distinct('user_id')->count('user_id')
                : 0,
        ];

        $filters = [
            'categories' => Course::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->filter()->values(),
            'instructors' => Course::query()->whereNotNull('instructor_name')->distinct()->orderBy('instructor_name')->pluck('instructor_name')->filter()->values(),
            'statuses' => ['draft', 'scheduled', 'published'],
            'types' => ['free', 'paid'],
            'dateRanges' => [
                '' => 'All Time',
                'today' => 'Today',
                'week' => 'Last 7 Days',
                'month' => 'Last 30 Days',
            ],
        ];

        return view('lms::admin.courses.index', [
            'courses' => $courses,
            'stats' => $stats,
            'filters' => $filters,
            'perPage' => $perPage,
            'locales' => app(PlatformLocale::class)->languages(),
        ]);
    }

    public function createCourse(): View
    {
        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $locale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode();

        return view('lms::admin.courses.create', [
            'course' => null,
            'formOptions' => $this->formOptions($locale),
            'locale' => $locale,
            'locales' => $locales,
            'translation' => null,
            ...$this->courseEditorData(),
        ]);
    }

    public function storeCourse(Request $request): RedirectResponse|JsonResponse
    {
        $this->normalizeCategoryInput($request);
        $action = (string) $request->input('action', 'draft');

        if ($action === 'autosave') {
            return $this->autosaveCourse($request);
        }

        if ($action === 'save_draft') {
            $course = $this->savePartialDraft($request);
            $switchLocale = (string) $request->input('switch_locale', '');
            $params = ['course' => $course];

            if ($switchLocale !== '' && array_key_exists($switchLocale, app(PlatformLocale::class)->languages())) {
                $params['locale'] = $switchLocale;
            }

            $statusLocale = $params['locale'] ?? app()->getLocale();

            return redirect()->route('admin.plugins.lms.courses.edit', $params)
                ->with('status', __('lms::ui.admin_course_draft_saved', [], $statusLocale));
        }

        $validated = $this->validateCourseRequest($request);

        $status = match ($action) {
            'publish' => 'published',
            'preview' => 'draft',
            default => $validated['status'] ?? 'draft',
        };

        $course = $this->draftCourseFromRequest($request) ?? new Course;
        $this->saveCourseFromRequest($course, $validated, $request, $status);

        if ($action === 'preview') {
            return redirect()->route('admin.plugins.lms.courses.preview', $course)->with('status', __('lms::ui.admin_course_draft_preview'));
        }

        return redirect()->route('admin.plugins.lms.courses')->with('status', $status === 'published' ? __('lms::ui.admin_course_published') : __('lms::ui.admin_course_draft_saved'));
    }

    public function editCourse(Request $request, Course $course): View
    {
        $course->loadMissing('translations');
        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $defaultLocale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode();
        $locale = (string) $request->query('locale', $defaultLocale);

        if (! array_key_exists($locale, $locales)) {
            $locale = $defaultLocale;
        }


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
        }

        return view('lms::admin.courses.create', [
            'course' => $display,
            'formOptions' => $this->formOptions($locale),
            'locale' => $locale,
            'locales' => $locales,
            'translation' => $translation,
            'baseCourse' => $course,
            ...$this->courseEditorData($display),
        ]);
    }

    public function updateCourse(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $locale = (string) $request->input('locale', app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode());
        $locales = app(PlatformLocale::class)->languages();

        if ($locale !== app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode() && array_key_exists($locale, $locales)) {
            return $this->updateCourseTranslation($request, $course, $locale);
        }

        $this->normalizeCategoryInput($request);
        $action = (string) $request->input('action', 'draft');
        $validated = $this->validateCourseRequest($request, $course);

        $status = match ($action) {
            'publish' => 'published',
            'preview' => $validated['status'] ?? $course->status ?? 'draft',
            default => $validated['status'] ?? $course->status ?? 'draft',
        };

        $this->saveCourseFromRequest($course, $validated, $request, $status);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'course_id' => $course->id,
                'status' => $course->status,
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        if ($action === 'preview') {
            return redirect()->route('admin.plugins.lms.courses.preview', $course)->with('status', __('lms::ui.admin_course_saved_preview'));
        }

        return redirect()->route('admin.plugins.lms.courses.edit', $course)->with('status', __('lms::ui.admin_course_updated'));
    }

    protected function updateCourseTranslation(Request $request, Course $course, string $locale): RedirectResponse|JsonResponse
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
            'requirements' => $this->cleanList($validated['requirements'] ?? []),
            'target_audience' => $this->cleanList($validated['target_audience'] ?? []),
            'faqs' => $this->cleanFaqs($validated['faqs'] ?? []),
            'updated_by' => Auth::id(),
        ]);

        if (! $translation->exists) {
            $translation->created_by = Auth::id();
        }

        $translation->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'course_id' => $course->id,
                'locale' => $locale,
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('admin.plugins.lms.courses.edit', ['course' => $course, 'locale' => $locale])
            ->with('status', __('core.translate.saved'));
    }

    public function updateCourseCurriculum(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'units' => ['present', 'array', 'max:250'],
            'units.*.key' => ['required', 'string', 'max:191'],
            'units.*.title' => ['required', 'string', 'max:160'],
            'units.*.previous_title' => ['nullable', 'string', 'max:160'],
            'units.*.order' => ['required', 'integer', 'min:1', 'max:10000'],
            'units.*.translations' => ['nullable', 'array'],
            'units.*.translations.*' => ['nullable', 'string', 'max:160'],
            'lessons' => ['sometimes', 'array', 'max:2000'],
            'lessons.*.id' => ['required', 'integer', 'distinct'],
            'lessons.*.module_title' => ['required', 'string', 'max:160'],
            'lessons.*.module_order' => ['required', 'integer', 'min:1', 'max:10000'],
            'lessons.*.lesson_order' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $storedUnits = CurriculumSections::index($course->curriculum_units);

        $units = collect($validated['units'])
            ->map(fn (array $unit): array => [
                'key' => trim($unit['key']),
                'title' => trim($unit['title']),
                'order' => (int) $unit['order'],
                'translations' => CurriculumSections::resolveForSave($unit, $storedUnits['byKey'], $storedUnits['byTitle']),
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

        DB::transaction(function () use ($course, $units, $lessons, $validated): void {
            foreach ($validated['units'] as $unit) {
                $previousTitle = trim((string) ($unit['previous_title'] ?? ''));
                $title = trim((string) $unit['title']);

                if ($previousTitle !== '' && $previousTitle !== $title) {
                    Lesson::query()
                        ->where('course_id', $course->id)
                        ->where('module_title', $previousTitle)
                        ->update(['module_title' => $title, 'updated_by' => Auth::id()]);
                }

                Lesson::query()
                    ->where('course_id', $course->id)
                    ->where('module_title', $title)
                    ->update(['module_order' => (int) $unit['order'], 'updated_by' => Auth::id()]);
            }

            foreach ($lessons as $lessonOrder) {
                Lesson::query()
                    ->where('course_id', $course->id)
                    ->whereKey($lessonOrder['id'])
                    ->update([
                        'module_title' => $lessonOrder['module_title'],
                        'module_order' => $lessonOrder['module_order'],
                        'lesson_order' => $lessonOrder['lesson_order'],
                        'updated_by' => Auth::id(),
                    ]);
            }

            $course->forceFill([
                'curriculum_units' => $units->all(),
                'updated_by' => Auth::id(),
            ])->save();
        });

        return response()->json([
            'ok' => true,
            'units' => $units->all(),
            'lessons' => $lessons->all(),
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    private function autosaveCourse(Request $request): JsonResponse
    {
        try {
            $course = $this->savePartialDraft($request);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->errors()['title'][0] ?? 'Add a course title before saving the draft.',
            ], 422);
        }

        return response()->json([
            'course_id' => $course->id,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    private function savePartialDraft(Request $request): Course
    {
        $title = trim((string) $request->input('title', ''));

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Add a course title before saving the draft.',
            ]);
        }

        $course = $this->draftCourseFromRequest($request) ?? new Course;
        $pricingType = in_array($request->input('pricing_type'), ['free', 'paid'], true)
            ? $request->input('pricing_type')
            : ($course->pricing_type ?: 'free');
        $limitType = in_array($request->input('enrollment_limit_type'), ['unlimited', 'limited'], true)
            ? $request->input('enrollment_limit_type')
            : ($course->enrollment_limit_type ?: 'unlimited');
        $instructor = $request->filled('instructor_id')
            ? Instructor::query()
                ->whereKey($request->integer('instructor_id'))
                ->where('status', 'active')
                ->first()
            : null;

        $course->fill([
            'program_type' => in_array($request->input('program_type'), ['course', 'diploma'], true)
                ? $request->input('program_type')
                : ($course->program_type ?: 'course'),
            'title' => $title,
            'slug' => $this->uniqueCourseSlug((string) $request->input('slug', ''), $title, $course),
            'subtitle' => $this->nullableDraftValue($request->input('subtitle')),
            'overview' => $this->nullableDraftValue($request->input('overview')),
            'partners' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $request->input('partners_text', '')) ?: []))),
            'short_description' => $this->nullableDraftValue($request->input('short_description')),
            'description' => $this->nullableDraftValue($request->input('description')),
            'outcomes' => $this->cleanLearnItems((array) $request->input('outcomes', [])),
            'requirements' => $this->cleanList((array) $request->input('requirements', [])),
            'target_audience' => $this->cleanList((array) $request->input('target_audience', [])),
            'faqs' => $this->cleanFaqs((array) $request->input('faqs', [])),
            'status' => 'draft',
            'review_status' => 'none',
            'publish_date' => $request->input('publish_date') ?: null,
            'visibility' => $this->nullableDraftValue($request->input('visibility')) ?: ($course->visibility ?: 'public'),
            'promo_video_url' => $this->nullableDraftValue($request->input('promo_video_url')),
            'category' => $this->nullableDraftValue($request->input('category')),
            'training_mode' => in_array($request->input('training_mode'), ['online', 'in_person', 'both'], true) ? (string) $request->input('training_mode') : ($course->training_mode ?: 'online'),
            'online_price' => $request->filled('online_price') ? $request->input('online_price') : null,
            'in_person_price' => $request->filled('in_person_price') ? $request->input('in_person_price') : null,
            'level' => $this->nullableDraftValue($request->input('level')),
            'language' => $this->nullableDraftValue($request->input('language')),
            'instructor_id' => $instructor?->getKey() ?? $course->instructor_id,
            'instructor_name' => $instructor?->name ?? $course->instructor_name,
            'instructor_title' => null,
            'pricing_type' => $pricingType,
            'regular_price' => $pricingType === 'paid' && $request->filled('regular_price') ? $request->input('regular_price') : null,
            'sale_price' => $pricingType === 'paid' && $request->filled('sale_price') ? $request->input('sale_price') : null,
            'currency' => strtoupper($this->nullableDraftValue($request->input('currency')) ?: ($course->currency ?: 'USD')),
            'enrollment_limit_type' => $limitType,
            'enrollment_limit' => $limitType === 'limited' && $request->filled('enrollment_limit') ? $request->integer('enrollment_limit') : null,
            'enrollment_type' => $this->nullableDraftValue($request->input('enrollment_type')) ?: ($course->enrollment_type ?: 'open'),
            'access_duration' => $this->nullableDraftValue($request->input('access_duration')) ?: ($course->access_duration ?: 'lifetime'),
            'course_progress' => $this->nullableDraftValue($request->input('course_progress')) ?: ($course->course_progress ?: 'complete_previous'),
            'updated_by' => Auth::id(),
        ]);

        if (! $course->exists) {
            $course->created_by = Auth::id();
        }

        $instructorRelationshipChanged = $course->isDirty('instructor_id');
        $course->save();

        // Repair legacy assignments whose relationship was saved before owner
        // enrollment synchronization existed. Changed assignments are handled
        // by CourseObserver, so this path avoids running the same work twice.
        if (! $instructorRelationshipChanged) {
            app(LmsPublicationService::class)->reconcileInstructorOwners($course);
        }

        return $course;
    }

    private function draftCourseFromRequest(Request $request): ?Course
    {
        $draftId = $request->integer('draft_course_id');

        if ($draftId < 1) {
            return null;
        }

        return Course::query()->whereKey($draftId)->where('status', 'draft')->first();
    }

    private function nullableDraftValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCategoryInput(Request $request): void
    {
        $newCategory = trim((string) $request->input('category_new', ''));

        if ($newCategory !== '') {
            $request->merge(['category' => $newCategory]);
        }
    }

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
    private function validateCourseRequest(Request $request, ?Course $course = null): array
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

        return $request->validate([
            'program_type' => ['required', Rule::in(['course', 'diploma'])],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('lms_courses', 'slug')->ignore($course?->id)],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string', 'max:600'],
            'partners_text' => ['nullable', 'string', 'max:4000'],
            'short_description' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'published'])],
            'publish_date' => ['nullable', 'date'],
            'visibility' => ['required', Rule::in(['public', 'private', 'unlisted'])],
            'featured_image' => ['nullable', 'image', 'max:8192'],
            'featured_image_media_url' => ['nullable', 'string', 'max:2048'],
            'featured_image_media_name' => ['nullable', 'string', 'max:255'],
            'promo_video_url' => ['nullable', 'url', 'max:255'],
            'promo_video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:51200'],
            'category' => ['required', 'string', 'max:120'],
            'category_new' => ['nullable', 'string', 'max:120'],
            'training_mode' => ['nullable', Rule::in(['online', 'in_person', 'both'])],
            'online_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'in_person_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'level' => ['required', 'string', 'max:80'],
            'language' => ['required', 'string', 'max:80'],
            'instructor_id' => [
                'required',
                'integer',
                Rule::exists('lms_instructors', 'id')
                    ->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at')),
            ],
            'pricing_type' => ['required', Rule::in(['free', 'paid'])],
            'regular_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'enrollment_limit_type' => ['required', Rule::in(['unlimited', 'limited'])],
            'enrollment_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'enrollment_type' => ['required', 'string', 'max:80'],
            'access_duration' => ['required', 'string', 'max:80'],
            'course_progress' => ['required', 'string', 'max:80'],
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
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveCourseFromRequest(Course $course, array $validated, Request $request, string $status): void
    {
        $isNew = ! $course->exists;
        $instructor = Instructor::query()
            ->whereKey((int) $validated['instructor_id'])
            ->where('status', 'active')
            ->firstOrFail();

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
            'slug' => $this->uniqueCourseSlug((string) ($validated['slug'] ?? ''), (string) $validated['title'], $course),
            'subtitle' => $validated['subtitle'] ?? null,
            'overview' => $validated['overview'] ?? null,
            'partners' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($validated['partners_text'] ?? '')) ?: []))),
            'short_description' => $validated['short_description'],
            'description' => $validated['description'],
            'outcomes' => $this->cleanLearnItems($validated['outcomes'] ?? []),
            'requirements' => $this->cleanList($validated['requirements'] ?? []),
            'target_audience' => $this->cleanList($validated['target_audience'] ?? []),
            'faqs' => $this->cleanFaqs($validated['faqs'] ?? []),
            'status' => $status,
            'review_status' => $status === 'published' ? 'approved' : 'none',
            'publish_date' => $validated['publish_date'] ?? null,
            'published_at' => $status === 'published' ? ($course->published_at ?: now()) : null,
            'visibility' => $validated['visibility'],
            'promo_video_url' => $validated['promo_video_url'] ?? null,
            'category' => $validated['category'],
            'training_mode' => $trainingMode,
            'online_price' => $trainingMode === 'both' ? $onlinePriceValue : null,
            'in_person_price' => $trainingMode !== 'online' ? $inPersonPriceValue : null,
            'level' => $validated['level'],
            'language' => $validated['language'],
            'instructor_id' => $instructor->getKey(),
            'instructor_name' => $instructor->name,
            'instructor_title' => null,
            'pricing_type' => $effectivePricingType,
            'regular_price' => $effectivePricingType === 'paid' ? $effectivePrice : null,
            'sale_price' => $validated['pricing_type'] === 'paid' ? ($validated['sale_price'] ?? null) : null,
            'currency' => strtoupper($validated['currency'] ?? 'USD'),
            'enrollment_limit_type' => $validated['enrollment_limit_type'],
            'enrollment_limit' => $validated['enrollment_limit_type'] === 'limited' ? ($validated['enrollment_limit'] ?? null) : null,
            'enrollment_type' => $validated['enrollment_type'],
            'access_duration' => $validated['access_duration'],
            'course_progress' => $validated['course_progress'],
            'updated_by' => Auth::id(),
        ]);

        if ($isNew) {
            $course->created_by = Auth::id();
        }

        if ($request->hasFile('featured_image')) {
            $file = $request->file('featured_image');
            $course->featured_image_path = $file->store('lms/course-images', 'public');
            $course->featured_image_name = $file->getClientOriginalName();
            $course->featured_image_meta = ['size' => $file->getSize(), 'mime' => $file->getMimeType(), 'source' => 'upload'];
        } elseif (! empty($validated['featured_image_media_url'])) {
            $mediaPath = $this->publicStoragePathFromUrl((string) $validated['featured_image_media_url']);

            if ($mediaPath) {
                $course->featured_image_path = $mediaPath;
                $course->featured_image_name = $validated['featured_image_media_name'] ?: basename($mediaPath);
                $course->featured_image_meta = [
                    'source' => 'media_library',
                    'url' => $this->publicStorageUrlFromPath($mediaPath),
                ];
            }
        }

        if ($request->hasFile('promo_video')) {
            $course->promo_video_path = $request->file('promo_video')->store('lms/course-videos', 'public');
        }

        $instructorRelationshipChanged = $course->isDirty('instructor_id');
        $course->save();

        if (! $instructorRelationshipChanged) {
            app(LmsPublicationService::class)->reconcileInstructorOwners($course);
        }
    }

    private function uniqueCourseSlug(string $slug, string $title, ?Course $course = null): string
    {
        $base = Str::slug($slug !== '' ? $slug : $this->transliterateArabic($title));

        if ($base === '') {
            $base = 'course-'.now()->format('YmdHis');
        }

        $candidate = $base;
        $counter = 2;

        while (Course::query()
            ->where('slug', $candidate)
            ->when($course?->id, fn ($query) => $query->whereKeyNot($course->id))
            ->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function transliterateArabic(string $value): string
    {
        $map = [
            'Ø§' => 'a', 'Ø£' => 'a', 'Ø¥' => 'i', 'Ø¢' => 'a', 'Ø¨' => 'b', 'Øª' => 't',
            'Ø«' => 'th', 'Ø¬' => 'j', 'Ø­' => 'h', 'Ø®' => 'kh', 'Ø¯' => 'd', 'Ø°' => 'dh',
            'Ø±' => 'r', 'Ø²' => 'z', 'Ø³' => 's', 'Ø´' => 'sh', 'Øµ' => 's', 'Ø¶' => 'd',
            'Ø·' => 't', 'Ø¸' => 'z', 'Ø¹' => 'a', 'Øº' => 'gh', 'Ù' => 'f', 'Ù‚' => 'q',
            'Ùƒ' => 'k', 'Ù„' => 'l', 'Ù…' => 'm', 'Ù†' => 'n', 'Ù‡' => 'h', 'Ø©' => 'h',
            'Ùˆ' => 'w', 'Ø¤' => 'w', 'ÙŠ' => 'y', 'Ù‰' => 'a', 'Ø¦' => 'y', 'Ø¡' => '',
            'Ù ' => '0', 'Ù¡' => '1', 'Ù¢' => '2', 'Ù£' => '3', 'Ù¤' => '4',
            'Ù¥' => '5', 'Ù¦' => '6', 'Ù§' => '7', 'Ù¨' => '8', 'Ù©' => '9',
        ];

        return strtr($value, $map);
    }

    private function makeCourseCode(int $courseId): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';

        for ($i = 0; $i < 8; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'C'.$courseId.'-'.$token;
    }

    private function publicStoragePathFromUrl(string $url): ?string
    {
        $url = trim($url);
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        if (! str_starts_with($path, '/storage/')) {
            return null;
        }

        $storagePath = ltrim(substr($path, strlen('/storage/')), '/');

        return Storage::disk('public')->exists($storagePath) ? $storagePath : null;
    }

    private function publicStorageUrlFromPath(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    public function previewCourse(Course $course): View
    {
        return view('lms::admin.courses.preview', ['course' => $course]);
    }

    public function bulkCourses(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', Rule::exists('lms_courses', 'id')],
            'bulk_action' => ['required', Rule::in(['publish', 'draft', 'delete'])],
        ]);

        $courses = Course::query()->whereIn('id', $validated['course_ids']);
        $count = (clone $courses)->count();

        match ($validated['bulk_action']) {
            'publish' => $courses->get()->each(fn (Course $course) => $course->forceFill([
                'status' => 'published',
                'review_status' => 'approved',
                'published_at' => $course->published_at ?: now(),
                'updated_by' => Auth::id(),
            ])->save()),
            'draft' => $courses->update([
                'status' => 'draft',
                'review_status' => 'none',
                'published_at' => null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]),
            'delete' => $courses->delete(),
        };

        return redirect()
            ->route('admin.plugins.lms.courses')
            ->with('status', $count.' course(s) updated successfully.');
    }

    public function updateCourseStatus(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'scheduled', 'published'])],
        ]);

        $course->forceFill([
            'status' => $validated['status'],
            'review_status' => $validated['status'] === 'published' ? 'approved' : 'none',
            'published_at' => $validated['status'] === 'published' ? ($course->published_at ?: now()) : null,
            'updated_by' => Auth::id(),
        ])->save();

        app(LmsNotificationService::class)->notifySubmissionDecision($course, $validated['status']);

        return back()->with('status', 'Course status updated successfully.');
    }

    public function deleteCourse(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect()
            ->route('admin.plugins.lms.courses')
            ->with('status', 'Course deleted successfully.');
    }

    public function courseCodes(Request $request): View
    {
        $selectedCourseId = (int) $request->integer('course_id');
        $courses = Course::query()
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'status']);

        $query = CourseCode::query()
            ->with(['course', 'enrollment'])
            ->latest();

        if ($selectedCourseId > 0) {
            $query->where('course_id', $selectedCourseId);
        }

        $codes = $query
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'total' => CourseCode::query()->count(),
            'available' => CourseCode::query()->where('status', 'available')->count(),
            'used' => CourseCode::query()->where('status', 'used')->count(),
            'enrollments' => CourseEnrollment::query()->count(),
        ];

        return view('lms::admin.courses.codes', [
            'courses' => $courses,
            'codes' => $codes,
            'stats' => $stats,
            'selectedCourseId' => $selectedCourseId,
            'codeIssuers' => \App\Models\User::query()
                ->whereHas('permissions', static fn ($query) => $query->where('name', 'lms.codes.generate'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'canManageIssuers' => (bool) auth()->user()?->can('lms.settings.manage'),
        ]);
    }

    public function generateCourseCodes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('lms_courses', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);
        $quantity = (int) $validated['quantity'];
        $batchId = 'batch-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $created = 0;

        DB::transaction(function () use ($course, $quantity, $batchId, &$created): void {
            while ($created < $quantity) {
                $code = $this->makeCourseCode((int) $course->id);

                if (CourseCode::query()->where('code', $code)->exists()) {
                    continue;
                }

                CourseCode::query()->create([
                    'course_id' => $course->id,
                    'code' => $code,
                    'batch_id' => $batchId,
                    'status' => 'available',
                    'created_by' => Auth::id(),
                ]);

                $created++;
            }
        });

        return redirect()
            ->route('admin.plugins.lms.courses.codes', [
                'course_id' => $course->id,
            ])
            ->with('status', 'Generated '.$created.' course code(s) for '.$course->title.'.');
    }

    public function exportCourseCodes(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $courseId = (int) $request->integer('course_id');
        $status = (string) $request->query('status', '');

        $query = CourseCode::query()->with('course')->orderByDesc('id');

        if ($courseId > 0) {
            $query->where('course_id', $courseId);
        }

        if (in_array($status, ['available', 'used', 'void'], true)) {
            $query->where('status', $status);
        }

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(static fn ($value): int => (int) trim($value))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }

        $filename = 'lms-course-codes-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['code', 'course', 'batch', 'status', 'used_by', 'used_by_email', 'used_at', 'created_at']);

            $names = [];

            $query->chunk(500, function ($codes) use ($out, &$names): void {
                $ids = $codes->pluck('used_by')->filter()->unique()->values()->all();

                if ($ids !== []) {
                    $missing = array_values(array_diff($ids, array_keys($names)));

                    if ($missing !== []) {
                        foreach (DB::table('users')->whereIn('id', $missing)->get(['id', 'name', 'email']) as $user) {
                            $names[(int) $user->id] = [(string) $user->name, (string) $user->email];
                        }
                    }
                }

                foreach ($codes as $code) {
                    $user = $code->used_by ? ($names[(int) $code->used_by] ?? ['', '']) : ['', ''];

                    fputcsv($out, [
                        (string) $code->code,
                        (string) ($code->course?->title ?? ''),
                        (string) ($code->batch_id ?? ''),
                        (string) $code->status,
                        $user[0],
                        $user[1],
                        $code->used_at?->format('Y-m-d H:i') ?? '',
                        $code->created_at?->format('Y-m-d H:i') ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function codeIssuerSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->whereDoesntHave('permissions', fn ($query) => $query->where('name', 'lms.codes.generate'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(fn ($builder) => $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return response()->json(['data' => $users]);
    }

    public function grantCodeIssuer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'lms.codes.generate', 'guard_name' => 'web']);

        $user = User::query()->findOrFail($validated['user_id']);
        $user->givePermissionTo('lms.codes.generate');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'ØªÙ… Ù…Ù†Ø­ '.$user->name.' ØµÙ„Ø§Ø­ÙŠØ© Ø¥ØµØ¯Ø§Ø± Ø£ÙƒÙˆØ§Ø¯ Ø§Ù„Ø®ØµÙ….');
    }

    public function revokeCodeIssuer(Request $request, User $user): RedirectResponse
    {
        $user->revokePermissionTo('lms.codes.generate');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'ØªÙ… Ø³Ø­Ø¨ ØµÙ„Ø§Ø­ÙŠØ© Ø¥ØµØ¯Ø§Ø± Ø§Ù„Ø£ÙƒÙˆØ§Ø¯ Ù…Ù† '.$user->name.'.');
    }

    public function lessons(Request $request): View
    {
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $query = Lesson::query()->with(['course', 'author']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('module_title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        if ($courseId = (int) $request->integer('course_id')) {
            $query->where('course_id', $courseId);
        }

        if ($author = trim((string) $request->query('author', ''))) {
            $query->whereHas('author', function ($builder) use ($author): void {
                $builder->where('name', $author);
            });
        }

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        match ((string) $request->query('sort', 'newest')) {
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title'),
            'order' => $query->orderBy('course_id')->orderBy('lesson_order'),
            default => $query->latest(),
        };

        $lessons = $query
            ->paginate($perPage)
            ->withQueryString();

        $courses = Course::query()
            ->orderBy('title')
            ->get(['id', 'title', 'instructor_name', 'instructor_title']);

        $filters = [
            'courses' => $courses,
            'authors' => User::query()
                ->whereIn('id', Lesson::query()->whereNotNull('created_by')->select('created_by'))
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->values(),
            'statuses' => ['draft', 'scheduled', 'published', 'archived'],
            'sorts' => [
                'newest' => __('lms::ui.admin_sort_newest'),
                'oldest' => __('lms::ui.admin_sort_oldest'),
                'title' => __('lms::ui.admin_sort_title'),
                'order' => __('lms::ui.admin_sort_order'),
            ],
        ];

        return view('lms::admin.lessons.index', [
            'lessons' => $lessons,
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }

    public function createLesson(): View
    {
        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $locale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode();

        return view('lms::admin.lessons.create', [
            'lesson' => null,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'authors' => collect(),
            'formOptions' => $this->lessonFormOptions(),
            'locale' => $locale,
            'locales' => $locales,
            'translation' => null,
        ]);
    }

    public function draftLessons(Request $request): View
    {
        $request->query->set('status', 'draft');

        return $this->lessons($request);
    }

    public function storeLesson(Request $request): RedirectResponse|JsonResponse
    {
        $this->prepareLessonContentType($request);
        $action = (string) $request->input('action', 'draft');

        if ($action === 'autosave') {
            return $this->autosaveLesson($request);
        }

        if ($action === 'save_draft') {
            $lesson = $this->savePartialLessonDraft($request);
            $switchLocale = (string) $request->input('switch_locale', '');
            $params = ['lesson' => $lesson];
            if ($switchLocale !== '' && array_key_exists($switchLocale, app(PlatformLocale::class)->languages())) {
                $params['locale'] = $switchLocale;
            }

            return redirect()->route('admin.plugins.lms.lessons.edit', $params)
                ->with('status', 'Lesson draft saved successfully.');
        }

        $validated = $this->validateLessonRequest($request);

        $status = match ($action) {
            'publish' => 'published',
            'preview' => 'draft',
            default => $validated['status'] ?? 'draft',
        };

        $lesson = $this->draftLessonFromRequest($request) ?? new Lesson;
        $this->saveLessonFromRequest($lesson, $validated, $request, $status);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'lesson' => $this->lessonEditorPayload($lesson->fresh(['author'])),
            ]);
        }

        if ($action === 'preview') {
            return redirect()->route('admin.plugins.lms.lessons.preview', $lesson)->with('status', 'Lesson draft saved for preview.');
        }

        return redirect()->route('admin.plugins.lms.lessons')->with('status', $status === 'published' ? 'Lesson published successfully.' : 'Lesson draft saved successfully.');
    }

    private function autosaveLesson(Request $request): JsonResponse
    {
        try {
            $lesson = $this->savePartialLessonDraft($request);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->errors()['title'][0] ?? 'Add a lesson title before saving the draft.',
            ], 422);
        }

        return response()->json([
            'lesson_id' => $lesson->id,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    private function savePartialLessonDraft(Request $request, ?Lesson $existingLesson = null): Lesson
    {
        $lesson = $existingLesson ?? $this->draftLessonFromRequest($request) ?? new Lesson;
        $title = trim((string) $request->input('title', '')) ?: ($lesson->title ?: 'Untitled lesson');
        $courseId = $request->integer('course_id');

        if ($courseId > 0 && ! Course::query()->whereKey($courseId)->exists()) {
            throw ValidationException::withMessages([
                'course_id' => 'Select a valid course before saving the draft.',
            ]);
        }

        $lessonType = $this->nullableDraftValue($request->input('lesson_type')) ?: ($lesson->lesson_type ?: 'video');
        if (! in_array($lessonType, ['video', 'text', 'pdf'], true)) {
            $lessonType = 'video';
        }
        $availability = (string) ($this->nullableDraftValue($request->input('availability')) ?: ($lesson->availability ?: 'immediate'));
        if (! in_array($availability, ['immediate', 'scheduled', 'after_previous'], true)) {
            $availability = 'immediate';
        }
        $prerequisiteLessonId = $availability === 'after_previous' && $request->integer('prerequisite_lesson_id') > 0
            ? $request->integer('prerequisite_lesson_id')
            : null;

        $lesson->fill([
            'course_id' => $courseId > 0 ? $courseId : null,
            'module_title' => $this->nullableDraftValue($request->input('module_title')),
            'module_order' => max(1, $request->integer('module_order', $lesson->module_order ?: 1)),
            'lesson_order' => max(1, $request->integer('lesson_order', $lesson->lesson_order ?: 1)),
            'title' => $title,
            'slug' => $this->uniqueLessonSlug((string) $request->input('slug', ''), $title, $lesson),
            'description' => $this->nullableDraftValue($request->input('description')),
            'content' => $this->nullableDraftValue($request->input('content')),
            'status' => 'draft',
            'review_status' => 'none',
            'publish_date' => $request->input('publish_date') ?: null,
            'published_at' => null,
            'visibility' => $this->nullableDraftValue($request->input('visibility')) ?: ($lesson->visibility ?: 'enrolled'),
            'duration_hours' => max(0, $request->integer('duration_hours', $lesson->duration_hours ?: 0)),
            'duration_minutes' => min(59, max(0, $request->integer('duration_minutes', $lesson->duration_minutes ?: 30))),
            'availability' => $availability,
            'prerequisite_lesson_id' => $prerequisiteLessonId,
            'access' => $this->nullableDraftValue($request->input('access')) ?: ($lesson->access ?: 'enrolled'),
            'completion_requirement' => in_array($request->input('completion_requirement'), ['manual', 'video', 'content'], true)
                ? $request->input('completion_requirement')
                : ($lesson->completion_requirement ?: 'manual'),
            'lesson_type' => $lessonType,
            'video_url' => $this->nullableDraftValue($request->input('video_url')),
            'free_preview' => $request->boolean('free_preview'),
            'resources' => $this->cleanResources((array) $request->input('resources', [])),
            'updated_by' => Auth::id(),
        ]);

        if (! $lesson->exists) {
            $lesson->created_by = Auth::id();
        } elseif ($request->integer('author_id') > 0 && User::query()->whereKey($request->integer('author_id'))->exists()) {
            $lesson->created_by = $request->integer('author_id');
        }

        if ($request->boolean('remove_featured_image')) {
            $lesson->featured_image_path = null;
            $lesson->featured_image_name = null;
            $lesson->featured_image_meta = null;
        } elseif ($request->filled('featured_image_media_url')) {
            $mediaPath = $this->publicStoragePathFromUrl((string) $request->input('featured_image_media_url'));

            if ($mediaPath) {
                $lesson->featured_image_path = $mediaPath;
                $lesson->featured_image_name = $this->nullableDraftValue($request->input('featured_image_media_name')) ?: basename($mediaPath);
                $lesson->featured_image_meta = [
                    'source' => 'media_library',
                    'url' => $this->publicStorageUrlFromPath($mediaPath),
                ];
            }
        }

        $lesson->save();

        $newAttachments = collect($this->storeLessonAttachments($request))
            ->concat($this->storeLessonPdf($request))
            ->unique('path')
            ->values()
            ->all();
        $retainedAttachments = $this->retainedLessonAttachments($lesson, $request);
        if ($newAttachments !== [] || $request->filled('remove_attachment_paths')) {
            $lesson->attachments = collect(array_merge($retainedAttachments, $newAttachments))
                ->unique('path')
                ->values()
                ->all();
            $lesson->save();
        }

        $this->syncModuleOrder($lesson);

        return $lesson;
    }

    private function draftLessonFromRequest(Request $request): ?Lesson
    {
        $draftId = $request->integer('draft_lesson_id');

        if ($draftId < 1) {
            return null;
        }

        return Lesson::query()->whereKey($draftId)->where('status', 'draft')->first();
    }

    public function editLesson(Request $request, Lesson $lesson): View
    {
        $lesson->loadMissing('translations');
        $locales = app(PlatformLocale::class)->languages();
        $settingsLocale = (string) app()->getLocale();
        $defaultLocale = array_key_exists($settingsLocale, $locales) ? $settingsLocale : app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode();
        $locale = (string) $request->query('locale', $defaultLocale);
        if (! array_key_exists($locale, $locales)) {
            $locale = $defaultLocale;
        }

        $translation = null;
        $display = $lesson;

        if ($locale !== app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode()) {
            $translation = LessonTranslation::resolve($lesson, $locale);
            $display = clone $lesson;
            $display->title = $translation?->title ?? $lesson->title;
            $display->slug = $translation?->slug ?? $lesson->slug;
            $display->description = $translation?->description ?? $lesson->description;
            $display->content = $translation?->content ?? $lesson->content;
        }

        return view('lms::admin.lessons.create', [
            'lesson' => $display,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'authors' => User::query()->orderBy('name')->get(['id', 'name']),
            'formOptions' => $this->lessonFormOptions(),
            'locale' => $locale,
            'locales' => $locales,
            'translation' => $translation,
            'baseLesson' => $lesson,
        ]);
    }

    public function updateLesson(Request $request, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $this->prepareLessonContentType($request);
        $locale = (string) $request->input('locale', app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode());
        if ($locale !== app(\App\Platform\Core\Localization\PlatformLocale::class)->defaultCode() && array_key_exists($locale, app(PlatformLocale::class)->languages())) {
            return $this->updateLessonTranslation($request, $lesson, $locale);
        }
        $action = (string) $request->input('action', 'draft');

        if ($action === 'save_draft') {
            $this->savePartialLessonDraft($request, $lesson);

            return redirect()->route('admin.plugins.lms.lessons.edit', $lesson)
                ->with('status', 'Lesson draft saved successfully.');
        }

        $validated = $this->validateLessonRequest($request, $lesson);

        $status = match ($action) {
            'publish' => 'published',
            'preview' => $validated['status'] ?? $lesson->status ?? 'draft',
            default => $validated['status'] ?? $lesson->status ?? 'draft',
        };

        $this->saveLessonFromRequest($lesson, $validated, $request, $status);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'lesson' => $this->lessonEditorPayload($lesson->fresh(['author'])),
            ]);
        }

        if ($action === 'preview') {
            return redirect()->route('admin.plugins.lms.lessons.preview', $lesson)->with('status', 'Lesson saved for preview.');
        }

        return redirect()->route('admin.plugins.lms.lessons.edit', $lesson)->with('status', 'Lesson updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLessonRequest(Request $request, ?Lesson $lesson = null): array
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('lms_courses', 'id')],
            'module_title' => ['required', 'string', 'max:160'],
            'module_order' => ['required', 'integer', 'min:1', 'max:10000'],
            'lesson_order' => ['required', 'integer', 'min:1', 'max:10000'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('lms_lessons', 'slug')->ignore($lesson?->id)],
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
            'author_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'video_url' => ['nullable', 'url', 'max:255'],
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

        $validated['prerequisite_lesson_id'] = $this->validatedPrerequisiteLessonId($validated, $lesson);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveLessonFromRequest(Lesson $lesson, array $validated, Request $request, string $status): void
    {
        $isNew = ! $lesson->exists;
        $lessonOrder = $isNew
            ? $this->nextLessonOrder((int) $validated['course_id'], (string) $validated['module_title'])
            : (int) $validated['lesson_order'];

        $lesson->fill([
            'course_id' => $validated['course_id'],
            'module_title' => $validated['module_title'],
            'module_order' => $validated['module_order'],
            'lesson_order' => $lessonOrder,
            'title' => $validated['title'],
            'slug' => $this->uniqueLessonSlug((string) ($validated['slug'] ?? ''), (string) $validated['title'], $lesson),
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'] ?? null,
            'status' => $status,
            'review_status' => $status === 'published' ? 'approved' : 'none',
            'publish_date' => $validated['publish_date'] ?? null,
            'published_at' => $status === 'published' ? ($lesson->published_at ?: now()) : null,
            'visibility' => $validated['visibility'],
            'duration_hours' => $validated['duration_hours'],
            'duration_minutes' => $validated['duration_minutes'],
            'availability' => $validated['availability'],
            'prerequisite_lesson_id' => $validated['prerequisite_lesson_id'],
            'access' => $validated['access'],
            'completion_requirement' => $validated['completion_requirement'],
            'lesson_type' => $validated['lesson_type'],
            'video_url' => $validated['video_url'] ?? null,
            'free_preview' => $request->boolean('free_preview'),
            'resources' => $this->cleanResources($validated['resources'] ?? []),
            'updated_by' => Auth::id(),
        ]);

        if ($validated['lesson_type'] === 'video' && ! empty($validated['video_url'])) {
            $lesson->video_path = $this->protectPlatformVideo($validated['video_url']);
        } elseif ($validated['lesson_type'] !== 'video') {
            $lesson->video_path = null;
        }

        if ($isNew) {
            $lesson->created_by = Auth::id();
        } elseif (! empty($validated['author_id'])) {
            $lesson->created_by = (int) $validated['author_id'];
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
        } elseif (! empty($validated['featured_image_media_url'])) {
            $mediaPath = $this->publicStoragePathFromUrl((string) $validated['featured_image_media_url']);

            if ($mediaPath) {
                $lesson->featured_image_path = $mediaPath;
                $lesson->featured_image_name = $validated['featured_image_media_name'] ?: basename($mediaPath);
                $lesson->featured_image_meta = [
                    'source' => 'media_library',
                    'url' => $this->publicStorageUrlFromPath($mediaPath),
                ];
            }
        }

        if ($request->hasFile('video_file')) {
            $lesson->video_path = 'private:'.$request->file('video_file')->store('lms/protected-videos', 'local');
        }

        $newAttachments = collect($this->storeLessonAttachments($request))
            ->concat($this->storeLessonPdf($request))
            ->unique('path')
            ->values()
            ->all();
        $retainedAttachments = $isNew ? [] : $this->retainedLessonAttachments($lesson, $request);
        if ($isNew || $newAttachments !== [] || $request->filled('remove_attachment_paths')) {
            $lesson->attachments = collect(array_merge($retainedAttachments, $newAttachments))
                ->unique('path')
                ->values()
                ->all();
        }

        $lesson->save();
        $this->syncModuleOrder($lesson);
    }

    /**
     * Keep stored files unless the editor explicitly removes them from this lesson.
     * Physical files remain in storage so rollback and other references stay safe.
     *
     * @return array<int, array<string, mixed>>
     */
    private function retainedLessonAttachments(Lesson $lesson, Request $request): array
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

    private function uniqueLessonSlug(string $slug, string $title, ?Lesson $lesson = null): string
    {
        $base = Str::slug($slug !== '' ? $slug : $this->transliterateArabic($title));

        if ($base === '') {
            $base = 'lesson-'.now()->format('YmdHis');
        }

        $candidate = $base;
        $counter = 2;

        while (Lesson::query()
            ->where('slug', $candidate)
            ->when($lesson?->id, fn ($query) => $query->whereKeyNot($lesson->id))
            ->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    public function previewLesson(Lesson $lesson): View
    {
        $lesson->load('course');
        $course = $lesson->course;
        $lessons = $course
            ? $course->lessons()->whereNull('deleted_at')->orderBy('module_order')->orderBy('lesson_order')->get()
            : collect([$lesson]);

        if (! $lessons->contains(fn (Lesson $item): bool => (int) $item->id === (int) $lesson->id)) {
            $lessons->push($lesson);
        }

        $lessonIndex = max(0, (int) $lessons->search(fn (Lesson $item): bool => (int) $item->id === (int) $lesson->id));
        $attachments = collect((array) ($lesson->attachments ?: []))
            ->map(function (mixed $resource): array {
                if (is_string($resource)) {
                    return ['title' => basename($resource), 'type' => 'FILE', 'size' => '', 'url' => $resource];
                }

                $path = (string) ($resource['path'] ?? '');

                return [
                    'title' => (string) ($resource['file_name'] ?? $resource['name'] ?? basename($path) ?: 'Lesson resource'),
                    'type' => strtoupper((string) ($resource['type'] ?? pathinfo($path, PATHINFO_EXTENSION) ?: 'FILE')),
                    'size' => isset($resource['size']) ? round(((int) $resource['size']) / 1024, 1).' KB' : '',
                    'url' => $path ? Storage::disk('public')->url($path) : (string) ($resource['url'] ?? '#'),
                ];
            })
            ->values()
            ->all();

        return view('lms::frontend.course-player', [
            'course' => $course,
            'lessons' => $lessons,
            'sections' => $lessons->groupBy(fn (Lesson $item): string => (string) ($item->module_title ?: 'Course Lessons')),
            'currentLesson' => $lesson,
            'previousLesson' => $lessonIndex > 0 ? $lessons->get($lessonIndex - 1) : null,
            'nextLesson' => $lessons->get($lessonIndex + 1),
            'lessonIndex' => $lessonIndex,
            'imageUrl' => $course?->featured_image_path ? Storage::disk('public')->url($course->featured_image_path) : null,
            'totalDuration' => '',
            'resources' => $attachments,
            'completedSeed' => collect(),
            'progressPercent' => 0,
        ]);
    }

    public function bulkLessons(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lesson_ids' => ['required', 'array', 'min:1'],
            'lesson_ids.*' => ['integer', Rule::exists('lms_lessons', 'id')],
            'bulk_action' => ['required', Rule::in(['publish', 'draft', 'change_course', 'delete'])],
            'target_course_id' => ['nullable', 'integer', Rule::exists('lms_courses', 'id')],
        ]);

        if ($validated['bulk_action'] === 'change_course' && empty($validated['target_course_id'])) {
            return back()->withErrors(['target_course_id' => 'Select a target course before changing selected lessons.']);
        }

        $lessons = Lesson::query()->whereIn('id', $validated['lesson_ids']);
        $count = (clone $lessons)->count();

        match ($validated['bulk_action']) {
            'publish' => $lessons->get()->each(fn (Lesson $lesson) => $lesson->forceFill([
                'status' => 'published',
                'review_status' => 'approved',
                'published_at' => $lesson->published_at ?: now(),
                'updated_by' => Auth::id(),
            ])->save()),
            'draft' => $lessons->update([
                'status' => 'draft',
                'review_status' => 'none',
                'published_at' => null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]),
            'change_course' => $lessons->update([
                'course_id' => $validated['target_course_id'],
                'availability' => 'immediate',
                'prerequisite_lesson_id' => null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]),
            'delete' => $lessons->get()->each(fn (Lesson $lesson) => $lesson->delete()),
        };

        return redirect()
            ->route('admin.plugins.lms.lessons')
            ->with('status', $count.' lesson(s) updated successfully.');
    }


    protected function updateLessonTranslation(Request $request, Lesson $lesson, string $locale): RedirectResponse
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
            'updated_by' => Auth::id(),
        ]);

        if (! $translation->exists) {
            $translation->created_by = Auth::id();
        }

        $translation->save();

        return redirect()->route('admin.plugins.lms.lessons.edit', ['lesson' => $lesson, 'locale' => $locale])
            ->with('status', __('lms::ui.admin_lesson_translation_saved'));
    }

    public function updateLessonStatus(Request $request, Lesson $lesson): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'scheduled', 'published', 'archived'])],
        ]);

        $lesson->forceFill([
            'status' => $validated['status'],
            'review_status' => $validated['status'] === 'published' ? 'approved' : 'none',
            'published_at' => $validated['status'] === 'published' ? ($lesson->published_at ?: now()) : null,
            'updated_by' => Auth::id(),
        ])->save();

        $course = $lesson->course;
        if ($course) {
            app(LmsNotificationService::class)->notifySubmissionDecision($course, $validated['status'], $lesson);
        }

        return back()->with('status', 'Lesson status updated successfully.');
    }

    public function deleteLesson(Request $request, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $lesson->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('admin.plugins.lms.lessons')
            ->with('status', 'Lesson deleted successfully.');
    }

    public function students(Request $request): View
    {
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $query = $this->studentIndexQuery($request);

        $students = $query
            ->latest('registered_at')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('lms::admin.students.index', [
            'students' => $students,
            'filters' => $this->studentFilters(),
            'perPage' => $perPage,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function createStudent(): View
    {
        return view('lms::admin.students.form', [
            'student' => null,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $validated = $this->validateStudent($request);
        $courseIds = $this->normalizeCourseIds($validated['enrolled_course_ids'] ?? []);

        $student = new Student;
        $student->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'registered_at' => $validated['registered_at'] ?? now(),
            'enrolled_course_ids' => $courseIds,
            'enrolled_courses_count' => count($courseIds),
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->storeStudentAvatar($request, $student);
        $student->save();

        return redirect()->route('admin.plugins.lms.students')->with('status', 'Student created successfully.');
    }

    public function showStudent(Student $student): View
    {
        return view('lms::admin.students.show', [
            'student' => $student,
            'courses' => $this->studentCoursesFor($student),
        ]);
    }

    public function studentCourses(Student $student): View
    {
        return view('lms::admin.students.courses', [
            'student' => $student,
            'courses' => $this->studentCoursesFor($student),
        ]);
    }

    public function editStudent(Student $student): View
    {
        return view('lms::admin.students.form', [
            'student' => $student,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function updateStudent(Request $request, Student $student): RedirectResponse
    {
        $validated = $this->validateStudent($request, $student);
        $courseIds = $this->normalizeCourseIds($validated['enrolled_course_ids'] ?? []);

        $student->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'registered_at' => $validated['registered_at'] ?? $student->registered_at,
            'enrolled_course_ids' => $courseIds,
            'enrolled_courses_count' => count($courseIds),
            'notes' => $validated['notes'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        $this->storeStudentAvatar($request, $student);
        $student->save();

        return redirect()->route('admin.plugins.lms.students')->with('status', 'Student updated successfully.');
    }

    public function bulkStudents(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', Rule::exists('lms_students', 'id')],
            'bulk_action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ]);

        $students = Student::query()->whereIn('id', $validated['student_ids']);
        $count = (clone $students)->count();

        $updated = match ($validated['bulk_action']) {
            'activate' => $students->update([
                'status' => 'active',
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]),
            'deactivate' => $students->update([
                'status' => 'inactive',
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]),
            'delete' => DB::transaction(function () use ($students): int {
                $selected = $students->get();
                foreach ($selected as $student) {
                    $this->deleteStudentRecord($student);
                }

                return $selected->count();
            }),
        };

        $message = match ($validated['bulk_action']) {
            'activate' => $updated.' student(s) activated. Pending invitations remain awaiting acceptance.',
            'deactivate' => $updated.' student(s) deactivated. Pending invitations remain awaiting acceptance.',
            'delete' => $count.' student(s), related enrollments, and course progress deleted. User accounts were preserved.',
        };

        return redirect()
            ->route('admin.plugins.lms.students')
            ->with('status', $message);
    }

    public function updateStudentStatus(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $student->forceFill([
            'status' => $validated['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return back()->with('status', 'Student status updated successfully.');
    }

    public function deleteStudent(Student $student): RedirectResponse
    {
        DB::transaction(fn () => $this->deleteStudentRecord($student));

        return redirect()->route('admin.plugins.lms.students')->with('status', 'Student, enrollments, and course progress deleted. The user account was preserved.');
    }

    private function deleteStudentRecord(Student $student): void
    {
        if ($student->user_id) {
            DB::table('lms_lesson_progress')->where('user_id', $student->user_id)->delete();
            CourseEnrollment::query()->where('user_id', $student->user_id)->delete();
        }

        Invitation::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($student->email)])
            ->where('role', 'student')
            ->whereIn('status', ['pending', 'sent', 'failed'])
            ->update([
                'status' => 'cancelled',
                'last_error' => 'Cancelled because the student record was deleted by an administrator.',
                'updated_at' => now(),
            ]);

        $student->delete();
    }

    public function exportStudents(Request $request): StreamedResponse
    {
        $fileName = 'lms-students-'.now()->format('Ymd-His').'.csv';
        $students = $this->studentIndexQuery($request)->orderBy('name')->get();

        return response()->streamDownload(function () use ($students): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Status', 'Enrolled Courses', 'Registration Date']);

            foreach ($students as $student) {
                fputcsv($handle, [
                    $student->name,
                    $student->email,
                    $student->phone,
                    $student->status,
                    $student->enrolled_courses_count,
                    optional($student->registered_at)->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewData(Request $request): array
    {
        $range = $this->overviewRange($request);
        $totalStudents = Student::query()->count();
        $activeCourses = Course::query()->where('status', 'published')->count();
        $totalCourses = Course::query()->count();
        $publishedLessons = Lesson::query()->where('status', 'published')->count();
        $totalLessons = Lesson::query()->count();
        $completionRate = $totalLessons > 0 ? (int) round(($publishedLessons / $totalLessons) * 100) : 0;

        $recentStudents = Student::query()
            ->where('registered_at', '>=', now()->subDays($range['days']))
            ->count();

        $stats = [
            'students' => [
                'label' => __('lms::ui.admin_overview_stat_students'),
                'value' => $totalStudents,
                'display' => number_format($totalStudents),
                'note' => $recentStudents > 0 ? __('lms::ui.admin_overview_note_new_students', ['count' => number_format($recentStudents)]) : __('lms::ui.admin_overview_note_no_new_students'),
                'tone' => 'purple',
                'icon' => 'users',
            ],
            'courses' => [
                'label' => __('lms::ui.admin_overview_stat_active_courses'),
                'value' => $activeCourses,
                'display' => number_format($activeCourses),
                'note' => __('lms::ui.admin_overview_note_total_courses', ['count' => number_format($totalCourses)]),
                'tone' => 'green',
                'icon' => 'book',
            ],
            'lessons' => [
                'label' => __('lms::ui.admin_overview_stat_published_lessons'),
                'value' => $publishedLessons,
                'display' => number_format($publishedLessons),
                'note' => __('lms::ui.admin_overview_note_total_lessons', ['count' => number_format($totalLessons)]),
                'tone' => 'blue',
                'icon' => 'play',
            ],
            'completion' => [
                'label' => __('lms::ui.admin_overview_stat_completion'),
                'value' => $completionRate,
                'display' => $completionRate.'%',
                'note' => __('lms::ui.admin_overview_note_published_ratio'),
                'tone' => 'orange',
                'icon' => 'trend',
            ],
        ];

        return [
            'range' => $range,
            'stats' => $stats,
            'chart' => $this->overviewEnrollmentSeries($range),
            'courseStatus' => $this->overviewCourseStatus(),
            'topCourses' => $this->overviewTopCourses(),
            'recentEnrollments' => $this->overviewRecentEnrollments(),
            'upcomingLiveClasses' => $this->overviewUpcomingLiveClasses(),
            'recentActivity' => $this->overviewRecentActivity(),
        ];
    }

    /**
     * @return array{key: string, label: string, days: int, bucket: string}
     */
    private function overviewRange(Request $request): array
    {
        return match ((string) $request->query('range', '30d')) {
            '7d' => ['key' => '7d', 'label' => __('lms::ui.admin_overview_range_7d_label'), 'days' => 7, 'bucket' => 'day'],
            '12m' => ['key' => '12m', 'label' => __('lms::ui.admin_overview_range_12m_label'), 'days' => 365, 'bucket' => 'month'],
            default => ['key' => '30d', 'label' => __('lms::ui.admin_overview_range_30d_label'), 'days' => 30, 'bucket' => 'day'],
        };
    }

    /**
     * @param  array{key: string, label: string, days: int, bucket: string}  $range
     * @return array<string, mixed>
     */
    private function overviewEnrollmentSeries(array $range): array
    {
        $labels = [];
        $students = [];
        $enrollments = [];

        if ($range['bucket'] === 'month') {
            for ($index = 11; $index >= 0; $index--) {
                $date = now()->subMonths($index);
                $labels[] = $date->translatedFormat('M');
                $students[] = Student::query()
                    ->whereYear('registered_at', $date->year)
                    ->whereMonth('registered_at', $date->month)
                    ->count();
                $enrollments[] = (int) Student::query()
                    ->whereYear('registered_at', $date->year)
                    ->whereMonth('registered_at', $date->month)
                    ->sum('enrolled_courses_count');
            }
        } else {
            $points = $range['key'] === '7d' ? 7 : 10;
            $step = max(1, (int) floor($range['days'] / $points));

            for ($index = $points - 1; $index >= 0; $index--) {
                $from = now()->subDays($index * $step)->startOfDay();
                $to = (clone $from)->addDays($step - 1)->endOfDay();
                $labels[] = $from->translatedFormat('M d');
                $students[] = Student::query()->whereBetween('registered_at', [$from, $to])->count();
                $enrollments[] = (int) Student::query()->whereBetween('registered_at', [$from, $to])->sum('enrolled_courses_count');
            }
        }

        $max = max([1, ...$students, ...$enrollments]);

        return [
            'labels' => $labels,
            'students' => $students,
            'enrollments' => $enrollments,
            'studentsPoints' => $this->overviewChartPoints($students, $max),
            'enrollmentPoints' => $this->overviewChartPoints($enrollments, $max),
            'max' => $max,
        ];
    }

    /**
     * @param  array<int, int>  $values
     */
    private function overviewChartPoints(array $values, int $max): string
    {
        $count = count($values);

        if ($count === 0) {
            return '';
        }

        $width = 520;
        $height = 150;
        $usableHeight = 126;
        $step = $count > 1 ? $width / ($count - 1) : 0;

        return collect($values)
            ->map(function (int $value, int $index) use ($max, $height, $usableHeight, $step): string {
                $x = round($index * $step, 2);
                $y = round($height - (($value / max($max, 1)) * $usableHeight) - 8, 2);

                return $x.','.$y;
            })
            ->implode(' ');
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewCourseStatus(): array
    {
        $statuses = [
            'published' => ['label' => __('lms::ui.admin_status_published'), 'color' => '#22c55e'],
            'draft' => ['label' => __('lms::ui.admin_status_draft'), 'color' => '#f59e0b'],
            'scheduled' => ['label' => __('lms::ui.admin_status_scheduled'), 'color' => '#3b82f6'],
            'archived' => ['label' => __('lms::ui.admin_status_archived'), 'color' => '#94a3b8'],
        ];

        $total = Course::query()->count();
        $segments = [];
        $cursor = 0;
        $gradient = [];

        foreach ($statuses as $status => $meta) {
            $count = Course::query()->where('status', $status)->count();
            $percent = $total > 0 ? (int) round(($count / $total) * 100) : 0;
            $end = $cursor + $percent;

            if ($percent > 0) {
                $gradient[] = $meta['color'].' '.$cursor.'% '.$end.'%';
            }

            $segments[] = [
                'status' => $status,
                'label' => $meta['label'],
                'count' => $count,
                'percent' => $percent,
                'color' => $meta['color'],
            ];

            $cursor = $end;
        }

        return [
            'total' => $total,
            'segments' => $segments,
            'donutStyle' => $gradient === [] ? 'background: #eef2f7;' : 'background: conic-gradient('.implode(', ', $gradient).');',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overviewTopCourses(): array
    {
        return Course::query()
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (Course $course): array {
                $lessonCount = Lesson::query()->where('course_id', $course->id)->count();
                $publishedLessonCount = Lesson::query()->where('course_id', $course->id)->where('status', 'published')->count();
                $students = Student::query()->whereJsonContains('enrolled_course_ids', $course->id)->count();

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'code' => '#CRS-'.str_pad((string) $course->id, 4, '0', STR_PAD_LEFT),
                    'instructor' => $course->instructor_name ?: __('lms::ui.admin_overview_unassigned'),
                    'instructor_title' => $course->instructor_title ?: __('lms::ui.admin_overview_instructor_role'),
                    'students' => $students,
                    'completion' => $lessonCount > 0 ? (int) round(($publishedLessonCount / $lessonCount) * 100) : 0,
                    'status' => $course->status,
                    'image' => $course->featured_image_path ? Storage::disk('public')->url($course->featured_image_path) : null,
                ];
            })
            ->sortByDesc('students')
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overviewRecentEnrollments(): array
    {
        return Student::query()
            ->latest('registered_at')
            ->limit(5)
            ->get()
            ->map(function (Student $student): array {
                $course = $this->studentCoursesFor($student)->first();

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'course' => $course?->title ?? __('lms::ui.admin_overview_no_course_assigned'),
                    'status' => $student->status,
                    'date' => optional($student->registered_at)->translatedFormat('M d, Y') ?: optional($student->created_at)->translatedFormat('M d, Y'),
                    'avatar' => $student->avatar_path ? Storage::disk('public')->url($student->avatar_path) : null,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overviewUpcomingLiveClasses(): array
    {
        return Lesson::query()
            ->with('course')
            ->where('lesson_type', 'live')
            ->whereIn('status', ['published', 'scheduled'])
            ->orderByRaw('COALESCE(publish_date, created_at) asc')
            ->limit(4)
            ->get()
            ->map(function (Lesson $lesson): array {
                $students = Student::query()->whereJsonContains('enrolled_course_ids', $lesson->course_id)->count();

                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'course' => $lesson->course?->title ?? __('lms::ui.admin_overview_unassigned_course'),
                    'date' => optional($lesson->publish_date)->translatedFormat('M d, Y') ?: optional($lesson->created_at)->translatedFormat('M d, Y'),
                    'time' => optional($lesson->publish_date)->translatedFormat('h:i A') ?: __('lms::ui.admin_overview_not_scheduled'),
                    'students' => $students,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function overviewRecentActivity(): array
    {
        $courses = Course::query()->latest()->limit(3)->get()->map(fn (Course $course): array => [
            'tone' => 'green',
            'title' => __('lms::ui.admin_overview_activity_new_course', ['title' => $course->title]),
            'meta' => ($course->instructor_name ?: __('lms::ui.admin_overview_system')).' â€¢ '.optional($course->created_at)->diffForHumans(),
            'timestamp' => optional($course->created_at)->getTimestamp() ?? 0,
        ]);

        $lessons = Lesson::query()->with('course')->latest()->limit(3)->get()->map(fn (Lesson $lesson): array => [
            'tone' => 'blue',
            'title' => __('lms::ui.admin_overview_activity_lesson_added', ['title' => $lesson->title]),
            'meta' => ($lesson->course?->title ?? 'LMS').' â€¢ '.optional($lesson->created_at)->diffForHumans(),
            'timestamp' => optional($lesson->created_at)->getTimestamp() ?? 0,
        ]);

        $students = Student::query()->latest()->limit(3)->get()->map(fn (Student $student): array => [
            'tone' => 'purple',
            'title' => __('lms::ui.admin_overview_activity_student_registered', ['name' => $student->name]),
            'meta' => __('lms::ui.admin_overview_system').' â€¢ '.optional($student->created_at)->diffForHumans(),
            'timestamp' => optional($student->created_at)->getTimestamp() ?? 0,
        ]);

        return $courses
            ->merge($lessons)
            ->merge($students)
            ->sortByDesc('timestamp')
            ->take(5)
            ->map(fn (array $activity): array => [
                'tone' => $activity['tone'],
                'title' => $activity['title'],
                'meta' => $activity['meta'],
            ])
            ->values()
            ->all();
    }

    private function studentIndexQuery(Request $request)
    {
        $query = Student::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($courseId = (int) $request->integer('course_id')) {
            $query->whereJsonContains('enrolled_course_ids', $courseId);
        }

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        if ($registrationDate = trim((string) $request->query('registration_date', ''))) {
            $query->whereDate('registered_at', $registrationDate);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function studentFilters(): array
    {
        return [
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'statuses' => ['active', 'inactive'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStudent(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190', Rule::unique('lms_students', 'email')->ignore($student?->id)],
            'phone' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'registered_at' => ['nullable', 'date'],
            'enrolled_course_ids' => ['nullable', 'array'],
            'enrolled_course_ids.*' => ['integer', Rule::exists('lms_courses', 'id')],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'avatar_media_url' => ['nullable', 'string', 'max:2048'],
            'avatar_media_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @param  array<int, mixed>  $courseIds
     * @return array<int, int>
     */
    private function normalizeCourseIds(array $courseIds): array
    {
        return collect($courseIds)
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function storeStudentAvatar(Request $request, Student $student): void
    {
        if ($request->filled('avatar_media_url')) {
            $mediaPath = $this->publicStoragePathFromUrl((string) $request->input('avatar_media_url'));

            if ($mediaPath) {
                $student->avatar_path = $mediaPath;
                $student->avatar_name = trim((string) $request->input('avatar_media_name')) ?: basename($mediaPath);
                $student->avatar_meta = ['source' => 'media_library', 'url' => $this->publicStorageUrlFromPath($mediaPath)];

                return;
            }
        }

        if (! $request->hasFile('avatar')) {
            return;
        }

        $file = $request->file('avatar');
        $student->avatar_path = $file->store('lms/student-avatars', 'public');
        $student->avatar_name = $file->getClientOriginalName();
        $student->avatar_meta = ['size' => $file->getSize(), 'mime' => $file->getMimeType()];
    }

    private function studentCoursesFor(Student $student)
    {
        $courseIds = $this->normalizeCourseIds($student->enrolled_course_ids ?? []);

        if ($courseIds === []) {
            return collect();
        }

        return Course::query()
            ->whereIn('id', $courseIds)
            ->orderBy('title')
            ->get();
    }

    private function page(string $title): View
    {
        return view('lms::admin.page', ['title' => $title]);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function cleanList(array $items): array
    {
        return collect($items)->map(fn (mixed $item): string => trim((string) $item))->filter()->values()->all();
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{question: string, answer: string}>
     */
    private function cleanFaqs(array $items): array
    {
        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'question' => trim((string) ($item['question'] ?? '')),
                'answer' => trim((string) ($item['answer'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['question'] !== '' || $item['answer'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{file_name: string, type: string, size: string, download: bool}>
     */
    private function cleanResources(array $items): array
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storeLessonAttachments(Request $request): array
    {
        $uploads = collect($request->file('attachments', []))
            ->filter()
            ->map(function ($file): array {
                return [
                    'file_name' => $file->getClientOriginalName(),
                    'path' => $file->store('lms/lesson-attachments', 'public'),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'download' => true,
                ];
            });

        $mediaSelections = collect((array) $request->input('media_attachments', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): ?array {
                $url = trim((string) ($item['url'] ?? ''));
                $path = $this->publicStoragePathFromUrl($url);

                if (! $path || ! Storage::disk('public')->exists($path)) {
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

        return $uploads
            ->concat($mediaSelections)
            ->unique('path')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storeLessonPdf(Request $request): array
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

        $path = $this->publicStoragePathFromUrl((string) $request->input('pdf_media_url', ''));
        if (! $path || ! str_ends_with(strtolower($path), '.pdf') || ! Storage::disk('public')->exists($path)) {
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

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function formOptions(?string $locale = null): array
    {
        $formLocale = $locale ?? app()->getLocale();
        $t = static fn (string $key, array $replace = []) => __($key, $replace, $formLocale);
        $categories = collect([
            'Interior Design',
            'Fine Art',
            'Digital Art',
            'Photography',
        ])->merge(
            Course::query()
                ->whereNotNull('category')
                ->pluck('category')
                ->map(fn ($category) => trim((string) $category))
                ->filter()
        )->unique()->values()->map(fn (string $category) => [
            'value' => $category,
            'label' => $category,
        ])->all();

        $instructors = Instructor::query()
            ->with('user:id,email')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get()
            ->map(fn (Instructor $instructor) => [
                'value' => (string) $instructor->getKey(),
                'label' => $instructor->name,
                'email' => (string) ($instructor->user?->email ?: $instructor->email),
            ])
            ->all();

        return [
            'statuses' => [
                ['value' => 'draft', 'label' => $t('lms::ui.admin_course_status_draft')],
                ['value' => 'scheduled', 'label' => $t('lms::ui.admin_course_status_scheduled')],
                ['value' => 'published', 'label' => $t('lms::ui.admin_course_status_published')],
            ],
            'visibility' => [
                ['value' => 'public', 'label' => $t('lms::ui.admin_course_visibility_public')],
                ['value' => 'private', 'label' => $t('lms::ui.admin_course_visibility_private')],
                ['value' => 'unlisted', 'label' => $t('lms::ui.admin_course_visibility_unlisted')],
            ],
            'categories' => $categories,
            'instructors' => $instructors,
            'levels' => [
                ['value' => 'Beginner', 'label' => $t('lms::ui.admin_course_level_beginner')],
                ['value' => 'Intermediate', 'label' => $t('lms::ui.admin_course_level_intermediate')],
                ['value' => 'Advanced', 'label' => $t('lms::ui.admin_course_level_advanced')],
            ],
            'languages' => [
                ['value' => 'English', 'label' => $t('lms::ui.admin_course_language_english')],
                ['value' => 'Arabic', 'label' => $t('lms::ui.admin_course_language_arabic')],
            ],
            'enrollmentTypes' => [
                ['value' => 'open', 'label' => $t('lms::ui.admin_course_enrollment_open')],
                ['value' => 'manual', 'label' => $t('lms::ui.admin_course_enrollment_manual')],
                ['value' => 'invite_only', 'label' => $t('lms::ui.admin_course_enrollment_invite')],
            ],
            'accessDurations' => [
                ['value' => 'lifetime', 'label' => $t('lms::ui.admin_course_duration_lifetime')],
                ['value' => 'one_year', 'label' => $t('lms::ui.admin_course_duration_one_year')],
                ['value' => 'six_months', 'label' => $t('lms::ui.admin_course_duration_six_months')],
            ],
            'progressRules' => [
                ['value' => 'complete_previous', 'label' => $t('lms::ui.admin_course_progress_complete')],
                ['value' => 'free_navigation', 'label' => $t('lms::ui.admin_course_progress_free')],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncModuleOrder(Lesson $lesson): void
    {
        if (! $lesson->course_id || ! $lesson->module_title) {
            return;
        }

        Lesson::query()
            ->where('course_id', $lesson->course_id)
            ->where('module_title', $lesson->module_title)
            ->update(['module_order' => max(1, (int) $lesson->module_order)]);
    }

    private function nextLessonOrder(int $courseId, string $moduleTitle): int
    {
        return ((int) Lesson::query()
            ->where('course_id', $courseId)
            ->where('module_title', trim($moduleTitle))
            ->max('lesson_order')) + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function courseEditorData(?Course $course = null): array
    {
        $lessons = $course
            ? $course->lessons()->with('author:id,name')->get()
            : collect();

        $storedUnits = collect($course?->curriculum_units ?: [])
            ->filter(fn (mixed $unit): bool => is_array($unit) && trim((string) ($unit['title'] ?? '')) !== '')
            ->map(fn (array $unit, int $index): array => [
                'key' => (string) ($unit['key'] ?? 'unit-'.($index + 1)),
                'title' => trim((string) $unit['title']),
                'order' => max(1, (int) ($unit['order'] ?? ($index + 1))),
                'translations' => CurriculumSections::normalize($unit['translations'] ?? [], trim((string) $unit['title'])),
            ]);

        $lessonUnits = $lessons
            ->groupBy(fn (Lesson $lesson): string => trim((string) $lesson->module_title) ?: 'Module 1')
            ->map(function ($moduleLessons, string $title): array {
                $first = $moduleLessons->sortBy('module_order')->first();

                return [
                    'key' => 'module-'.Str::slug($title).'-'.((int) ($first?->module_order ?: 1)),
                    'title' => $title,
                    'order' => max(1, (int) ($first?->module_order ?: 1)),
                    'translations' => [],
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

        return [
            'curriculumUnits' => $units->all(),
            'courseLessons' => $lessons->map(fn (Lesson $lesson): array => $this->lessonEditorPayload($lesson))->all(),
            'lessonAuthors' => User::query()->orderBy('name')->get(['id', 'name']),
            'lessonFormOptions' => $this->lessonFormOptions(),
            'enrolledStudentCount' => $course && Schema::hasTable('lms_course_enrollments')
                ? CourseEnrollment::query()->where('course_id', $course->id)->count()
                : 0,
        ];
    }

    private function prepareLessonContentType(Request $request): void
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

    /**
     * @return array<string, mixed>
     */
    private function lessonEditorPayload(Lesson $lesson): array
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
            'publish_date' => $lesson->publish_date?->format('Y-m-d\\TH:i'),
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

                return [
                    ...$attachment,
                    'url' => $path !== '' ? $this->publicStorageUrlFromPath($path) : null,
                ];
            })->values()->all(),
        ];
    }

    private function lessonFormOptions(): array
    {
        $moduleLessons = Lesson::query()
            ->whereNotNull('course_id')
            ->whereNotNull('module_title')
            ->where('module_title', '!=', '')
            ->orderBy('course_id')
            ->orderBy('module_order')
            ->orderBy('module_title')
            ->get(['course_id', 'module_title', 'module_order']);
        $prerequisiteLessonsByCourse = Lesson::query()
            ->whereNotNull('course_id')
            ->whereNull('deleted_at')
            ->orderBy('course_id')
            ->orderBy('module_order')
            ->orderBy('lesson_order')
            ->orderBy('id')
            ->get(['id', 'course_id', 'title', 'module_title', 'lesson_order'])
            ->groupBy('course_id')
            ->map(fn ($lessons): array => $lessons->map(fn (Lesson $lesson): array => [
                'id' => (int) $lesson->id,
                'title' => $lesson->title,
                'module' => $lesson->module_title,
                'order' => (int) $lesson->lesson_order,
            ])->values()->all())
            ->all();

        $modulesByCourse = $moduleLessons
            ->groupBy('course_id')
            ->map(fn ($lessons): array => $lessons->pluck('module_title')->unique()->values()->all())
            ->all();

        $moduleOrdersByCourse = $moduleLessons
            ->groupBy('course_id')
            ->map(fn ($lessons): array => $lessons
                ->groupBy('module_title')
                ->map(fn ($moduleLessons): int => max(1, (int) $moduleLessons->min('module_order')))
                ->all())
            ->all();

        return [
            'modulesByCourse' => $modulesByCourse,
            'moduleOrdersByCourse' => $moduleOrdersByCourse,
            'prerequisiteLessonsByCourse' => $prerequisiteLessonsByCourse,
            'statuses' => [
                ['value' => 'draft', 'label' => __('lms::ui.admin_course_status_draft')],
                ['value' => 'scheduled', 'label' => __('lms::ui.admin_course_status_scheduled')],
                ['value' => 'published', 'label' => __('lms::ui.admin_course_status_published')],
            ],
            'visibility' => [
                ['value' => 'enrolled', 'label' => __('lms::ui.admin_course_access_enrolled')],
                ['value' => 'public', 'label' => __('lms::ui.admin_course_visibility_public')],
                ['value' => 'private', 'label' => __('lms::ui.admin_course_visibility_private')],
            ],
            'availability' => [
                ['value' => 'immediate', 'label' => __('lms::ui.admin_course_availability_immediate')],
                ['value' => 'scheduled', 'label' => __('lms::ui.admin_course_availability_scheduled')],
                ['value' => 'after_previous', 'label' => __('lms::ui.admin_course_availability_after')],
            ],
            'access' => [
                ['value' => 'enrolled', 'label' => __('lms::ui.admin_course_access_enrolled')],
                ['value' => 'public', 'label' => __('lms::ui.admin_course_access_public')],
                ['value' => 'instructor', 'label' => __('lms::ui.admin_course_access_instructor')],
            ],
            'completion' => [
                ['value' => 'manual', 'label' => 'Mark as Complete Manually', 'help' => 'Students must mark the lesson as complete manually.'],
            ],
            'types' => [
                ['value' => 'video', 'label' => 'Video', 'icon' => 'â–£'],
                ['value' => 'text', 'label' => 'Text', 'icon' => 'â–¤'],
                ['value' => 'pdf', 'label' => 'PDF', 'icon' => 'â–§'],
            ],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function validatedPrerequisiteLessonId(array $validated, ?Lesson $lesson = null): ?int
    {
        if (($validated['availability'] ?? 'immediate') !== 'after_previous') {
            return null;
        }

        $prerequisiteId = (int) ($validated['prerequisite_lesson_id'] ?? 0);
        $isValid = $prerequisiteId > 0
            && (! $lesson || $prerequisiteId !== (int) $lesson->id)
            && Lesson::query()
                ->whereKey($prerequisiteId)
                ->where('course_id', (int) $validated['course_id'])
                ->whereNull('deleted_at')
                ->exists();

        if (! $isValid) {
            throw ValidationException::withMessages([
                'prerequisite_lesson_id' => 'Select a previous lesson from the same course.',
            ]);
        }

        return $prerequisiteId;
    }
}
