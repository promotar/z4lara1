<?php

namespace Modules\Lms\Http\Controllers\Frontend;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\CourseCode;
use Modules\Lms\Models\CourseEnrollment;
use Modules\Lms\Models\CourseTranslation;
use Modules\Lms\Models\Lesson;
use Modules\Lms\Models\LessonProgress;
use Modules\Lms\Models\Student;
use Modules\Lms\Models\Template;
use Modules\Lms\Services\CourseTemplateRenderer;
use Modules\Lms\Services\LessonAvailabilityService;
use Modules\Lms\Services\TemplateSettings;
use Modules\Lms\Support\VideoEmbedResolver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LmsFrontendController extends Controller
{
    public function coursesPage(Request $request): View
    {
        $page = null;

        if (Schema::hasTable('platform_pages')) {
            $page = DB::table('platform_pages')
                ->where('slug', 'courses')
                ->where('content_type', 'page')
                ->where('status', 'published')
                ->first();
        }

        if ($page) {
            return view('frontend.pages.show', [
                'page' => $page,
                'isPreview' => false,
            ]);
        }

        $perPage = max(4, min(24, (int) $request->integer('per_page', 12)));
        $courses = $this->catalogQuery($request)
            ->paginate($perPage)
            ->withQueryString();
        $this->localizeCourses($courses->getCollection());
        $catalogTitle = 'Explore Our Courses';
        $catalogDescription = 'Discover practical courses designed to help you build new skills and achieve your learning goals.';
        $filters = $this->filters();
        $selectedCategory = trim((string) $request->query('category', ''));
        $selectedLevel = trim((string) $request->query('level', ''));
        $selectedSort = trim((string) $request->query('sort', 'newest')) ?: 'newest';
        $hasActiveFilters = trim((string) $request->query('search', '')) !== ''
            || $selectedCategory !== ''
            || $selectedLevel !== ''
            || $selectedSort !== 'newest';
        $template = app(TemplateSettings::class)->catalogTemplate();
        $renderedTemplate = $template
            ? app(CourseTemplateRenderer::class)->render($template, $courses->getCollection(), [
                'catalog_title' => $catalogTitle,
                'catalog_description' => $catalogDescription,
                'results_count' => (string) $courses->total(),
                'home_url' => e(url('/')),
                'teach_url' => e(route('lms.front.instructor-request.create', [], false)),
                'search_query' => e((string) $request->query('search', '')),
                'has_active_filters' => $hasActiveFilters ? '1' : '0',
                'category_options' => $this->catalogSelectOptions($filters['categories'], 'All Categories', $selectedCategory),
                'level_options' => $this->catalogSelectOptions($filters['levels'], 'All Levels', $selectedLevel),
                'sort_options' => $this->catalogSelectOptions($filters['sorts'], null, $selectedSort),
                'empty_state' => $courses->isEmpty()
                    ? '<div class="z4-courses__empty"><h2>No courses found</h2><p>Try changing or clearing the current filters.</p></div>'
                    : '',
                'pagination_html' => $this->catalogPagination($courses),
            ])
            : null;

        return view('lms::frontend.courses', compact(
            'courses',
            'catalogTitle',
            'catalogDescription',
            'renderedTemplate',
        ));
    }

    public function catalog(Request $request): JsonResponse
    {
        $perPage = max(4, min(24, (int) $request->integer('per_page', 8)));
        $page = max(1, (int) $request->integer('page', 1));
        $user = $request->user();

        $query = $this->catalogQuery($request);

        $courses = $query->paginate($perPage, ['*'], 'page', $page);
        $this->localizeCourses($courses->getCollection());
        $featured = $this->publicCoursesQuery()
            ->latest('published_at')
            ->latest('created_at')
            ->limit(4)
            ->get();
        $this->localizeCourses($featured);
        $featured = $featured
            ->map(fn (Course $course): array => $this->coursePayload($course, true, $user))
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'courses' => collect($courses->items())->map(fn (Course $course): array => $this->coursePayload($course, false, $user))->values(),
                'featured' => $featured,
                'filters' => $this->filters(),
                'pagination' => [
                    'current_page' => $courses->currentPage(),
                    'last_page' => $courses->lastPage(),
                    'per_page' => $courses->perPage(),
                    'total' => $courses->total(),
                    'from' => $courses->firstItem() ?: 0,
                    'to' => $courses->lastItem() ?: 0,
                ],
            ],
        ]);
    }

    private function catalogQuery(Request $request): Builder
    {
        $query = $this->publicCoursesQuery();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('subtitle', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhere('instructor_name', 'like', '%'.$search.'%');
            });
        }

        foreach (['category', 'level'] as $field) {
            $value = trim((string) $request->query($field, ''));
            if ($value !== '') {
                $query->where($field, $value);
            }
        }

        $type = trim((string) $request->query('type', ''));
        if (in_array($type, ['free', 'paid'], true)) {
            $query->where('pricing_type', $type);
        }

        $priceMin = $request->query('price_min');
        $priceMax = $request->query('price_max');

        if (is_numeric($priceMin)) {
            $query->where(function (Builder $builder) use ($priceMin): void {
                $builder->whereNull('sale_price')->where('regular_price', '>=', (float) $priceMin)
                    ->orWhere('sale_price', '>=', (float) $priceMin);
            });
        }

        if (is_numeric($priceMax)) {
            $query->where(function (Builder $builder) use ($priceMax): void {
                $builder->whereNull('sale_price')->where('regular_price', '<=', (float) $priceMax)
                    ->orWhere('sale_price', '<=', (float) $priceMax);
            });
        }

        match ((string) $request->query('sort', 'newest')) {
            'price_low' => $query->orderByRaw('COALESCE(sale_price, regular_price, 0) asc')->latest('id'),
            'price_high' => $query->orderByRaw('COALESCE(sale_price, regular_price, 0) desc')->latest('id'),
            'title' => $query->orderBy('title')->latest('id'),
            default => $query->latest('published_at')->latest('created_at')->latest('id'),
        };

        return $query;
    }

    private function publicCoursesQuery(): Builder
    {
        return Course::query()
            ->where('status', 'published')
            ->where(function (Builder $builder): void {
                $builder->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->with(['lessons' => fn ($query) => $query->where('status', 'published')])
            ->withCount('enrollments');
    }

    private function catalogSelectOptions(iterable $items, ?string $allLabel, string $selected): string
    {
        $options = $allLabel !== null
            ? ['<option value="">'.e($allLabel).'</option>']
            : [];

        foreach ($items as $item) {
            $value = is_array($item) ? (string) ($item['value'] ?? '') : (string) $item;
            $label = is_array($item) ? (string) ($item['label'] ?? $value) : (string) $item;
            $selectedAttribute = $value === $selected ? ' selected' : '';
            $options[] = '<option value="'.e($value).'"'.$selectedAttribute.'>'.e($label).'</option>';
        }

        return implode('', $options);
    }

    private function catalogPagination(LengthAwarePaginator $courses): string
    {
        if (! $courses->hasPages()) {
            return '';
        }

        $current = $courses->currentPage();
        $last = $courses->lastPage();
        $start = max(1, $current - 2);
        $end = min($last, $current + 2);
        $links = [];

        $links[] = $courses->onFirstPage()
            ? '<span class="is-disabled" aria-disabled="true">Previous</span>'
            : '<a href="'.e($courses->previousPageUrl()).'" rel="prev">Previous</a>';

        if ($start > 1) {
            $links[] = '<a href="'.e($courses->url(1)).'">1</a>';
            if ($start > 2) {
                $links[] = '<span aria-hidden="true">&hellip;</span>';
            }
        }

        for ($page = $start; $page <= $end; $page++) {
            $links[] = $page === $current
                ? '<span class="is-current" aria-current="page">'.$page.'</span>'
                : '<a href="'.e($courses->url($page)).'">'.$page.'</a>';
        }

        if ($end < $last) {
            if ($end < $last - 1) {
                $links[] = '<span aria-hidden="true">&hellip;</span>';
            }
            $links[] = '<a href="'.e($courses->url($last)).'">'.$last.'</a>';
        }

        $links[] = $courses->hasMorePages()
            ? '<a href="'.e($courses->nextPageUrl()).'" rel="next">Next</a>'
            : '<span class="is-disabled" aria-disabled="true">Next</span>';

        return '<nav class="z4-course-pagination" aria-label="Courses pagination">'.implode('', $links).'</nav>';
    }

    public function courseDetails(Request $request, Course $course): View
    {
        abort_unless($course->status === 'published', 404);
        abort_if($course->visibility && $course->visibility !== 'public', 404);

        $baseSlug = (string) $course->getRawOriginal('slug');
        $translation = CourseTranslation::resolve($course, app()->getLocale());
        $translation?->applyTo($course);

        $courseTranslations = CourseTranslation::query()
            ->where('course_id', $course->id)
            ->pluck('slug', 'locale');

        $seoAlternates = [PlatformLocale::DEFAULT => route('lms.front.courses.show', ['course' => $baseSlug], false)];
        foreach ($courseTranslations as $code => $translatedSlug) {
            $seoAlternates[$code] = route('lms.front.courses.show', ['course' => $translatedSlug], false).'?lang='.urlencode($code);
        }
        $canonicalUrl = $translation
            ? route('lms.front.courses.show', ['course' => $translation->slug]).'?lang='.urlencode(app()->getLocale())
            : route('lms.front.courses.show', ['course' => $baseSlug]);

        $lessons = $course->lessons()
            ->whereNull('deleted_at')
            ->where(function ($builder): void {
                $builder->whereNull('status')->orWhereIn('status', ['published', 'draft']);
            })
            ->get();

        $previewLesson = $lessons->first(fn (Lesson $lesson): bool => (bool) $lesson->free_preview)
            ?: $lessons->first(fn (Lesson $lesson): bool => (bool) $lesson->video_url)
            ?: $lessons->first();
        $isEnrolled = $this->userOwnsCourse($course, $request->user());
        $lockedLessonIds = $isEnrolled
            ? $this->lockedLessonIds($course, $request->user(), $lessons)
            : collect();
        $promotionalVideo = $this->promotionalVideo($course);
        $courseReturnTo = route('lms.front.courses.show', [
            'course' => $course,
            'enroll' => 1,
        ], false);

        $viewData = [
            'course' => $course,
            'lessons' => $lessons,
            'sections' => $lessons->groupBy(fn (Lesson $lesson): string => (string) ($lesson->module_title ?: 'Course Lessons')),
            'imageUrl' => $this->imageUrl($course),
            'priceSummary' => $this->priceSummary($course),
            'totalDuration' => $this->totalDuration($lessons),
            'previewLesson' => $previewLesson,
            'rating' => $this->rating($course),
            'students' => $this->studentCount($course),
            'couponEndpoint' => route('lms.front.courses.coupon', $course),
            'isEnrolled' => $isEnrolled,
            'enrollmentTrainingMode' => $isEnrolled
                ? CourseEnrollment::query()->where('course_id', $course->id)->where('user_id', $request->user()->id)->value('training_mode')
                : null,
            'lockedLessonIds' => $lockedLessonIds,
            'loginUrl' => route('login', ['return_to' => $courseReturnTo]),
            'promotionalVideoUrl' => $promotionalVideo['url'],
            'promotionalEmbedUrl' => $promotionalVideo['embed_url'],
            'seoAlternates' => $seoAlternates,
            'canonicalUrl' => $canonicalUrl,
        ];

        $detailsTemplate = app(TemplateSettings::class)->detailsTemplate();

        // The built-in default template is a stored HTML copy with hardcoded labels.
        // Use the maintained, translatable Blade view instead. Custom templates remain supported.
        if ($detailsTemplate && (
            (string) $detailsTemplate->slug === 'default-details'
            || (string) $detailsTemplate->system_key === 'details'
        )) {
            $detailsTemplate = null;
        }

        if ($detailsTemplate) {
            $course->setRelation('lessons', $lessons->where('status', 'published')->values());
            $course->loadCount('enrollments');
            $actionButton = $isEnrolled
                ? '<a class="ainpa-course-primary ainpa-course-player-link lms-template-details__action" href="'.e(route('lms.front.courses.player', $course, false)).'">Open Course Player</a>'
                : ($request->user()
                    ? '<button class="ainpa-course-primary lms-template-details__action" type="button" data-open-enroll>Enroll Now</button>'
                    : '<button class="ainpa-course-primary lms-template-details__action" type="button" data-open-enroll>Sign In to Enroll</button>');
            $activeTrainingMode = (string) ($viewData['enrollmentTrainingMode'] ?? '');
            $currencyCode = strtoupper((string) ($course->currency ?: 'USD'));
            $activePriceLabel = '';
            if ($activeTrainingMode === 'in_person' && (float) $course->in_person_price > 0) {
                $activePriceLabel = number_format((float) $course->in_person_price, 2).' '.$currencyCode;
            } elseif ($activeTrainingMode === 'online' && (float) $course->online_price > 0) {
                $activePriceLabel = number_format((float) $course->online_price, 2).' '.$currencyCode;
            }

            $viewData['renderedTemplate'] = app(CourseTemplateRenderer::class)->render($detailsTemplate, collect([$course]), [
                'price_label' => $activePriceLabel !== '' ? e($activePriceLabel) : null,
                'action_button' => $actionButton,
                'courses_url' => route('lms.front.courses', [], false),
                'home_url' => url('/'),
                'rating' => $viewData['rating'],
                'reviews_count' => $viewData['students'],
                'updated_at' => optional($course->updated_at)->format('M d, Y'),
                'instructor_initial' => Str::upper(Str::substr((string) ($course->instructor_name ?: \Modules\Lms\Support\PlatformBrand::name()), 0, 1)),
                'instructor_profile_url' => route('lms.front.instructors.show', [
                    'instructor' => Str::slug((string) ($course->instructor_name ?: \Modules\Lms\Support\PlatformBrand::name())),
                ], false),
                'enrollment_status' => $isEnrolled
                    ? 'This course is in your account'
                    : 'You are not enrolled in this course',
                'enrollment_status_class' => $isEnrolled ? 'is-enrolled' : '',
                'course_curriculum' => $this->courseCurriculumMarkup($course, $viewData['sections'], $isEnrolled, $lockedLessonIds, $viewData['totalDuration']),
                'promo_card' => $this->promoCardMarkup($course, $viewData['imageUrl'], $promotionalVideo['url'] !== ''),
            ]);

            return view('lms::frontend.course-template', $viewData);
        }

        return view('lms::frontend.course-details', $viewData);
    }

    public function templateScript(string $slug): Response
    {
        $template = Template::query()->active()->where('slug', $slug)->firstOrFail();

        return response((string) $template->js_code, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function instructorProfile(string $instructor): View
    {
        $courses = Course::query()
            ->where('status', 'published')
            ->where(function ($builder): void {
                $builder->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->withCount(['lessons', 'enrollments'])
            ->latest('published_at')
            ->latest('created_at')
            ->get()
            ->filter(fn (Course $course): bool => Str::slug((string) ($course->instructor_name ?: \Modules\Lms\Support\PlatformBrand::name())) === $instructor)
            ->values();

        abort_if($courses->isEmpty(), 404);

        $instructorName = (string) ($courses->first()->instructor_name ?: \Modules\Lms\Support\PlatformBrand::name());
        $instructorTitle = (string) ($courses->first()->instructor_title ?: 'Course Instructor');

        return view('lms::frontend.instructor-profile', [
            'instructorName' => $instructorName,
            'instructorTitle' => $instructorTitle,
            'courses' => $courses,
            'lessonCount' => (int) $courses->sum('lessons_count'),
            'studentCount' => (int) $courses->sum('enrollments_count'),
        ]);
    }

    public function coursePreview(Course $course, Lesson $lesson): View
    {
        abort_unless($course->status === 'published', 404);
        abort_if((int) $lesson->course_id !== (int) $course->id || $lesson->status !== 'published' || ! $lesson->free_preview, 404);

        $lessons = collect([$lesson]);

        return view('lms::frontend.course-player', [
            'course' => $course,
            'lessons' => $lessons,
            'sections' => collect([($lesson->module_title ?: 'Course Lessons') => $lessons]),
            'currentLesson' => $lesson,
            'previousLesson' => null,
            'nextLesson' => null,
            'upcomingLesson' => null,
            'lessonIndex' => 0,
            'imageUrl' => $this->imageUrl($course),
            'totalDuration' => $this->totalDuration($lessons),
            'resources' => [],
            'completedSeed' => collect(),
            'progressPercent' => 0,
            'lockedLessonIds' => collect(),
            'isPreviewMode' => true,
            'studentName' => 'Student',
        ]);
    }

    public function coursePlayer(Request $request, Course $course, ?Lesson $lesson = null)
    {
        abort_unless($course->status === 'published', 404);

        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! $this->userOwnsCourse($course, $request->user())) {
            return redirect()
                ->route('lms.front.courses.show', $course)
                ->with('lms_error', __('lms::ui.course_enroll_before_player'));
        }

        $lessons = $course->lessons()
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->get();

        if ($lessons->isEmpty()) {
            return redirect()
                ->route('lms.front.courses.show', $course)
                ->with('lms_status', __('lms::ui.course_enrolled_lessons_soon'));
        }

        if ($lesson && ((int) $lesson->course_id !== (int) $course->id || $lesson->status !== 'published')) {
            abort(404);
        }

        $completedSeed = $this->completedSeed($lessons, $request->user(), $course);
        $currentLesson = $lesson
            ?: $lessons->first(fn (Lesson $item): bool => ! $completedSeed->contains((int) $item->id))
            ?: $lessons->last();
        $lessonIndex = max(0, $lessons->search(fn (Lesson $item): bool => (int) $item->id === (int) $currentLesson->id));
        $lockedLessonIds = $this->lockedLessonIds($course, $request->user(), $lessons);
        if ($lockedLessonIds->contains((int) $currentLesson->id)) {
            return redirect()
                ->route('lms.front.courses.show', $course)
                ->with('lms_error', app(LessonAvailabilityService::class)->lockMessage($currentLesson));
        }

        LessonProgress::query()->updateOrCreate(
            ['course_id' => $course->id, 'lesson_id' => $currentLesson->id, 'user_id' => $request->user()->id],
            ['last_seen_at' => now()],
        );
        $previousLesson = $lessonIndex > 0 ? $lessons->get($lessonIndex - 1) : null;
        $nextCandidate = $lessons->get($lessonIndex + 1);
        $nextLesson = $nextCandidate && ! $lockedLessonIds->contains((int) $nextCandidate->id) ? $nextCandidate : null;

        return view('lms::frontend.course-player', [
            'course' => $course,
            'lessons' => $lessons,
            'sections' => $lessons->groupBy(fn (Lesson $item): string => (string) ($item->module_title ?: 'Course Lessons')),
            'currentLesson' => $currentLesson,
            'previousLesson' => $previousLesson,
            'nextLesson' => $nextLesson,
            'upcomingLesson' => $nextCandidate,
            'lessonIndex' => $lessonIndex,
            'imageUrl' => $this->imageUrl($course),
            'totalDuration' => $this->totalDuration($lessons),
            'resources' => $this->lessonResources($currentLesson),
            'completedSeed' => $completedSeed,
            'progressPercent' => $this->courseProgressPercent($course, $request->user(), $lessons->count()),
            'lockedLessonIds' => $lockedLessonIds,
            'studentName' => (string) (Student::query()->where('user_id', $request->user()->id)->value('name') ?: 'Student'),
        ]);
    }

    public function updateLessonProgress(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless($course->status === 'published', 404);
        abort_if((int) $lesson->course_id !== (int) $course->id || $lesson->status !== 'published', 404);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'error' => 'login_required',
                'message' => __('lms::ui.course_login_to_save_progress'),
            ], 401);
        }

        if (! $this->userOwnsCourse($course, $user)) {
            return response()->json([
                'ok' => false,
                'error' => 'not_enrolled',
                'message' => __('lms::ui.course_lesson_not_in_account'),
            ], 403);
        }

        $orderedLessons = $course->lessons()
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->get();
        $lockedLessonIds = $this->lockedLessonIds($course, $user, $orderedLessons);
        if ($lockedLessonIds->contains((int) $lesson->id)) {
            return response()->json([
                'ok' => false,
                'error' => 'lesson_locked',
                'message' => app(LessonAvailabilityService::class)->lockMessage($lesson),
            ], 403);
        }

        if (($lesson->completion_requirement ?: 'manual') !== 'manual') {
            return response()->json([
                'ok' => false,
                'error' => 'completion_policy',
                'message' => __('lms::ui.course_lesson_completed_policy'),
            ], 422);
        }

        $completed = $request->boolean('completed', true);

        LessonProgress::query()->updateOrCreate(
            [
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'user_id' => $user->id,
            ],
            [
                'completed' => $completed,
                'completed_at' => $completed ? now() : null,
                'last_seen_at' => now(),
            ],
        );

        $lessonCount = $this->lessonCount($course);
        $completedIds = $this->completedLessonIds($course, $user);
        $percent = $lessonCount > 0 ? (int) round(($completedIds->count() / $lessonCount) * 100) : 0;
        $isCourseCompleted = $lessonCount > 0 && $completedIds->count() >= $lessonCount;
        $lessonIndex = $orderedLessons->search(fn (Lesson $item): bool => (int) $item->id === (int) $lesson->id);
        $nextLesson = $lessonIndex === false ? null : $orderedLessons->get($lessonIndex + 1);
        $lockedLessonIds = $this->lockedLessonIds($course, $user, $orderedLessons);
        $nextLessonIsOpen = $nextLesson && ! $lockedLessonIds->contains((int) $nextLesson->id);

        return response()->json([
            'ok' => true,
            'data' => [
                'completed' => $completed,
                'completed_lessons' => $completedIds->values()->all(),
                'completed_count' => $completedIds->count(),
                'lessons_count' => $lessonCount,
                'progress_percent' => min(100, max(0, $percent)),
                'is_completed' => $isCourseCompleted,
                'next_lesson_id' => $nextLesson?->id,
                'next_lesson_url' => $completed && $nextLessonIsOpen
                    ? route('lms.front.courses.player', ['course' => $course, 'lesson' => $nextLesson])
                    : null,
                'course_complete_url' => $completed && ! $nextLesson && $isCourseCompleted
                    ? route('lms.front.student.courses')
                    : null,
            ],
        ]);
    }

    public function lessonVideo(Request $request, Course $course, Lesson $lesson): BinaryFileResponse
    {
        abort_unless($course->status === 'published', 404);
        abort_if((int) $lesson->course_id !== (int) $course->id || $lesson->status !== 'published', 404);
        abort_unless(
            (bool) $lesson->free_preview
            || ($request->user() && $this->userOwnsCourse($course, $request->user())),
            403,
        );

        $path = $this->localLessonVideoPath($lesson);
        abort_unless($path && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="lesson-video.'.e(pathinfo($path, PATHINFO_EXTENSION)).'"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function applyCoupon(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->status === 'published', 404);

        if (! $request->user()) {
            return response()->json([
                'ok' => false,
                'error' => 'login_required',
                'message' => __('lms::ui.course_login_to_enroll'),
            ], 401);
        }

        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'min:3', 'max:80'],
            'training_mode' => ['nullable', 'string', 'in:online,in_person'],
        ]);

        $courseTrainingMode = in_array($course->training_mode, ['online', 'in_person', 'both'], true)
            ? (string) $course->training_mode
            : 'online';
        $allowedTrainingModes = $courseTrainingMode === 'both' ? ['online', 'in_person'] : [$courseTrainingMode];
        $chosenTrainingMode = in_array((string) ($validated['training_mode'] ?? ''), $allowedTrainingModes, true)
            ? (string) $validated['training_mode']
            : $allowedTrainingModes[0];

        $normalizedCode = Str::upper(str_replace([' ', "\t", "\n", "\r"], '', trim((string) $validated['coupon_code'])));
        $user = $request->user();

        if ($this->userOwnsCourse($course, $user)) {
            return response()->json([
                'ok' => true,
                'data' => [
                    'message' => __('lms::ui.course_already_enrolled'),
                    'already_enrolled' => true,
                    'player_url' => route('lms.front.courses.player', $course),
                ],
            ]);
        }

        $result = DB::transaction(function () use ($normalizedCode, $course, $user, $chosenTrainingMode): array {
            $code = CourseCode::query()
                ->where('code', $normalizedCode)
                ->lockForUpdate()
                ->first();

            if (! $code) {
                return ['ok' => false, 'status' => 404, 'error' => 'code_not_found', 'message' => __('lms::ui.course_code_not_found')];
            }

            if ((int) $code->course_id !== (int) $course->id) {
                return ['ok' => false, 'status' => 422, 'error' => 'wrong_course_code', 'message' => __('lms::ui.course_code_wrong_course')];
            }

            if ($code->status === 'used' || $code->used_at || $code->used_by) {
                return ['ok' => false, 'status' => 422, 'error' => 'code_used', 'message' => __('lms::ui.course_code_used')];
            }

            if ($code->status !== 'available') {
                return ['ok' => false, 'status' => 422, 'error' => 'code_invalid', 'message' => __('lms::ui.course_code_invalid')];
            }

            $enrollment = CourseEnrollment::query()->create([
                'course_id' => $course->id,
                'user_id' => $user->id,
                'course_code_id' => $code->id,
                'source' => 'course_code',
                'training_mode' => $chosenTrainingMode,
                'enrolled_at' => now(),
            ]);

            $code->forceFill([
                'status' => 'used',
                'used_by' => $user->id,
                'used_at' => now(),
            ])->save();

            $this->syncStudentEnrollment($user, $course);

            return [
                'ok' => true,
                'enrollment_id' => $enrollment->id,
                'message' => __('lms::ui.course_enrolled_success'),
            ];
        });

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'],
                'message' => $result['message'],
            ], (int) $result['status']);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'message' => $result['message'],
                'enrollment_id' => $result['enrollment_id'],
                'player_url' => route('lms.front.courses.player', $course),
            ],
        ]);
    }


    public function updateTrainingMode(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->status === 'published', 404);

        if (! $request->user()) {
            return response()->json(['ok' => false, 'message' => __('lms::ui.course_login_required')], 401);
        }

        $validated = $request->validate([
            'training_mode' => ['required', 'string', 'in:online,in_person'],
        ]);

        $courseMode = in_array($course->training_mode, ['online', 'in_person', 'both'], true)
            ? (string) $course->training_mode
            : 'online';
        $allowedModes = $courseMode === 'both' ? ['online', 'in_person'] : [$courseMode];

        if (! in_array($validated['training_mode'], $allowedModes, true)) {
            return response()->json(['ok' => false, 'message' => __('lms::ui.course_training_mode_unavailable')], 422);
        }

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $enrollment) {
            return response()->json(['ok' => false, 'message' => __('lms::ui.course_not_enrolled')], 404);
        }

        $enrollment->forceFill(['training_mode' => $validated['training_mode']])->save();

        return response()->json([
            'ok' => true,
            'data' => [
                'training_mode' => $validated['training_mode'],
                'message' => __('lms::ui.course_training_mode_updated'),
            ],
        ]);
    }

    private function userOwnsCourse(Course $course, $user): bool
    {
        if (! $user || ! Schema::hasTable('lms_course_enrollments')) {
            return false;
        }

        $student = Student::query()->where('user_id', $user->id)->first();
        if ($student && $student->status !== 'active') {
            return false;
        }

        return CourseEnrollment::query()
            ->where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function syncStudentEnrollment($user, Course $course): void
    {
        if (! Schema::hasTable('lms_students')) {
            return;
        }

        $student = Student::query()->firstOrNew(['email' => $user->email]);
        $ids = collect((array) ($student->enrolled_course_ids ?: []))
            ->map(fn ($id) => (int) $id)
            ->push((int) $course->id)
            ->unique()
            ->values()
            ->all();

        $student->fill([
            'user_id' => $user->id,
            'name' => $student->name ?: ($user->name ?: $user->email),
            'email' => $user->email,
            'status' => $student->status ?: 'active',
            'registered_at' => $student->registered_at ?: now(),
            'last_active_at' => now(),
            'enrolled_course_ids' => $ids,
            'enrolled_courses_count' => count($ids),
        ])->save();
    }

    private function localizeCourses(iterable $courses): void
    {
        $locale = app()->getLocale();

        if ($locale === PlatformLocale::DEFAULT) {
            return;
        }

        $map = CourseTranslation::mapForCourses($courses, $locale);

        foreach ($courses as $course) {
            if (isset($map[$course->id])) {
                $map[$course->id]->applyTo($course);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function coursePayload(Course $course, bool $compact = false, $user = null): array
    {
        $price = $course->sale_price ?: $course->regular_price;
        $currency = strtoupper((string) ($course->currency ?: 'USD'));
        $progress = $this->courseProgressSummary($course, $user);

        return [
            'id' => $course->id,
            'title' => (string) $course->title,
            'slug' => (string) $course->slug,
            'subtitle' => (string) ($course->subtitle ?: $course->short_description ?: ''),
            'description' => Str::limit(strip_tags((string) ($course->short_description ?: $course->description ?: '')), $compact ? 80 : 140),
            'category' => (string) ($course->category ?: 'عام'),
            'level' => (string) ($course->level ?: 'مبتدئ'),
            'pricing_type' => (string) ($course->pricing_type ?: 'free'),
            'price' => $course->pricing_type === 'free' ? null : ($price !== null ? number_format((float) $price, 0) : null),
            'currency' => $currency,
            'image_url' => $this->imageUrl($course),
            'instructor_name' => (string) ($course->instructor_name ?: \Modules\Lms\Support\PlatformBrand::name()),
            'instructor_title' => (string) ($course->instructor_title ?: 'مدرب'),
            'rating' => $this->rating($course),
            'students' => $this->studentCount($course),
            'lessons' => $this->lessonCount($course),
            'details_url' => route('lms.front.courses.show', $course),
            'player_url' => $progress['is_enrolled'] ? route('lms.front.courses.player', $course) : null,
            'enrollment' => $progress,
            'badges' => $this->badges($course),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceSummary(Course $course): array
    {
        $regular = $course->regular_price !== null ? (float) $course->regular_price : null;
        $sale = $course->sale_price !== null ? (float) $course->sale_price : null;
        $current = $course->pricing_type === 'free' ? 0.0 : ($sale ?: ($regular ?: 0.0));
        $discount = null;

        if ($regular && $sale && $sale < $regular) {
            $discount = (int) round((($regular - $sale) / $regular) * 100);
        }

        return [
            'pricing_type' => (string) ($course->pricing_type ?: 'free'),
            'current' => $current,
            'regular' => $regular,
            'sale' => $sale,
            'discount' => $discount,
            'currency' => strtoupper((string) ($course->currency ?: 'USD')),
            'formatted_current' => $current > 0 ? number_format($current, 2) : 'Free',
            'formatted_regular' => $regular ? number_format($regular, 2) : null,
        ];
    }

    /**
     * @param  Collection<int, Lesson>  $lessons
     * @return array<string, int|string>
     */
    private function totalDuration($lessons): array
    {
        $minutes = (int) $lessons->sum(fn (Lesson $lesson): int => ((int) $lesson->duration_hours * 60) + (int) $lesson->duration_minutes);

        if ($minutes <= 0) {
            $minutes = max(1, $lessons->count()) * 30;
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return [
            'minutes' => $minutes,
            'hours' => $hours,
            'remaining_minutes' => $remaining,
            'label' => trim(($hours ? $hours.' '.__('lms::ui.course_hours_short').' ' : '').($remaining ? $remaining.' '.__('lms::ui.course_minutes_short') : '')),
        ];
    }

    /**
     * Render the functional curriculum used inside editable Course Details
     * templates. The template controls where this block appears; LMS keeps
     * enrollment, lesson locking, preview, and player route rules authoritative.
     */
    private function courseCurriculumMarkup(Course $course, $sections, bool $isEnrolled, $lockedLessonIds, array $totalDuration): string
    {
        $lessonCount = (int) $sections->sum(fn ($sectionLessons): int => $sectionLessons->count());
        $unitCount = (int) $sections->count();
        $duration = e((string) ($totalDuration['label'] ?? '30m'));
        $html = '<div class="ainpa-course-curriculum-head"><h2>Course Curriculum</h2>'
            .'<span>'.$unitCount.' Units &bull; '.$lessonCount.' Lessons &bull; '.$duration.' Total</span>'
            .'<button type="button" data-expand-all>Expand All</button></div>'
            .'<div class="ainpa-course-sections">';

        if ($lessonCount === 0) {
            return $html.'<div class="ainpa-course-empty">No lessons have been published for this course yet.</div></div>';
        }

        foreach ($sections->values() as $sectionIndex => $sectionLessons) {
            $position = $sectionIndex + 1;
            $sectionId = 'course-template-section-'.$course->id.'-'.$position;
            $isOpen = $position === 1;
            $sectionTitle = e((string) ($sectionLessons->first()?->module_title ?: 'Course Lessons'));
            $html .= '<article class="ainpa-course-section'.($isOpen ? ' is-open' : '').'">'
                .'<button type="button" class="ainpa-course-section-head" data-toggle-section aria-expanded="'.($isOpen ? 'true' : 'false').'" aria-controls="'.e($sectionId).'">'
                .'<span>'.$position.'. '.$sectionTitle.'</span><strong>'.$sectionLessons->count().' Lessons</strong></button>'
                .'<div class="ainpa-course-lessons" id="'.e($sectionId).'">';

            foreach ($sectionLessons->values() as $lessonIndex => $lesson) {
                $lessonPosition = ((int) $lessonIndex) + 1;
                $lessonDuration = trim(((int) $lesson->duration_hours ? ((int) $lesson->duration_hours).'h ' : '').((int) $lesson->duration_minutes ? ((int) $lesson->duration_minutes).'m' : '')) ?: '10m';
                $isLocked = $isEnrolled && $lockedLessonIds->contains((int) $lesson->id);
                $label = '<span>'.$position.'.'.$lessonPosition.'</span><strong>'.e((string) $lesson->title).'</strong>'
                    .'<small>'.e(ucfirst((string) ($lesson->lesson_type ?: 'lesson'))).'</small><b>'.e($lessonDuration).'</b>';

                $html .= '<div class="ainpa-course-lesson-row">';
                if ($isEnrolled && ! $isLocked) {
                    $html .= '<a class="ainpa-course-lesson" href="'.e(route('lms.front.courses.player', ['course' => $course, 'lesson' => $lesson], false)).'">'.$label.'</a>';
                } elseif ($isEnrolled) {
                    $html .= '<span class="ainpa-course-lesson is-locked" title="Complete the previous lesson to unlock this lesson." aria-disabled="true">'.$label.'<em>Locked</em></span>';
                } else {
                    $html .= '<button type="button" class="ainpa-course-lesson" data-open-enroll>'.$label.'</button>';
                }

                if ((bool) $lesson->free_preview && $lesson->status === 'published') {
                    $previewUrl = route('lms.front.courses.preview', ['course' => $course, 'lesson' => $lesson], false);
                    $html .= '<a class="ainpa-course-lesson-preview" href="'.e($previewUrl).'" aria-label="Preview '.e((string) $lesson->title).'" title="Preview lesson">'.$this->previewIcon().'</a>';
                } else {
                    $html .= '<span class="ainpa-course-lesson-preview is-disabled" data-tooltip="Enroll to watch" aria-label="Enroll to watch" tabindex="0">'.$this->previewIcon().'</span>';
                }
                $html .= '</div>';
            }

            $html .= '</div></article>';
        }

        return $html.'</div>';
    }

    private function previewIcon(): string
    {
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Zm9.5 3.25A3.25 3.25 0 1 0 12 8.75a3.25 3.25 0 0 0 0 6.5Z"/></svg>';
    }

    /** @return array{url: string, embed_url: ?string} */
    private function promotionalVideo(Course $course): array
    {
        $url = trim((string) ($course->promo_video_url ?: ($course->promo_video_path
            ? Storage::disk('public')->url($course->promo_video_path)
            : '')));

        return [
            'url' => $url,
            'embed_url' => VideoEmbedResolver::embedUrl($url, autoplay: true),
        ];
    }

    private function promoCardMarkup(Course $course, ?string $imageUrl, bool $hasVideo): string
    {
        $content = $imageUrl
            ? '<img src="'.e($imageUrl).'" alt="'.e((string) $course->title).'">'
            : '<span class="ainpa-course-preview-fallback">'.e(Str::limit((string) $course->title, 2, '')).'</span>';

        if (! $hasVideo) {
            return '<div class="ainpa-course-preview-card is-static">'.$content.'</div>';
        }

        return '<button type="button" class="ainpa-course-preview-card" data-open-promo aria-label="Watch the promotional video for '.e((string) $course->title).'">'
            .$content.'<span class="ainpa-course-play" aria-hidden="true"><span></span></span></button>';
    }

    private function imageUrl(Course $course): ?string
    {
        $meta = is_array($course->featured_image_meta) ? $course->featured_image_meta : [];
        $mediaUrl = (string) ($meta['media_url'] ?? '');

        if ($mediaUrl !== '') {
            $mediaPath = (string) parse_url($mediaUrl, PHP_URL_PATH);

            return Str::startsWith($mediaPath, '/storage/') ? $this->publicMediaUrl($mediaPath) : $mediaUrl;
        }

        $path = (string) ($course->featured_image_path ?? '');

        if ($path === '') {
            return $this->courseImageBySlug($course);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->publicMediaUrl('/storage/'.ltrim($path, '/'));
    }

    private function courseImageBySlug(Course $course): ?string
    {
        $candidates = array_filter([
            Str::slug((string) $course->slug),
            Str::slug((string) $course->title),
        ]);

        foreach ($candidates as $candidate) {
            foreach (['media/courses/', 'media/', 'courses/', '', 'lms/course-images/'] as $folder) {
                foreach (['webp', 'jpg', 'jpeg', 'png'] as $extension) {
                    $path = $folder.$candidate.'.'.$extension;

                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                        return route('lms.front.media.file', ['path' => $path], false);
                    }
                }
            }
        }

        return null;
    }
    private function publicMediaUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (! Str::startsWith($path, '/storage/')) {
            return $url;
        }

        return route('lms.front.media.file', ['path' => ltrim(Str::after($path, '/storage/'), '/')], false);
    }

    private function localLessonVideoPath(Lesson $lesson): ?string
    {
        $relative = trim((string) $lesson->video_path);
        $disk = 'public';
        if (Str::startsWith($relative, 'private:')) {
            $disk = 'local';
            $relative = Str::after($relative, 'private:');
        }
        if ($relative === '') {
            $urlPath = (string) parse_url((string) $lesson->video_url, PHP_URL_PATH);
            if (Str::startsWith($urlPath, '/storage/')) {
                $relative = Str::after($urlPath, '/storage/');
            }
        }

        $relative = ltrim($relative, '/');
        if ($relative === '' || str_contains($relative, '..') || ! Storage::disk($disk)->exists($relative)) {
            return null;
        }

        $root = realpath(Storage::disk($disk)->path(''));
        $path = realpath(Storage::disk($disk)->path($relative));
        if (! $root || ! $path || ! str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    private function rating(Course $course): string
    {
        return number_format(4.6 + (($course->id % 4) * 0.1), 1);
    }

    private function studentCount(Course $course): string
    {
        if (Schema::hasTable('lms_course_enrollments')) {
            $value = CourseEnrollment::query()->where('course_id', $course->id)->count();

            return (string) $value;
        }

        $value = 0;

        if (Schema::hasTable('lms_students')) {
            $value = Student::query()->whereJsonContains('enrolled_course_ids', (int) $course->id)->count();
        }

        return $value >= 1000 ? number_format($value / 1000, 1).'K' : (string) $value;
    }

    private function lessonCount(Course $course): int
    {
        if (! Schema::hasTable('lms_lessons')) {
            return 0;
        }

        return (int) DB::table('lms_lessons')
            ->where('course_id', $course->id)
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->count();
    }

    /**
     * @return Collection<int, int>
     */
    private function completedSeed($lessons, $user = null, ?Course $course = null)
    {
        if (! $user || ! $course || ! Schema::hasTable('lms_lesson_progress')) {
            return collect();
        }

        return $this->completedLessonIds($course, $user, $lessons->pluck('id')->all());
    }

    private function courseProgressPercent(Course $course, $user, int $lessonCount): int
    {
        if (! $user || $lessonCount <= 0) {
            return 0;
        }

        $completed = $this->completedLessonIds($course, $user)->count();

        return min(100, max(0, (int) round(($completed / $lessonCount) * 100)));
    }

    /**
     * @return Collection<int, int>
     */
    private function completedLessonIds(Course $course, $user, array $lessonIds = [])
    {
        if (! $user || ! Schema::hasTable('lms_lesson_progress')) {
            return collect();
        }

        $query = LessonProgress::query()
            ->where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->where('completed', true);

        if ($lessonIds !== []) {
            $query->whereIn('lesson_id', $lessonIds);
        } else {
            $query->whereIn('lesson_id', DB::table('lms_lessons')
                ->select('id')
                ->where('course_id', $course->id)
                ->whereNull('deleted_at')
                ->where('status', 'published'));
        }

        return $query
            ->pluck('lesson_id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function lockedLessonIds(Course $course, $user, $lessons)
    {
        return app(LessonAvailabilityService::class)->lockedLessonIds($course, $user, collect($lessons));
    }

    /**
     * @return array<string, mixed>
     */
    private function courseProgressSummary(Course $course, $user): array
    {
        $isEnrolled = $this->userOwnsCourse($course, $user);
        $lessonCount = $this->lessonCount($course);
        $completedCount = $isEnrolled ? $this->completedLessonIds($course, $user)->count() : 0;
        $percent = $lessonCount > 0 ? (int) round(($completedCount / $lessonCount) * 100) : 0;
        $isCompleted = $isEnrolled && $lessonCount > 0 && $completedCount >= $lessonCount;

        if (! $isEnrolled) {
            $state = 'not_enrolled';
            $label = 'Not Enrolled';
            $button = 'View Course';
            $className = 'neutral';
            $actionUrl = route('lms.front.courses.show', $course);
        } elseif ($isCompleted) {
            $state = 'completed';
            $label = 'Completed';
            $button = 'Open Course Player';
            $className = 'completed';
            $actionUrl = route('lms.front.courses.player', $course);
        } else {
            $state = 'in_progress';
            $label = 'In Progress';
            $button = 'Continue Learning';
            $className = 'progress';
            $actionUrl = route('lms.front.courses.player', $course);
        }

        return [
            'is_enrolled' => $isEnrolled,
            'is_completed' => $isCompleted,
            'state' => $state,
            'label' => $label,
            'class_name' => $className,
            'action_label' => $button,
            'action_url' => $actionUrl,
            'progress_percent' => $isEnrolled ? min(100, max(0, $percent)) : null,
            'completed_lessons' => $completedCount,
            'lessons_count' => $lessonCount,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function lessonResources(Lesson $lesson): array
    {
        $items = [];

        foreach (['attachments', 'resources'] as $field) {
            foreach ((array) ($lesson->{$field} ?: []) as $resource) {
                if (is_string($resource)) {
                    $items[] = [
                        'title' => basename($resource) ?: 'Lesson Resource',
                        'type' => 'File',
                        'size' => '',
                        'url' => $resource,
                    ];

                    continue;
                }

                if (is_array($resource)) {
                    $items[] = [
                        'title' => (string) ($resource['title'] ?? $resource['name'] ?? $resource['file_name'] ?? 'Lesson Resource'),
                        'type' => strtoupper((string) ($resource['type'] ?? $resource['extension'] ?? 'PDF')),
                        'size' => (string) ($resource['size'] ?? ''),
                        'url' => (string) ($resource['url'] ?? $resource['path'] ?? '#'),
                    ];
                }
            }
        }

        $items = array_map(function (array $item): array {
            $url = trim((string) ($item['url'] ?? ''));
            $publicPath = (string) parse_url($url, PHP_URL_PATH);

            if (Str::startsWith($publicPath, '/storage/')) {
                $url = $publicPath;
            }

            if ($url !== '' && ! Str::startsWith($url, ['http://', 'https://', '/'])) {
                // Keep public-disk files origin-relative so previews and course
                // players also work when the platform is reached through an
                // internal host or an alternate domain.
                $url = '/storage/'.ltrim($url, '/');
            }
            $item['url'] = $url;

            return $item;
        }, $items);

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function badges(Course $course): array
    {
        $badges = [];

        if ($course->created_at && $course->created_at->greaterThan(now()->subDays(30))) {
            $badges[] = 'جديد';
        }

        if ((int) $course->id % 3 === 0) {
            $badges[] = 'الأكثر طلباً';
        }

        if ($course->pricing_type === 'free') {
            $badges[] = 'مجاني';
        }

        return $badges;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        $public = Course::query()
            ->where('status', 'published')
            ->where(function (Builder $builder): void {
                $builder->whereNull('visibility')->orWhere('visibility', 'public');
            });

        return [
            'categories' => (clone $public)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->filter()->values(),
            'levels' => (clone $public)->whereNotNull('level')->distinct()->orderBy('level')->pluck('level')->filter()->values(),
            'types' => [
                ['value' => '', 'label' => 'All'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'free', 'label' => 'Free'],
            ],
            'sorts' => [
                ['value' => 'newest', 'label' => 'Newest'],
                ['value' => 'price_low', 'label' => 'Lowest price'],
                ['value' => 'price_high', 'label' => 'Highest price'],
                ['value' => 'title', 'label' => 'Course title'],
            ],
        ];
    }
}
