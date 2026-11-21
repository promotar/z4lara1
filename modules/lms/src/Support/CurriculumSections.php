<?php

namespace Modules\Lms\Support;

use App\Platform\Core\Localization\PlatformLocale;

/**
 * Curriculum unit ("section") titles are stored on lessons as `module_title`.
 * Their translations live next to each unit inside the course `curriculum_units`
 * JSON, so translating a section never needs an extra table or a migration.
 */
final class CurriculumSections
{
    /**
     * @return array<string, string> locale code => language label
     */
    public static function languages(): array
    {
        return app(PlatformLocale::class)->languages();
    }

    public static function defaultCode(): string
    {
        return app(PlatformLocale::class)->defaultCode();
    }

    /**
     * Keep only available locales with a non-empty title; the base language
     * stays on the unit title itself and is never duplicated here.
     *
     * @return array<string, string>
     */
    public static function normalize(mixed $input, string $baseTitle = ''): array
    {
        if (! is_array($input)) {
            return [];
        }

        $languages = self::languages();
        $default = self::defaultCode();
        $base = trim($baseTitle);
        $result = [];

        foreach ($input as $locale => $title) {
            $locale = str_replace('_', '-', trim((string) $locale));
            $title = trim((string) $title);

            if ($locale === '' || $title === '' || ! isset($languages[$locale])) {
                continue;
            }

            if ($locale === $default || ($base !== '' && $title === $base)) {
                continue;
            }

            $result[$locale] = mb_substr($title, 0, 160);
        }

        return $result;
    }

    /**
     * Resolve the translations to persist for one submitted unit: submitted
     * values win (an explicitly cleared field stays cleared), otherwise the
     * stored translation is preserved and matched by unit key, then by title.
     *
     * @param  array<string, mixed>  $unit
     * @param  array<string, array<string, mixed>>  $storedByKey
     * @param  array<string, array<string, mixed>>  $storedByTitle
     * @return array<string, string>
     */
    public static function resolveForSave(array $unit, array $storedByKey, array $storedByTitle): array
    {
        $title = trim((string) ($unit['title'] ?? ''));

        if (array_key_exists('translations', $unit)) {
            return self::normalize($unit['translations'] ?? [], $title);
        }

        $stored = $storedByKey[(string) ($unit['key'] ?? '')]
            ?? $storedByTitle[$title]
            ?? null;

        return self::normalize(is_array($stored) ? ($stored['translations'] ?? []) : [], $title);
    }

    /**
     * Index stored units by their key and by their title.
     *
     * @param  mixed  $units
     * @return array{byKey: array<string, array<string, mixed>>, byTitle: array<string, array<string, mixed>>}
     */
    public static function index(mixed $units): array
    {
        $byKey = [];
        $byTitle = [];

        if (! is_array($units)) {
            return ['byKey' => $byKey, 'byTitle' => $byTitle];
        }

        foreach ($units as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $title = trim((string) ($unit['title'] ?? ''));
            $key = (string) ($unit['key'] ?? '');

            if ($key !== '') {
                $byKey[$key] = $unit;
            }

            if ($title !== '') {
                $byTitle[$title] = $unit;
            }
        }

        return ['byKey' => $byKey, 'byTitle' => $byTitle];
    }

    /**
     * Build a map of base unit title => translated title for one locale.
     *
     * @param  mixed  $units
     * @return array<string, string>
     */
    public static function mapFor(mixed $units, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();

        if ($locale === '' || $locale === self::defaultCode() || ! is_array($units)) {
            return [];
        }

        $map = [];

        foreach ($units as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $title = trim((string) ($unit['title'] ?? ''));
            $translations = $unit['translations'] ?? [];

            if (is_string($translations)) {
                $translations = json_decode($translations, true) ?: [];
            }

            $translated = is_array($translations) ? trim((string) ($translations[$locale] ?? '')) : '';

            if ($title !== '' && $translated !== '') {
                $map[$title] = $translated;
            }
        }

        return $map;
    }

    /**
     * Replace unit titles of the given lessons in memory for frontend display.
     *
     * @param  iterable<int, object>  $lessons
     * @param  mixed  $units
     */
    public static function apply(iterable $lessons, mixed $units, ?string $locale = null): void
    {
        $map = self::mapFor($units, $locale);

        if ($map === []) {
            return;
        }

        foreach ($lessons as $lesson) {
            $title = trim((string) ($lesson->module_title ?? ''));

            if ($title !== '' && isset($map[$title])) {
                $lesson->module_title = $map[$title];
            }
        }
    }
}
