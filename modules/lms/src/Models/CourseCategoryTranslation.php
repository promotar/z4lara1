<?php

namespace Modules\Lms\Models;

use App\Platform\Core\Localization\PlatformLocale;
use Illuminate\Database\Eloquent\Model;

/**
 * Course categories are free text on `lms_courses.category`; their labels are
 * translated once per source value and reused by every course in that category.
 */
class CourseCategoryTranslation extends Model
{
    protected $table = 'lms_course_category_translations';

    protected $guarded = [];

    /** @var array<string, array<string, string>> */
    private static array $maps = [];

    /**
     * @return array<string, string> source category => translated label
     */
    public static function map(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();

        if ($locale === '' || $locale === app(PlatformLocale::class)->defaultCode()) {
            return [];
        }

        if (! array_key_exists($locale, self::$maps)) {
            self::$maps[$locale] = static::query()
                ->where('locale', $locale)
                ->pluck('title', 'source')
                ->map(fn ($title): string => trim((string) $title))
                ->filter()
                ->all();
        }

        return self::$maps[$locale];
    }

    public static function translate(?string $category, ?string $locale = null): string
    {
        $category = trim((string) $category);

        if ($category === '') {
            return '';
        }

        return self::map($locale)[$category] ?? $category;
    }

    /**
     * @return array<string, string> locale => title for one source category
     */
    public static function forSource(?string $source): array
    {
        $source = trim((string) $source);

        if ($source === '') {
            return [];
        }

        return static::query()
            ->where('source', $source)
            ->pluck('title', 'locale')
            ->all();
    }

    /**
     * Persist the submitted translations for one source category.
     *
     * @param  array<string, mixed>  $translations
     */
    public static function sync(?string $source, array $translations): void
    {
        $source = trim((string) $source);

        if ($source === '') {
            return;
        }

        $localeManager = app(PlatformLocale::class);
        $languages = $localeManager->languages();
        $default = $localeManager->defaultCode();

        foreach ($translations as $locale => $title) {
            $locale = str_replace('_', '-', trim((string) $locale));
            $title = trim((string) $title);

            if ($locale === '' || $locale === $default || ! isset($languages[$locale])) {
                continue;
            }

            if ($title === '') {
                static::query()->where('source', $source)->where('locale', $locale)->delete();

                continue;
            }

            static::query()->updateOrCreate(
                ['source' => $source, 'locale' => $locale],
                ['title' => mb_substr($title, 0, 120)],
            );
        }

        self::flush();
    }

    public static function flush(): void
    {
        self::$maps = [];
    }
}
