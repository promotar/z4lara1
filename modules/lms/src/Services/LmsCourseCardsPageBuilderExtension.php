<?php

namespace Modules\Lms\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Lms\Models\Course;

final class LmsCourseCardsPageBuilderExtension
{
    /** Keep the legacy marker so pages saved with the former template element remain compatible. */
    private const SLOT_PATTERN = '/<div\b(?=[^>]*\bdata-lms-course-template\s*=)[^>]*>[\s\S]*?<\/div>/i';

    /** @param array<int, array<string, mixed>> $extensions */
    public function editorExtensions(array $extensions, object $page): array
    {
        if (! $this->isReady()) {
            return $extensions;
        }

        $courses = $this->coursesQuery()->limit(24)->get();
        $extensions[] = [
            'id' => 'lms.courses',
            'config' => [
                'previewHtml' => $this->cardsHtml($courses),
                'hasCourses' => $courses->isNotEmpty(),
                'categories' => $this->coursesQuery()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->values()->all(),
                'levels' => $this->coursesQuery()->whereNotNull('level')->distinct()->orderBy('level')->pluck('level')->values()->all(),
            ],
            'scripts' => [url('/platform/plugins/lms/admin/js/vvveb-lms-course-cards.js?v=20260820-1')],
            'styles' => [],
        ];

        return $extensions;
    }

    public function renderFrontendHtml(string $html, object $context): string
    {
        if (! $this->isReady() || ! str_contains($html, 'data-lms-course-template')) {
            return $html;
        }

        return preg_replace_callback(self::SLOT_PATTERN, function (array $match): string {
            $attributes = $this->attributes($match[0]);
            [$courses, $pagination] = $this->courses($attributes);
            if ($courses->isEmpty()) {
                return '';
            }

            $instance = $this->instance($attributes);

            return '<section class="lms-course-cards" data-lms-course-cards-rendered="'.e($instance).'">'
                .$this->styles($instance, $attributes)
                .'<div class="lms-course-cards-items">'.$this->cardsHtml($courses).'</div>'
                .$pagination
                .'</section>';
        }, $html) ?? $html;
    }

    /** @return array{0:Collection<int, Course>,1:string} */
    private function courses(array $attributes): array
    {
        $source = (string) ($attributes['data-lms-course-source'] ?? 'latest');
        $value = trim((string) ($attributes['data-lms-course-value'] ?? ''));
        if ($source === 'category') {
            $value = trim((string) ($attributes['data-lms-course-category'] ?? $value));
        } elseif ($source === 'level') {
            $value = trim((string) ($attributes['data-lms-course-level'] ?? $value));
        }

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
            'random' => $query->inRandomOrder(),
            default => $query->latest('published_at')->latest('id'),
        };

        $limit = max(1, min(24, (int) ($attributes['data-lms-course-limit'] ?? 6)));
        if (($attributes['data-lms-course-pagination'] ?? '0') !== '1') {
            return [$query->limit($limit)->get(), ''];
        }

        $instance = $this->instance($attributes);
        $pageName = 'lms_page_'.str_replace('-', '_', $instance);
        $paginator = $query->paginate($limit, ['*'], $pageName, max(1, (int) request()->query($pageName, 1)))->withQueryString();

        return [$paginator->getCollection(), $this->paginationHtml($paginator, $instance)];
    }

    private function coursesQuery(): Builder
    {
        return Course::query()
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->withCount([
                'lessons as published_lessons_count' => fn (Builder $query) => $query->where('status', 'published'),
                'enrollments',
            ]);
    }

    /** @param Collection<int, Course> $courses */
    private function cardsHtml(Collection $courses): string
    {
        return $courses->map(function (Course $course): string {
            $url = route('lms.front.courses.show', ['course' => $course->slug], false);
            $image = $this->imageUrl($course);
            $visual = $image !== ''
                ? '<img src="'.e($image).'" alt="'.e((string) $course->title).'" loading="lazy">'
                : '<span class="lms-course-card-placeholder">'.e(Str::upper(Str::substr((string) $course->title, 0, 2))).'</span>';
            $price = $this->priceLabel($course);

            return '<article class="lms-course-card" data-course-category="'.e((string) $course->category).'" data-course-level="'.e((string) $course->level).'" data-course-title="'.e(Str::lower((string) $course->title)).'" data-course-price="'.e((string) $this->effectivePrice($course)).'" data-course-published="'.e((string) ($course->published_at?->timestamp ?? 0)).'">'
                .'<a class="lms-course-card-image" href="'.e($url).'">'.$visual.'</a>'
                .'<div class="lms-course-card-body">'
                .'<p class="lms-course-card-category">'.e((string) ($course->category ?: $this->label('دورة تدريبية', 'Course'))).'</p>'
                .'<h3><a href="'.e($url).'">'.e((string) $course->title).'</a></h3>'
                .'<p class="lms-course-card-instructor">'.e((string) ($course->instructor_name ?: \Modules\Lms\Support\PlatformBrand::name())).'</p>'
                .'<div class="lms-course-card-meta"><span class="lms-course-card-lessons">'.e((string) ($course->published_lessons_count ?? 0)).' '.$this->label('دروس', 'lessons').'</span><span class="lms-course-card-level">'.e((string) ($course->level ?: $this->label('جميع المستويات', 'All levels'))).'</span></div>'
                .'<div class="lms-course-card-footer"><strong class="lms-course-card-price">'.e($price).'</strong><a href="'.e($url).'">'.$this->label('عرض الدورة', 'View course').'</a></div>'
                .'</div></article>';
        })->implode('');
    }

    private function styles(string $instance, array $attributes): string
    {
        $selector = '[data-lms-course-cards-rendered="'.e($instance).'"]';
        $desktop = $this->number($attributes, 'data-lms-course-columns-desktop', 3, 1, 6);
        $tablet = $this->number($attributes, 'data-lms-course-columns-tablet', 2, 1, 4);
        $mobile = $this->number($attributes, 'data-lms-course-columns-mobile', 1, 1, 2);
        $gap = $this->number($attributes, 'data-lms-course-gap', 24, 0, 120);
        $fit = ($attributes['data-lms-course-image-fit'] ?? 'contain') === 'cover' ? 'cover' : 'contain';
        $hidden = collect([
            ($attributes['data-lms-course-show-category'] ?? '1') === '0' ? '.lms-course-card-category' : '',
            ($attributes['data-lms-course-show-instructor'] ?? '1') === '0' ? '.lms-course-card-instructor' : '',
            ($attributes['data-lms-course-show-lessons'] ?? '1') === '0' ? '.lms-course-card-lessons' : '',
            ($attributes['data-lms-course-show-level'] ?? '1') === '0' ? '.lms-course-card-level' : '',
            ($attributes['data-lms-course-show-price'] ?? '1') === '0' ? '.lms-course-card-price' : '',
        ])->filter()->map(fn (string $class): string => $selector.' '.$class)->implode(',');

        return '<style data-lms-course-cards-styles>'
            .$selector.' .lms-course-cards-items{display:grid;grid-template-columns:repeat('.$desktop.',minmax(0,1fr));gap:'.$gap.'px}'
            .$selector.' .lms-course-card{display:flex;flex-direction:column;min-width:0;overflow:hidden;border:1px solid rgba(15,23,42,.12);border-radius:14px;background:#fff;color:#0f172a}'
            .$selector.' .lms-course-card-image{display:flex;flex-shrink:0;aspect-ratio:'.($fit === 'contain' ? '1/1' : '16/10').';align-items:center;justify-content:center;overflow:hidden;background:#f3ecf8;text-decoration:none}'
            .$selector.' .lms-course-card-image img{display:block;width:100%;height:100%;object-fit:'.$fit.';object-position:center}'.$selector.' .lms-course-card-placeholder{min-height:180px;display:grid;place-items:center;color:#991b1b;font:800 2rem/1 system-ui,sans-serif}'
            .$selector.' .lms-course-card-body{display:flex;flex:1;flex-direction:column;gap:10px;padding:18px}'.$selector.' h3,'.$selector.' p{margin:0}'.$selector.' h3{font:700 1.05rem/1.45 system-ui,sans-serif}'.$selector.' h3 a{color:inherit;text-decoration:none}'
            .$selector.' .lms-course-card-category{color:#991b1b;font:700 12px/1.4 system-ui,sans-serif}'.$selector.' .lms-course-card-instructor{color:#64748b;font:400 14px/1.5 system-ui,sans-serif}'
            .$selector.' .lms-course-card-meta,'.$selector.' .lms-course-card-footer{display:flex;align-items:center;justify-content:space-between;gap:12px}'.$selector.' .lms-course-card-meta{color:#64748b;font:600 12px/1.4 system-ui,sans-serif}'.$selector.' .lms-course-card-footer{margin-top:auto}'.$selector.' .lms-course-card-footer a{color:#991b1b;font:700 12px/1.4 system-ui,sans-serif}'
            .($hidden !== '' ? $hidden.'{display:none}' : '')
            .'@media(max-width:991px){'.$selector.' .lms-course-cards-items{grid-template-columns:repeat('.$tablet.',minmax(0,1fr))}}@media(max-width:575px){'.$selector.' .lms-course-cards-items{grid-template-columns:repeat('.$mobile.',minmax(0,1fr))}}'
            .'</style>';
    }

    private function paginationHtml(LengthAwarePaginator $paginator, string $instance): string
    {
        if ($paginator->lastPage() <= 1) {
            return '';
        }

        $links = $paginator->onFirstPage()
            ? '<span class="is-disabled">'.$this->label('السابق', 'Previous').'</span>'
            : '<a href="'.e((string) $paginator->previousPageUrl()).'">'.$this->label('السابق', 'Previous').'</a>';
        foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url) {
            $links .= $page === $paginator->currentPage()
                ? '<span class="is-current">'.e((string) $page).'</span>'
                : '<a href="'.e($url).'">'.e((string) $page).'</a>';
        }
        $links .= $paginator->hasMorePages()
            ? '<a href="'.e((string) $paginator->nextPageUrl()).'">'.$this->label('التالي', 'Next').'</a>'
            : '<span class="is-disabled">'.$this->label('التالي', 'Next').'</span>';

        return '<nav class="lms-course-cards-pagination" data-lms-course-cards-pagination="'.e($instance).'"><style>.lms-course-cards-pagination{display:flex;justify-content:center;gap:.4rem;flex-wrap:wrap;margin:1.5rem 0}.lms-course-cards-pagination a,.lms-course-cards-pagination span{display:inline-flex;min-width:2.3rem;height:2.3rem;align-items:center;justify-content:center;border:1px solid #e5d4d4;border-radius:.5rem;padding:0 .7rem;text-decoration:none}.lms-course-cards-pagination .is-current{background:#991b1b;color:#fff}.lms-course-cards-pagination .is-disabled{opacity:.45}</style>'.$links.'</nav>';
    }

    /** @return array<string, string> */
    private function attributes(string $slot): array
    {
        preg_match('/^<div\b([^>]*)>/i', $slot, $opening);
        preg_match_all('/\b(data-lms-[a-z0-9-]+)\s*=\s*(["\'])(.*?)\2/is', (string) ($opening[1] ?? ''), $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match): array => [strtolower($match[1]) => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8')])->all();
    }

    private function instance(array $attributes): string
    {
        $value = strtolower((string) ($attributes['data-lms-course-instance'] ?? ''));
        $value = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $value), '-');

        return $value !== '' ? substr($value, 0, 80) : 'lms-courses-'.substr(sha1((string) json_encode($attributes)), 0, 12);
    }

    private function imageUrl(Course $course): string
    {
        $meta = is_array($course->featured_image_meta) ? $course->featured_image_meta : [];
        $url = trim((string) ($meta['media_url'] ?? ''));
        if ($url !== '') {
            return $url;
        }
        $path = trim((string) $course->featured_image_path);
        if ($path === '') {
            return '';
        }

        return Str::startsWith($path, ['http://', 'https://', '/']) ? $path : Storage::disk('public')->url($path);
    }

    private function effectivePrice(Course $course): float
    {
        if ($course->pricing_type === 'free') {
            return 0.0;
        }

        $sale = (float) ($course->sale_price ?? 0);
        return $sale > 0 ? $sale : (float) ($course->regular_price ?? 0);
    }

    private function priceLabel(Course $course): string
    {
        $price = $this->effectivePrice($course);

        return $price > 0 ? number_format($price, 2).' '.strtoupper((string) ($course->currency ?: 'USD')) : $this->label('مجاني', 'Free');
    }

    private function number(array $attributes, string $key, int $fallback, int $min, int $max): int
    {
        return max($min, min($max, (int) ($attributes[$key] ?? $fallback)));
    }

    private function label(string $arabic, string $english): string
    {
        return app()->getLocale() === 'ar' ? $arabic : $english;
    }

    private function isReady(): bool
    {
        return Schema::hasTable('lms_courses')
            && Schema::hasTable('lms_lessons')
            && Schema::hasTable('lms_course_enrollments')
            && Route::has('lms.front.courses.show');
    }
}
