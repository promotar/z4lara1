<?php

namespace Modules\Lms\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\Template;

class PageBuilderExtension
{
    private const SLOT_PATTERN = '/<div\b(?=[^>]*\bdata-lms-course-template\s*=)[^>]*>[\s\S]*?<\/div>/i';

    public function __construct(private readonly CourseTemplateRenderer $renderer) {}

    /**
     * Public Page Builder extension boundary. LMS registers its element only from
     * its active hook runtime; disabling LMS therefore removes the element and
     * its editor assets without leaving LMS code inside Page Builder.
     *
     * @param  array<int, array<string, mixed>>  $extensions
     * @return array<int, array<string, mixed>>
     */
    public function editorExtensions(array $extensions, object $page): array
    {
        if (! $this->schemaIsReady()) {
            return $extensions;
        }

        $previewCourses = $this->coursesQuery()->limit(6)->get();
        if ($previewCourses->isEmpty()) {
            $previewCourses = collect([$this->sampleCourse()]);
        }

        $templates = Template::query()
            ->active()
            ->catalog()
            ->orderBy('name')
            ->get()
            ->map(fn (Template $template): array => [
                'name' => $template->name,
                'slug' => $template->slug,
                'previewHtml' => $this->renderer->render($template, $previewCourses, [
                    'catalog_title' => 'Courses template preview',
                    'catalog_description' => 'The frontend uses the same template with published LMS courses.',
                ]),
            ])
            ->values()
            ->all();

        $extensions[] = [
            'id' => 'lms.courses',
            'config' => [
                'templates' => $templates,
                'categories' => Course::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->values()->all(),
                'levels' => Course::query()->whereNotNull('level')->distinct()->orderBy('level')->pluck('level')->values()->all(),
            ],
            'scripts' => [url('/platform/plugins/lms/admin/js/vvveb-lms-course-template.js')],
            'styles' => [],
        ];

        return $extensions;
    }

    public function renderFrontendHtml(string $html, object $context): string
    {
        if (! $this->schemaIsReady() || ! str_contains($html, 'data-lms-course-template')) {
            return $html;
        }

        return preg_replace_callback(self::SLOT_PATTERN, function (array $match): string {
            $attributes = $this->slotAttributes($match[0]);
            $slug = trim((string) ($attributes['data-lms-course-template'] ?? ''));
            $template = Template::query()->active()->catalog()->where('slug', $slug)->first();
            if (! $template) {
                return '';
            }

            [$courses, $pagination] = $this->slotCourses($attributes, $template);
            $instance = $this->instanceKey($attributes, $template);
            $layout = $this->layoutSettings($attributes);

            return '<div data-lms-template-rendered="'.e($instance).'" data-lms-template-layout="'.e($layout['mode']).'">'
                .$this->layoutStyles($instance, $layout)
                .'<div data-lms-template-results>'
                .$this->renderer->render($template, $courses, [], ['wrap_courses' => $layout['mode'] !== 'template'])
                .'</div>'
                .$pagination
                .'</div>';
        }, $html) ?? $html;
    }

    /** @return array{0:iterable<int, Course>, 1:string} */
    private function slotCourses(array $attributes, Template $template): array
    {
        $source = (string) ($attributes['data-lms-course-source'] ?? 'latest');
        $value = trim((string) ($attributes['data-lms-course-value'] ?? ''));
        if ($source === 'category') {
            $value = trim((string) ($attributes['data-lms-course-category'] ?? $value));
        } elseif ($source === 'level') {
            $value = trim((string) ($attributes['data-lms-course-level'] ?? $value));
        }

        $limit = max(1, min(24, (int) ($attributes['data-lms-course-limit'] ?? 6)));
        $query = $this->coursesQuery();
        if ($source === 'category' && $value !== '') {
            $query->where('category', $value);
        } elseif ($source === 'level' && $value !== '') {
            $query->where('level', $value);
        } elseif ($source === 'search' && $value !== '') {
            $query->where(function (Builder $builder) use ($value): void {
                $builder->where('title', 'like', '%'.$value.'%')
                    ->orWhere('subtitle', 'like', '%'.$value.'%')
                    ->orWhere('short_description', 'like', '%'.$value.'%')
                    ->orWhere('instructor_name', 'like', '%'.$value.'%');
            });
        }

        match ((string) ($attributes['data-lms-course-order'] ?? 'newest')) {
            'oldest' => $query->oldest('published_at')->oldest('id'),
            'title' => $query->orderBy('title')->latest('id'),
            'price-low' => $query->orderByRaw('COALESCE(sale_price, regular_price, 0) asc')->latest('id'),
            'price-high' => $query->orderByRaw('COALESCE(sale_price, regular_price, 0) desc')->latest('id'),
            default => $query->latest('published_at')->latest('id'),
        };

        if (($attributes['data-lms-course-pagination'] ?? '0') !== '1') {
            return [$query->limit($limit)->get(), ''];
        }

        $instance = $this->instanceKey($attributes, $template);
        $pageName = 'lms_page_'.str_replace('-', '_', $instance);
        $currentPage = max(1, (int) request()->query($pageName, 1));
        $paginator = $query->paginate($limit, ['*'], $pageName, $currentPage)->withQueryString();

        return [$paginator->getCollection(), $this->paginationHtml($paginator, $instance)];
    }

    private function coursesQuery(): Builder
    {
        return Course::query()
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->with(['lessons' => fn ($query) => $query->where('status', 'published')])
            ->withCount('enrollments');
    }

    private function paginationHtml(LengthAwarePaginator $paginator, string $instance): string
    {
        if ($paginator->lastPage() <= 1) {
            return '';
        }

        $links = $paginator->onFirstPage()
            ? '<span class="is-disabled" aria-disabled="true">Previous</span>'
            : '<a href="'.e((string) $paginator->previousPageUrl()).'">Previous</a>';

        foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url) {
            $links .= $page === $paginator->currentPage()
                ? '<span class="is-current" aria-current="page">'.e((string) $page).'</span>'
                : '<a href="'.e($url).'">'.e((string) $page).'</a>';
        }

        $links .= $paginator->hasMorePages()
            ? '<a href="'.e((string) $paginator->nextPageUrl()).'">Next</a>'
            : '<span class="is-disabled" aria-disabled="true">Next</span>';

        return '<nav class="lms-template-pagination" data-lms-template-pagination="'.e($instance).'" aria-label="Courses pagination">'
            .'<style>.lms-template-pagination{display:flex;justify-content:center;gap:.4rem;flex-wrap:wrap;margin:1.5rem 0}.lms-template-pagination a,.lms-template-pagination span{display:inline-flex;min-width:2.3rem;height:2.3rem;align-items:center;justify-content:center;border:1px solid #e5d4d4;border-radius:.5rem;background:#fff;padding:0 .7rem;color:#5b2929;font:700 .8rem Arial,sans-serif;text-decoration:none}.lms-template-pagination .is-current{border-color:#991b1b;background:#991b1b;color:#fff}.lms-template-pagination .is-disabled{opacity:.45}</style>'
            .$links
            .'</nav>';
    }

    /** @return array{mode:string, desktop:int, tablet:int, mobile:int, gap:int} */
    private function layoutSettings(array $attributes): array
    {
        $mode = (string) ($attributes['data-lms-course-layout'] ?? 'template');

        return [
            'mode' => in_array($mode, ['template', 'grid', 'cards', 'slider'], true) ? $mode : 'template',
            'desktop' => max(1, min(6, (int) ($attributes['data-lms-course-columns-desktop'] ?? 3))),
            'tablet' => max(1, min(4, (int) ($attributes['data-lms-course-columns-tablet'] ?? 2))),
            'mobile' => max(1, min(2, (int) ($attributes['data-lms-course-columns-mobile'] ?? 1))),
            'gap' => max(0, min(120, (int) ($attributes['data-lms-course-gap'] ?? 24))),
        ];
    }

    /** @param array{mode:string, desktop:int, tablet:int, mobile:int, gap:int} $layout */
    private function layoutStyles(string $instance, array $layout): string
    {
        if ($layout['mode'] === 'template') {
            return '';
        }

        $selector = '[data-lms-template-rendered="'.e($instance).'"]';
        $gap = $layout['gap'];
        $desktop = $layout['desktop'];
        $tablet = $layout['tablet'];
        $mobile = $layout['mobile'];

        if ($layout['mode'] === 'slider') {
            $base = 'display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - '.(($desktop - 1) * $gap).'px)/'.$desktop.');grid-template-columns:none;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth';
            $tabletRule = 'grid-auto-columns:calc((100% - '.(($tablet - 1) * $gap).'px)/'.$tablet.')';
            $mobileRule = 'grid-auto-columns:calc((100% - '.(($mobile - 1) * $gap).'px)/'.$mobile.')';
        } else {
            $base = 'display:grid;grid-template-columns:repeat('.$desktop.',minmax(0,1fr))';
            $tabletRule = 'grid-template-columns:repeat('.$tablet.',minmax(0,1fr))';
            $mobileRule = 'grid-template-columns:repeat('.$mobile.',minmax(0,1fr))';
        }

        $cards = $layout['mode'] === 'cards'
            ? $selector.' [data-lms-template-items]>*{overflow:hidden;border:1px solid #eadada;border-radius:12px;background:#fff;box-shadow:0 8px 24px rgba(45,20,20,.08)}'
            : '';

        return '<style data-lms-template-layout-styles>'
            .$selector.' [data-lms-template-items]{'.$base.';position:relative;width:100%;grid-column:1/-1;flex:0 0 100%;gap:'.$gap.'px}'
            .$selector.' [data-lms-template-items]>*{min-width:0;scroll-snap-align:start}'
            .$cards
            .'@media(max-width:991px){'.$selector.' [data-lms-template-items]{'.$tabletRule.'}}'
            .'@media(max-width:575px){'.$selector.' [data-lms-template-items]{'.$mobileRule.'}}'
            .'</style>';
    }

    /** @return array<string, string> */
    private function slotAttributes(string $slot): array
    {
        preg_match('/^<div\b([^>]*)>/i', $slot, $opening);
        preg_match_all('/\b(data-lms-[a-z0-9-]+)\s*=\s*(["\'])(.*?)\2/is', (string) ($opening[1] ?? ''), $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match): array => [
            strtolower($match[1]) => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ])->all();
    }

    private function instanceKey(array $attributes, Template $template): string
    {
        $candidate = strtolower((string) ($attributes['data-lms-course-instance'] ?? ''));
        $candidate = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $candidate), '-');

        return $candidate !== '' ? substr($candidate, 0, 80) : 'lms-'.substr(sha1($template->slug.'|'.json_encode($attributes)), 0, 12);
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('lms_templates') && Schema::hasTable('lms_courses');
    }

    private function sampleCourse(): Course
    {
        $course = new Course([
            'id' => 1,
            'title' => 'Professional Creative Course',
            'slug' => 'professional-creative-course',
            'subtitle' => 'Build practical skills through a focused learning path.',
            'short_description' => 'A clean course preview rendered with the template selected in LMS.',
            'category' => 'Creative Learning',
            'level' => 'Beginner',
            'language' => 'English',
            'instructor_name' => \Modules\Lms\Support\PlatformBrand::name(),
            'instructor_title' => 'Course Instructor',
            'pricing_type' => 'free',
            'currency' => 'USD',
            'outcomes' => ['Build practical skills', 'Complete a guided project'],
        ]);
        $course->setRelation('lessons', collect());
        $course->setAttribute('enrollments_count', 0);

        return $course;
    }
}
