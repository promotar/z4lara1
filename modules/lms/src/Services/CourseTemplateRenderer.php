<?php

namespace Modules\Lms\Services;

use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\Lesson;
use Modules\Lms\Models\Template;

class CourseTemplateRenderer
{
    /**
     * Render a saved LMS template. Markup between {{#courses}} and {{/courses}}
     * is repeated for every supplied course. The renderer is shared by the public
     * Courses page and Page Builder while LMS owns querying and course data.
     *
     * @param  iterable<int, Course>  $courses
     * @param  array<string, scalar|null>  $globals
     * @param  array{wrap_courses?: bool}  $options
     */
    public function render(Template $template, iterable $courses, array $globals = [], array $options = []): string
    {
        $items = $courses instanceof Enumerable ? $courses->values() : collect($courses)->values();
        $markup = (string) $template->html_code;

        $markup = preg_replace_callback(
            '/\{\{#courses\}\}([\s\S]*?)\{\{\/courses\}\}/i',
            function (array $match) use ($items, $globals, $options): string {
                $rendered = $items
                    ->map(fn (Course $course): string => $this->renderCourseMarkup($match[1], $course, $globals))
                    ->implode('');

                return ($options['wrap_courses'] ?? false)
                    ? '<div data-lms-template-items>'.$rendered.'</div>'
                    : $rendered;
            },
            $markup,
        ) ?? $markup;

        if ($items->count() === 1) {
            $markup = $this->renderCourseMarkup($markup, $items->first(), $globals);
        }

        $markup = $this->replaceTokens($markup, array_merge([
            'site_name' => (string) \Modules\Lms\Support\PlatformBrand::name(),
            'catalog_title' => 'Explore Our Courses',
            'catalog_description' => 'Build practical skills with courses created by experienced instructors.',
            'results_count' => (string) $items->count(),
            'courses_url' => route('lms.front.courses', [], false),
        ], $globals));

        $slug = e($template->slug);
        $output = '<div data-lms-template="'.$slug.'">'
            .'<style data-lms-template-style="'.$slug.'">'.(string) $template->css_code.'</style>'
            .$markup
            .'</div>';

        if (trim((string) $template->js_code) !== '') {
            $output .= '<script src="'.e(route('lms.front.template-script', ['slug' => $template->slug], false)).'" defer data-lms-template-script="'.$slug.'"></script>';
        }

        return $output;
    }

    /** @param array<string, scalar|null> $globals */
    private function renderCourseMarkup(string $markup, Course $course, array $globals): string
    {
        $lessons = $course->relationLoaded('lessons')
            ? $course->lessons->values()
            : $course->lessons()->where('status', 'published')->get();

        $collections = [
            'outcomes' => collect((array) $course->outcomes)->filter()->values()->map(fn ($value): array => ['text' => e(is_array($value) ? (string) ($value['title'] ?? '') : (string) $value)]),
            'requirements' => collect((array) $course->requirements)->filter()->values()->map(fn ($value): array => ['text' => e((string) $value)]),
            'audience' => collect((array) $course->target_audience)->filter()->values()->map(fn ($value): array => ['text' => e((string) $value)]),
            'faqs' => collect((array) $course->faqs)->filter()->values()->map(fn ($faq): array => [
                'question' => e((string) (is_array($faq) ? ($faq['question'] ?? '') : '')),
                'answer' => e((string) (is_array($faq) ? ($faq['answer'] ?? '') : '')),
            ]),
            'lessons' => $lessons->values()->map(fn (Lesson $lesson, int $index): array => [
                'id' => (string) $lesson->id,
                'number' => (string) ($index + 1),
                'title' => e((string) $lesson->title),
                'slug' => e((string) $lesson->slug),
                'type' => e(ucfirst((string) ($lesson->lesson_type ?: 'lesson'))),
                'duration' => e($this->lessonDuration($lesson)),
                'preview_url' => $lesson->free_preview ? e(route('lms.front.courses.preview', ['course' => $course, 'lesson' => $lesson], false)) : '',
            ]),
        ];

        foreach ($collections as $name => $values) {
            $markup = preg_replace_callback(
                '/\{\{#'.preg_quote($name, '/').'\}\}([\s\S]*?)\{\{\/'.preg_quote($name, '/').'\}\}/i',
                fn (array $match): string => $values->map(fn (array $value): string => $this->replaceTokens($match[1], $value))->implode(''),
                $markup,
            ) ?? $markup;
        }

        return $this->replaceTokens($markup, array_merge($this->courseValues($course, $lessons), $globals));
    }

    /** @return array<string, string> */
    private function courseValues(Course $course, Enumerable $lessons): array
    {
        $regular = $course->regular_price !== null ? (float) $course->regular_price : 0.0;
        $sale = $course->sale_price !== null ? (float) $course->sale_price : 0.0;
        $price = $course->pricing_type === 'free' ? 0.0 : ($sale > 0 ? $sale : $regular);
        $currency = strtoupper((string) ($course->currency ?: 'USD'));
        $minutes = (int) $lessons->sum(fn (Lesson $lesson): int => ((int) $lesson->duration_hours * 60) + (int) $lesson->duration_minutes);
        $description = $this->plainDescription($course);
        $faqMarkup = $this->faqMarkup($course);
        $learnMarkup = $this->learnMarkup($course);

        return [
            'id' => (string) $course->id,
            'slug' => e((string) $course->slug),
            'title' => e((string) $course->title),
            'subtitle' => e((string) ($course->subtitle ?: $course->short_description)),
            'short_description' => e((string) ($course->short_description ?: Str::limit($description, 180))),
            'description' => e($description),
            'description_html' => nl2br(e($description)),
            'url' => e(route('lms.front.courses.show', $course, false)),
            'image_url' => e($this->imageUrl($course)),
            'category' => e((string) ($course->category ?: 'Course')),
            'level' => e((string) ($course->level ?: 'All levels')),
            'language' => e((string) ($course->language ?: 'English')),
            'instructor_name' => e((string) ($course->instructor_name ?: \Modules\Lms\Support\PlatformBrand::name())),
            'instructor_initial' => e(Str::upper(Str::substr((string) ($course->instructor_name ?: 'A'), 0, 1))),
            'instructor_title' => e((string) ($course->instructor_title ?: 'Course Instructor')),
            'pricing_type' => e((string) ($course->pricing_type ?: 'free')),
            'regular_price' => $regular > 0 ? number_format($regular, 2) : '',
            'sale_price' => $sale > 0 ? number_format($sale, 2) : '',
            'price' => $price > 0 ? number_format($price, 2) : '0',
            'price_label' => $price > 0 ? e(number_format($price, 2).' '.$currency) : 'Free',
            'training_prices' => $this->trainingPricesMarkup($course, $currency),
            'about_cards' => $this->aboutCardsMarkup($course),
            'learn_tab' => $learnMarkup['tab'],
            'learn_panel' => $learnMarkup['panel'],
            'faq_tab' => $faqMarkup['tab'],
            'faq_panel' => $faqMarkup['panel'],
            'currency' => e($currency),
            'lesson_count' => (string) $lessons->count(),
            'student_count' => (string) ($course->enrollments_count ?? $course->enrollments()->count()),
            'duration' => e($this->durationLabel($minutes)),
            'access_duration' => e((string) ($course->access_duration ?: 'Lifetime')),
            'visibility' => e((string) ($course->visibility ?: 'public')),
            'published_at' => e((string) ($course->published_at?->format('F j, Y') ?: '')),
        ];
    }



    private function plainDescription(Course $course): string
    {
        $raw = (string) ($course->description ?: $course->short_description);
        $raw = preg_replace('/<\s*br\s*\/?>/i', "\n", $raw) ?? $raw;
        $raw = preg_replace('/<\s*\/\s*(p|div|li|h[1-6]|blockquote|tr)\s*>/i', "\n\n", $raw) ?? $raw;
        $raw = preg_replace('/<\s*li\b[^>]*>/i', '* ', $raw) ?? $raw;
        $text = trim(strip_tags($raw));
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return $text;
    }
    private function aboutCardsMarkup(Course $course): string
    {
        $description = $this->plainDescription($course);
        $overview = trim((string) ($course->overview ?? ''));
        $aboutBody = nl2br(e($overview !== '' ? $overview : $description));

        if ($overview !== '' && $description !== '' && mb_strlen($description) > mb_strlen($overview) + 40) {
            $aboutBody .= '<div class="lms-details-more" hidden>'.nl2br(e($description)).'</div>'
                .'<button type="button" class="lms-details-more-btn" data-lms-more>Read more</button>';
        }

        $cards = '<article class="ainpa-course-info-card"><h2>About This Course</h2><div class="lms-details-description">'.$aboutBody.'</div></article>';

        $groups = [

            ['Course Requirements', collect((array) $course->requirements)->filter()->values(), ''],
            ['Who This Course Is For', collect((array) $course->target_audience)->filter()->values(), ''],
        ];

        foreach ($groups as $group) {
            [$title, $items, $class] = $group;

            if ($items->isEmpty()) {
                continue;
            }

            $listItems = $items->map(fn ($item): string => '<li>'.e((string) $item).'</li>')->implode('');
            $cards .= '<article class="ainpa-course-info-card"><h2>'.$title.'</h2><ul'.($class !== '' ? ' class="'.$class.'"' : '').'>'.$listItems.'</ul></article>';
        }

        return $cards;
    }

    /** @return array{tab: string, panel: string} */
    private function learnMarkup(Course $course): array
    {
        $items = collect((array) $course->outcomes)->filter()->values();

        if ($items->isEmpty()) {
            return ['tab' => '', 'panel' => ''];
        }

        $hasText = $items->contains(fn ($item): bool => is_array($item) && trim((string) ($item['text'] ?? '')) !== '');

        if ($hasText) {
            $inner = '<div class="lms-learn-grid">'.$items->map(function ($item): string {
                $title = is_array($item) ? trim((string) ($item['title'] ?? '')) : trim((string) $item);
                $text = is_array($item) ? trim((string) ($item['text'] ?? '')) : '';

                if ($title === '' && $text === '') {
                    return '';
                }

                return '<div class="lms-learn-card">'
                    .($title !== '' ? '<h3>'.e($title).'</h3>' : '')
                    .($text !== '' ? '<p>'.e($text).'</p>' : '')
                    .'</div>';
            })->implode('').'</div>';
        } else {
            $inner = '<ul class="ainpa-course-check-list">'.$items->map(fn ($item): string => '<li>'.e(is_array($item) ? (string) ($item['title'] ?? '') : (string) $item).'</li>')->implode('').'</ul>';
        }

        return [
            'tab' => '<button type="button" data-course-tab="learn">What You Will Learn</button>',
            'panel' => '<section class="ainpa-course-tab-panel" data-course-panel="learn"><article class="ainpa-course-info-card"><h2>What You Will Learn</h2>'.$inner.'</article></section>',
        ];
    }
    /** @return array{tab: string, panel: string} */
    private function faqMarkup(Course $course): array
    {
        $faqs = collect((array) $course->faqs)
            ->filter(fn ($faq): bool => is_array($faq) && trim((string) ($faq['question'] ?? '')) !== '')
            ->values();

        if ($faqs->isEmpty()) {
            return ['tab' => '', 'panel' => ''];
        }

        $items = $faqs->map(fn (array $faq): string => '<details class="ainpa-course-faq"><summary>'.e((string) ($faq['question'] ?? '')).'</summary><p>'.e((string) ($faq['answer'] ?? '')).'</p></details>')->implode('');

        return [
            'tab' => '<button type="button" data-course-tab="faq">FAQ</button>',
            'panel' => '<section class="ainpa-course-tab-panel" data-course-panel="faq"><div class="ainpa-course-faq-list">'.$items.'</div></section>',
        ];
    }

    private function trainingPricesMarkup(Course $course, string $currency): string
    {
        $mode = in_array($course->training_mode, ['online', 'in_person', 'both'], true)
            ? (string) $course->training_mode
            : 'online';

        if ($mode === 'online') {
            return '';
        }

        $rows = [];

        if ($mode === 'both' && (float) $course->online_price > 0) {
            $rows[] = ['Online training', (float) $course->online_price];
        }

        if ((float) $course->in_person_price > 0) {
            $rows[] = ['In-person training', (float) $course->in_person_price];
        }

        if ($rows === []) {
            return '';
        }

        $html = '<div class="lms-training-prices" style="display:flex;flex-direction:column;gap:6px;margin:10px 0 0;padding:10px 12px;border-radius:10px;background:#f7f5ff;border:1px solid #e3dcff">';

        foreach ($rows as $row) {
            $html .= '<span style="display:flex;gap:8px;justify-content:space-between;font-size:13px;color:#374151">'.e($row[0])
                .'<b style="color:#6d28d9;font-weight:800">'.e(number_format($row[1], 2).' '.$currency).'</b></span>';
        }

        return $html.'</div>';
    }

    private function courseImageBySlug(Course $course): string
    {
        $candidates = array_filter([
            \Illuminate\Support\Str::slug((string) $course->slug),
            \Illuminate\Support\Str::slug((string) $course->title),
        ]);

        foreach ($candidates as $candidate) {
            foreach (['media/courses/', 'media/', 'courses/', '', 'lms/course-images/'] as $folder) {
                foreach (['webp', 'jpg', 'jpeg', 'png'] as $extension) {
                    $path = $folder.$candidate.'.'.$extension;

                    if (Storage::disk('public')->exists($path)) {
                        return route('lms.front.media.file', ['path' => $path], false);
                    }
                }
            }
        }

        return '';
    }
    private function courseImagePlaceholder(): string
    {
        return 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="750"><rect width="100%" height="100%" fill="#f1e5e5"/><text x="50%" y="50%" text-anchor="middle" fill="#7f1d1d" font-family="Arial" font-size="54">Course</text></svg>');
    }
    private function imageUrl(Course $course): string
    {
        $meta = is_array($course->featured_image_meta) ? $course->featured_image_meta : [];
        $url = trim((string) ($meta['media_url'] ?? ''));

        if ($url === '') {
            $url = trim((string) $course->featured_image_path);
        }

        if ($url === '') {
            $slugFallback = $this->courseImageBySlug($course);

            return $slugFallback !== '' ? $slugFallback : $this->courseImagePlaceholder();
        }

        if (Str::startsWith($url, 'data:')) {
            return $url;
        }

        $parsedPath = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        $relative = ltrim($parsedPath, '/');

        foreach (['storage/', 'lms/media-file/'] as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $relative = substr($relative, strlen($prefix));
                break;
            }
        }

        if ($relative === '') {
            return $url;
        }

        if (Storage::disk('public')->exists($relative) || is_file(public_path($relative)) || is_file(storage_path('app/'.$relative))) {
            return route('lms.front.media.file', ['path' => $relative], false);
        }

        $slugFallback = $this->courseImageBySlug($course);

        if ($slugFallback !== '') {
            return $slugFallback;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return $this->courseImagePlaceholder();
    }

    private function lessonDuration(Lesson $lesson): string
    {
        return $this->durationLabel(((int) $lesson->duration_hours * 60) + (int) $lesson->duration_minutes);
    }

    private function durationLabel(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return trim(($hours > 0 ? $hours.'h ' : '').($remaining > 0 ? $remaining.'m' : ($hours === 0 ? '0m' : '')));
    }

    /** @param array<string, scalar|null> $values */
    private function replaceTokens(string $markup, array $values): string
    {
        foreach (['about_cards', 'learn_tab', 'learn_panel', 'faq_tab', 'faq_panel', 'training_prices', 'description_html', 'action_button', 'course_curriculum', 'promo_card', 'category_options', 'level_options', 'sort_options', 'empty_state', 'pagination_html'] as $rawToken) {
            $markup = preg_replace_callback(
                '/\{\{\{\s*'.preg_quote($rawToken, '/').'\s*\}\}\}/i',
                fn (): string => (string) ($values[$rawToken] ?? ''),
                $markup,
            ) ?? $markup;
        }

        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn (array $match): string => array_key_exists($match[1], $values) ? (string) $values[$match[1]] : $match[0],
            $markup,
        ) ?? $markup;
    }
}
