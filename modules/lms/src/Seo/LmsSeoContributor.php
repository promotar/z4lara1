<?php

namespace Modules\Lms\Seo;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Seo\LocaleUrlStrategy;
use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoContributor;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\CourseTranslation;
use Throwable;

/**
 * SEO adapter for LMS course pages.
 */
final class LmsSeoContributor implements SeoContributor
{
    public function module(): string
    {
        return 'lms';
    }

    public function supports(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'lms.front.courses',
            'lms.front.courses.show',
            'lms.front.instructors.show',
        ], true);
    }

    public function contribute(SeoData $seo, Request $request): void
    {
        try {
            match ($request->route()?->getName()) {
                'lms.front.courses' => $this->catalog($seo),
                'lms.front.courses.show' => $this->course($seo, $request),
                'lms.front.instructors.show' => $this->instructor($seo, $request),
                default => null,
            };
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function catalog(SeoData $seo): void
    {
        $seo->title = 'Courses';
        $seo->description = 'Explore practical ART INPA courses and build new creative and professional skills.';
        $seo->ogTitle = $seo->title;
        $seo->ogDescription = $seo->description;
        $seo->extra['replace_title'] = true;
        $seo->schema[] = $this->collectionSchema($seo, 'Courses');
        $seo->schema[] = $this->breadcrumbs([
            ['name' => 'Home', 'item' => url('/')],
            ['name' => 'Courses', 'item' => $seo->canonical],
        ]);
    }

    private function course(SeoData $seo, Request $request): void
    {
        $courseParameter = $request->route('course');
        $slug = $courseParameter instanceof Course
            ? (string) $courseParameter->getRawOriginal('slug')
            : (string) ($courseParameter ?: $request->segment(2));
        $locale = app()->getLocale();
        $isDefaultLocale = $locale === PlatformLocale::DEFAULT;
        $course = null;
        $translation = null;

        if ($locale !== PlatformLocale::DEFAULT) {
            $translation = CourseTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->first();

            if ($translation !== null) {
                $course = Course::query()->find($translation->course_id);
            }
        }

        if ($course === null) {
            $course = Course::query()->where('slug', $slug)->first();
        }

        if ($course === null || $course->status !== 'published') {
            return;
        }

        if ($course->visibility !== null && $course->visibility !== 'public') {
            return;
        }

        $baseSlug = (string) $course->getRawOriginal('slug');
        $translation = CourseTranslation::resolve($course, $locale);
        $translation?->applyTo($course);

        $localeUrls = app(LocaleUrlStrategy::class);
        $canonical = $isDefaultLocale ? trim((string) ($course->getAttribute('canonical_url') ?: '')) : '';
        if ($canonical === '') {
            $canonical = $localeUrls->withLocale(
                route('lms.front.courses.show', ['course' => $translation?->slug ?: $baseSlug], false),
                $locale,
            );
        } elseif ($translation !== null) {
            $canonical = $localeUrls->withLocale(
                route('lms.front.courses.show', ['course' => $translation->slug], false),
                $locale,
            );
        }

        $description = $this->text(
            ($isDefaultLocale ? $course->getAttribute('meta_description') : null)
                ?: $course->short_description
                ?: $course->subtitle
                ?: $course->description,
            160,
        );

        $seo->title = trim((string) ((($isDefaultLocale ? $course->getAttribute('meta_title') : null) ?: $course->title))) ?: null;
        $seo->description = $description;
        $seo->canonical = $this->absolute($canonical);
        $seo->ogType = 'website';
        $seo->ogTitle = $seo->title;
        $seo->ogDescription = $description;
        $seo->twitterTitle = $seo->title;
        $seo->twitterDescription = $description;
        if ($isDefaultLocale && ($course->getAttribute('robots_index') !== null || $course->getAttribute('robots_follow') !== null)) {
            $seo->robots = $seo->robots->merge(new Robots(
                index: (bool) ($course->getAttribute('robots_index') ?? true),
                follow: (bool) ($course->getAttribute('robots_follow') ?? true),
            ));
        }

        $image = $this->courseImage($course);
        if ($image !== null) {
            $seo->image = $image;
            $seo->imageAlt = $course->title;
            $seo->twitterImage = $image;
            $seo->twitterCard = 'summary_large_image';
        }

        $seo->extra['lms_course_id'] = (int) $course->id;
        $seo->extra['lms_course_slug'] = $baseSlug;
        $seo->extra['content_title'] = strip_tags((string) $course->title);
        $seo->extra['content_description'] = strip_tags((string) ($course->short_description ?: $course->subtitle ?: $course->description));
        $seo->extra['content_text'] = implode(' ', array_filter([
            strip_tags((string) $course->title),
            strip_tags((string) $course->short_description),
            strip_tags((string) $course->subtitle),
            strip_tags((string) $course->overview),
            strip_tags((string) $course->description),
        ]));
        $seo->extra['replace_title'] = true;
        $seo->alternates = $this->alternates($course, $baseSlug);

        $seo->schema[] = $this->courseSchema($course, $seo, $canonical, $description);
        $offer = $this->offerSchema($course, $canonical);
        if ($offer !== null) {
            $seo->schema[] = $offer;
        }
        $seo->schema[] = $this->breadcrumbs([
            ['name' => 'Home', 'item' => url('/')],
            ['name' => 'Courses', 'item' => route('lms.front.courses')],
            ['name' => $course->title, 'item' => $canonical],
        ]);

        $faq = $this->faqSchema($course);
        if ($faq !== null) {
            $seo->schema[] = $faq;
        }
    }

    private function instructor(SeoData $seo, Request $request): void
    {
        $slug = (string) ($request->route('instructor') ?: $request->segment(2));
        $courses = Course::query()
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->get()
            ->filter(fn (Course $course): bool => Str::slug((string) ($course->instructor_name ?: config('app.name', 'ART INPA'))) === $slug)
            ->values();

        if ($courses->isEmpty()) {
            return;
        }

        $name = (string) ($courses->first()->instructor_name ?: config('app.name', 'ART INPA'));
        $title = (string) ($courses->first()->instructor_title ?: 'Course Instructor');

        $seo->title = $name;
        $seo->description = $this->text($title.' at ART INPA. Explore their published courses.', 160);
        $seo->ogTitle = $seo->title;
        $seo->ogDescription = $seo->description;
        $seo->extra['replace_title'] = true;
        $seo->schema[] = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'name' => $name,
            'description' => $seo->description,
            'url' => $seo->canonical,
            'mainEntity' => [
                '@type' => 'Person',
                'name' => $name,
                'jobTitle' => $title,
            ],
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $seo->schema[] = $this->breadcrumbs([
            ['name' => 'Home', 'item' => url('/')],
            ['name' => 'Courses', 'item' => route('lms.front.courses')],
            ['name' => $name, 'item' => $seo->canonical],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function alternates(Course $course, string $baseSlug): array
    {
        $localeUrls = app(LocaleUrlStrategy::class);
        $defaultLocale = app(PlatformLocale::class)->defaultCode();
        $baseUrl = route('lms.front.courses.show', ['course' => $baseSlug], false);
        $alternates = [
            $defaultLocale => $localeUrls->withLocale($baseUrl, $defaultLocale),
        ];

        foreach (CourseTranslation::query()->where('course_id', $course->id)->pluck('slug', 'locale') as $locale => $slug) {
            $alternates[(string) $locale] = $localeUrls->withLocale(
                route('lms.front.courses.show', ['course' => $slug], false),
                (string) $locale,
            );
        }

        foreach (array_keys(app(PlatformLocale::class)->languages()) as $code) {
            $code = (string) $code;
            $alternates[$code] ??= $localeUrls->withLocale($baseUrl, $code);
        }

        $default = $alternates[$defaultLocale] ?? reset($alternates);
        if (is_string($default) && $default !== '') {
            $alternates['x-default'] = $default;
        }

        return $alternates;
    }

    /**
     * @return array<string, mixed>
     */
    private function courseSchema(Course $course, SeoData $seo, string $canonical, ?string $description): array
    {
        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => $course->getAttribute('schema_type') ?: 'Course',
            'name' => $course->title,
            'description' => $description,
            'url' => $canonical,
            'image' => $seo->image,
            'provider' => [
                '@type' => 'Organization',
                'name' => $seo->siteName ?: config('app.name', 'ART INPA'),
                'url' => url('/'),
            ],
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $mode = match ((string) ($course->training_mode ?? '')) {
            'online' => 'online',
            'in_person', 'onsite' => 'onsite',
            'blended' => 'blended',
            default => null,
        };

        $instance = array_filter([
            '@type' => 'CourseInstance',
            'courseMode' => $mode,
            'startDate' => $course->publish_date?->toDateString(),
            'location' => $mode === 'onsite'
                ? ['@type' => 'Place', 'name' => $seo->siteName ?: config('app.name', 'ART INPA')]
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if (count($instance) > 1) {
            $schema['hasCourseInstance'] = $instance;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function offerSchema(Course $course, string $canonical): ?array
    {
        $price = $course->sale_price ?? $course->regular_price ?? $course->online_price ?? $course->in_person_price;

        if ($price === null && (string) ($course->pricing_type ?? '') !== 'free') {
            return null;
        }

        $price ??= 0;

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Offer',
            'price' => number_format((float) $price, 2, '.', ''),
            'priceCurrency' => (string) ($course->currency ?: 'USD'),
            'availability' => 'https://schema.org/InStock',
            'url' => $canonical,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function faqSchema(Course $course): ?array
    {
        $entities = [];

        foreach ((array) ($course->faqs ?: []) as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $question = trim((string) ($faq['question'] ?? $faq['q'] ?? ''));
            $answer = trim(strip_tags((string) ($faq['answer'] ?? $faq['a'] ?? '')));

            if ($question === '' || $answer === '') {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * @param  array<int, array{name: string, item: ?string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
            ];

            if (filled($item['item'])) {
                $entry['item'] = $this->absolute((string) $item['item']);
            }

            $elements[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionSchema(SeoData $seo, string $name): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $seo->description,
            'url' => $seo->canonical,
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function courseImage(Course $course): ?string
    {
        $meta = is_array($course->featured_image_meta) ? $course->featured_image_meta : [];
        $url = trim((string) ($meta['media_url'] ?? $course->featured_image_path ?? ''));

        if ($url === '') {
            return null;
        }

        return $this->absolute($url);
    }

    private function text(?string $value, int $limit): ?string
    {
        $value = trim(strip_tags((string) $value));

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit);
    }

    private function absolute(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
