<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Localization\PlatformLocale;
use Modules\Seo\Models\SeoSetting;
use Throwable;

/**
 * Per-module, per-language templates for the automatic content SEO pipeline.
 *
 * These settings do not store per-record SEO values. They decide which
 * metadata features the SEO module contributes for a module's content.
 */
final class SeoModuleSettings
{
    public const FIELDS = [
        'seo_title' => 'SEO Title',
        'meta_description' => 'Meta Description',
        'focus_keyword' => 'Focus Keyword',
        'robots' => 'Robots',
        'canonical' => 'Canonical URL',
        'google_preview' => 'Google Search Preview',
        'social_preview' => 'Social Sharing Preview',
        'image_seo_title' => 'Image SEO Title',
        'image_seo_description' => 'Image SEO Description',
    ];

    public const DEFAULT_TEMPLATES = [
        'seo_title' => '{seo_title}',
        'meta_description' => '{meta_description}',
        'focus_keyword' => '{focus_keyword}',
        'robots' => '{robots}',
        'canonical' => '{canonical_url}',
        'google_preview' => '{google_preview}',
        'social_preview' => '{seo_title}',
        'image_seo_title' => '{content_title}',
        'image_seo_description' => '{content_description}',
    ];

    public const MODULE_LABELS = [
        'blog' => 'Articles',
        'lms' => 'Courses',
        'store' => 'Products',
        'exhibitions' => 'Artwork',
        'page-builder' => 'Pages',
    ];

    private const CONTENT_TYPE_MODULES = [
        'blog_post' => 'blog',
        'lms_course' => 'lms',
        'store_product' => 'store',
        'exhibition_artwork' => 'exhibitions',
        'page' => 'page-builder',
    ];

    public function __construct(
        private readonly SeoSiteResolver $sites,
        private readonly PlatformLocale $locales,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return self::DEFAULT_TEMPLATES;
    }

    /**
     * @return array<string, string>
     */
    public function forModule(string $module, ?string $locale = null): array
    {
        $values = $this->valuesForLocale($this->stored($module), $this->normalizeLocale($locale));
        $templates = $this->defaults();

        foreach ($templates as $field => $default) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $templates[$field] = $this->normalizeValue($values[$field], $default);
        }

        return $templates;
    }

    /**
     * @param  array<string, mixed>  $flags
     */
    public function saveModule(string $module, array $flags, ?string $locale = null): void
    {
        $locale = $this->normalizeLocale($locale);
        $stored = $this->stored($module);
        $localized = [];

        if ($stored !== []) {
            if ($this->isLegacyFlat($stored)) {
                // Preserve old shared templates for every available language
                // until each language is edited separately.
                $legacy = $this->normalizeValues($stored);

                foreach ($this->locales->available() as $code) {
                    $localized[$code] = $legacy;
                }
            } else {
                foreach ($stored as $code => $values) {
                    if (is_string($code) && is_array($values)) {
                        $localized[$code] = $this->normalizeValues($values);
                    }
                }
            }
        }

        $localized[$locale] = $this->normalizeValues($flags);

        SeoSetting::putValue(
            $this->sites->id(),
            'seo',
            'modules',
            $module,
            $localized,
            'json',
        );
    }

    public function moduleForContentType(string $type): ?string
    {
        return self::CONTENT_TYPE_MODULES[$type] ?? null;
    }

    public function label(string $module): string
    {
        return self::MODULE_LABELS[$module] ?? ucfirst(str_replace('-', ' ', $module));
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(string $module): array
    {
        try {
            return (array) SeoSetting::valueFor(
                $this->sites->id(),
                'seo',
                'modules',
                $module,
                '',
                [],
            );
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function valuesForLocale(array $stored, string $locale): array
    {
        if ($stored === []) {
            return [];
        }

        if ($this->isLegacyFlat($stored)) {
            return $stored;
        }

        $values = $stored[$locale] ?? [];

        return is_array($values) ? $values : [];
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function isLegacyFlat(array $stored): bool
    {
        return array_intersect(array_keys($stored), array_keys(self::FIELDS)) !== [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function normalizeValues(array $values): array
    {
        $normalized = [];

        foreach (array_keys(self::FIELDS) as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $normalized[$field] = $this->normalizeValue(
                $values[$field],
                self::DEFAULT_TEMPLATES[$field],
            );
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value, string $default): string
    {
        // Backward compatibility with the earlier checkbox settings.
        if (is_bool($value)) {
            return $value ? $default : '';
        }

        return mb_substr(trim((string) $value), 0, 2048);
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = $locale !== null && trim($locale) !== ''
            ? trim($locale)
            : (string) app()->getLocale();

        return $this->locales->isAvailable($locale)
            ? $locale
            : $this->locales->defaultCode();
    }
}
