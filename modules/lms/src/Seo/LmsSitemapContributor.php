<?php

namespace Modules\Lms\Seo;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Seo\SitemapContributor;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\CourseTranslation;

/**
 * Adds published LMS course URLs to the sitemap while LMS is active.
 */
final class LmsSitemapContributor implements SitemapContributor
{
    public function module(): string
    {
        return 'lms';
    }

    public function sitemapEntries(): iterable
    {
        $courses = Course::query()
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->orderBy('id')
            ->cursor();

        foreach ($courses as $course) {
            $alternates = [app(PlatformLocale::class)->defaultCode() => route('lms.front.courses.show', ['course' => $course->slug], false)];

            foreach (CourseTranslation::query()->where('course_id', $course->id)->pluck('slug', 'locale') as $locale => $slug) {
                $alternates[(string) $locale] = route('lms.front.courses.show', ['course' => $slug], false).'?lang='.urlencode((string) $locale);
            }

            $default = $alternates[app(PlatformLocale::class)->defaultCode()] ?? reset($alternates);
            if (is_string($default) && $default !== '') {
                $alternates['x-default'] = $default;
            }

            yield [
                'loc' => route('lms.front.courses.show', ['course' => $course->slug]),
                'lastmod' => $course->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => 0.8,
                'alternates' => $alternates,
            ];
        }
    }
}
