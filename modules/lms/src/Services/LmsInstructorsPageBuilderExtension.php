<?php

namespace Modules\Lms\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Lms\Models\Course;

final class LmsInstructorsPageBuilderExtension
{
    private const SLOT_PATTERN = '/<div\b(?=[^>]*\bdata-lms-instructors\s*=)[^>]*>[\s\S]*?<\/div>/i';

    /** @param array<int, array<string, mixed>> $extensions */
    public function editorExtensions(array $extensions, object $page): array
    {
        if (! $this->isReady()) {
            return $extensions;
        }

        $instructors = $this->instructors('courses', 24);
        $extensions[] = [
            'id' => 'lms.instructors',
            'config' => [
                'previewHtml' => $this->cardsHtml($instructors),
                'hasInstructors' => $instructors->isNotEmpty(),
            ],
            'scripts' => [url('/platform/plugins/lms/admin/js/vvveb-lms-instructors.js?v=20260820-2')],
            'styles' => [],
        ];

        return $extensions;
    }

    public function renderFrontendHtml(string $html, object $context): string
    {
        if (! $this->isReady() || ! str_contains($html, 'data-lms-instructors')) {
            return $html;
        }

        return preg_replace_callback(self::SLOT_PATTERN, function (array $match): string {
            $attributes = $this->attributes($match[0]);
            $limit = max(1, min(24, (int) ($attributes['data-lms-instructors-limit'] ?? 6)));
            $order = (string) ($attributes['data-lms-instructors-order'] ?? 'courses');
            $instance = $this->instance($attributes);
            $instructors = $this->instructors($order, $limit);

            if ($instructors->isEmpty()) {
                return '';
            }

            return '<section class="lms-instructors" data-lms-instructors-rendered="'.e($instance).'">'
                .$this->styles($instance, $attributes)
                .'<div class="lms-instructors-items">'.$this->cardsHtml($instructors).'</div>'
                .'</section>';
        }, $html) ?? $html;
    }

    /** @return Collection<int, array{name:string,title:string,slug:string,courses:int,students:int,published_at:string}> */
    private function instructors(string $order, int $limit): Collection
    {
        $rows = Course::query()
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->withCount('enrollments')
            ->get(['id', 'instructor_name', 'instructor_title', 'published_at'])
            ->groupBy(fn (Course $course): string => trim((string) ($course->instructor_name ?: \Modules\Lms\Support\PlatformBrand::name())))
            ->map(function (Collection $courses, string $name): array {
                $latest = $courses->sortByDesc(fn (Course $course) => (string) $course->published_at)->first();

                return [
                    'name' => $name,
                    'title' => trim((string) ($latest?->instructor_title ?: 'Course Instructor')),
                    'slug' => Str::slug($name),
                    'courses' => $courses->count(),
                    'students' => (int) $courses->sum('enrollments_count'),
                    'published_at' => (string) $latest?->published_at,
                ];
            })
            ->values();

        $rows = match ($order) {
            'name' => $rows->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            'newest' => $rows->sortByDesc('published_at'),
            'students' => $rows->sortByDesc('students'),
            default => $rows->sortByDesc('courses'),
        };

        return $rows->take($limit)->values();
    }

    /** @param Collection<int, array{name:string,title:string,slug:string,courses:int,students:int,published_at:string}> $instructors */
    private function cardsHtml(Collection $instructors): string
    {
        return $instructors->map(function (array $instructor): string {
            $url = route('lms.front.instructors.show', ['instructor' => $instructor['slug']], false);
            $initial = Str::upper(Str::substr($instructor['name'], 0, 1));

            return '<article class="lms-instructor-card">'
                .'<a class="lms-instructor-avatar" href="'.e($url).'" aria-label="'.e($instructor['name']).'">'.e($initial).'</a>'
                .'<div><h3><a href="'.e($url).'">'.e($instructor['name']).'</a></h3>'
                .'<p class="lms-instructor-title">'.e($instructor['title']).'</p>'
                .'<p class="lms-instructor-stats"><span>'.e((string) $instructor['courses']).' courses</span><span>'.e((string) $instructor['students']).' students</span></p></div>'
                .'</article>';
        })->implode('');
    }

    private function styles(string $instance, array $attributes): string
    {
        $selector = '[data-lms-instructors-rendered="'.e($instance).'"]';
        $desktop = max(1, min(6, (int) ($attributes['data-lms-instructors-columns-desktop'] ?? 3)));
        $tablet = max(1, min(4, (int) ($attributes['data-lms-instructors-columns-tablet'] ?? 2)));
        $mobile = max(1, min(2, (int) ($attributes['data-lms-instructors-columns-mobile'] ?? 1)));
        $gap = max(0, min(120, (int) ($attributes['data-lms-instructors-gap'] ?? 20)));
        $showTitle = ($attributes['data-lms-instructors-show-title'] ?? '1') !== '0';
        $showStats = ($attributes['data-lms-instructors-show-stats'] ?? '1') !== '0';

        return '<style data-lms-instructors-styles>'
            .$selector.' .lms-instructors-items{display:grid;grid-template-columns:repeat('.$desktop.',minmax(0,1fr));gap:'.$gap.'px}'
            .$selector.' .lms-instructor-card{display:flex;align-items:center;gap:16px;padding:20px;border:1px solid rgba(15,23,42,.12);border-radius:14px;background:#fff;color:#0f172a}'
            .$selector.' .lms-instructor-avatar{display:grid;flex:0 0 56px;width:56px;height:56px;place-items:center;border-radius:50%;background:#991b1b;color:#fff;font:800 22px/1 system-ui,sans-serif;text-decoration:none}'
            .$selector.' h3,'.$selector.' p{margin:0}'.$selector.' h3{font:700 1rem/1.4 system-ui,sans-serif}'.$selector.' h3 a{color:inherit;text-decoration:none}'
            .$selector.' .lms-instructor-title{margin-top:4px;color:#64748b;font:400 13px/1.4 system-ui,sans-serif}'.($showTitle ? '' : $selector.' .lms-instructor-title{display:none}')
            .$selector.' .lms-instructor-stats{display:flex;gap:10px;margin-top:8px;color:#991b1b;font:700 12px/1.4 system-ui,sans-serif}'.($showStats ? '' : $selector.' .lms-instructor-stats{display:none}')
            .'@media(max-width:991px){'.$selector.' .lms-instructors-items{grid-template-columns:repeat('.$tablet.',minmax(0,1fr))}}@media(max-width:575px){'.$selector.' .lms-instructors-items{grid-template-columns:repeat('.$mobile.',minmax(0,1fr))}}'
            .'</style>';
    }

    /** @return array<string, string> */
    private function attributes(string $slot): array
    {
        preg_match('/^<div\b([^>]*)>/i', $slot, $opening);
        preg_match_all('/\b(data-lms-instructors[a-z0-9-]*)\s*=\s*(["\'])(.*?)\2/is', (string) ($opening[1] ?? ''), $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match): array => [strtolower($match[1]) => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8')])->all();
    }

    private function instance(array $attributes): string
    {
        $value = strtolower((string) ($attributes['data-lms-instructors-instance'] ?? ''));
        $value = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $value), '-');

        return $value !== '' ? substr($value, 0, 80) : 'lms-instructors-'.substr(sha1((string) json_encode($attributes)), 0, 12);
    }

    private function isReady(): bool
    {
        return Schema::hasTable('lms_courses') && Route::has('lms.front.instructors.show');
    }
}
